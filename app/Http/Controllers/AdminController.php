<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function index(InventoryService $inventory)
    {
        $now = now();
        $thisMonth = $now->copy()->startOfMonth();
        $lastMonthStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $lastMonthSameDay = $now->copy()->subMonthNoOverflow();

        $revenue = fn ($from, $to) => (float) Order::revenue()->whereBetween('created_at', [$from, $to])->sum('total_amount');
        $orders = fn ($from, $to) => Order::revenue()->whereBetween('created_at', [$from, $to])->count();

        // Month-to-date compared with the same period last month.
        $revenueThisMonth = $revenue($thisMonth, $now);
        $revenueLastMonth = $revenue($lastMonthStart, $lastMonthSameDay);
        $ordersThisMonth = $orders($thisMonth, $now);
        $ordersLastMonth = $orders($lastMonthStart, $lastMonthSameDay);

        $profit = $this->grossProfit($thisMonth, $now);

        $today = Order::revenue()->where('created_at', '>=', $now->copy()->startOfDay())
            ->selectRaw('channel, COUNT(*) as orders, COALESCE(SUM(total_amount), 0) as revenue')
            ->groupBy('channel')->get()->keyBy('channel');

        $stats = [
            'revenue' => $revenueThisMonth,
            'revenue_change' => $this->percentChange($revenueThisMonth, $revenueLastMonth),
            'orders' => $ordersThisMonth,
            'orders_change' => $this->percentChange($ordersThisMonth, $ordersLastMonth),
            'profit' => $profit['profit'],
            'margin' => $profit['margin'],
            'customers' => User::where('role', User::ROLE_CUSTOMER)->count(),
            'new_customers' => User::where('role', User::ROLE_CUSTOMER)->where('created_at', '>=', $thisMonth)->count(),
            'avg_order' => (float) Order::revenue()->avg('total_amount'),
            'pending' => Order::where('status', Order::STATUS_PENDING)->count(),
            'processing' => Order::where('status', Order::STATUS_PROCESSING)->count(),
            'today_online' => $today->get(Order::CHANNEL_ONLINE),
            'today_pos' => $today->get(Order::CHANNEL_POS),
            'stock' => $inventory->summary(),
            'products' => Product::count(),
        ];

        $daily = $this->dailySeries(30);
        $recentOrders = Order::with('user')->withCount('items')->latest()->take(8)->get();
        $topProducts = Product::getTopProducts(5);
        $lowStock = Product::with('stock')->active()->needsReorder()->get()->sortBy('stock_quantity')->take(6);
        $categorySales = $this->categorySales(now()->subDays(90));

        return view('admin.dashboard', compact('stats', 'daily', 'recentOrders', 'topProducts', 'lowStock', 'categorySales'));
    }

    public function customers(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $customers = User::where('role', User::ROLE_CUSTOMER)
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->withCount('orders')
            ->withSum(['orders as total_spent' => fn ($q) => $q->revenue()], 'total_amount')
            ->withMax('orders as last_order_at', 'created_at')
            ->orderByDesc('total_spent')
            ->paginate(20)
            ->withQueryString();

        return view('admin.customers', compact('customers', 'search'));
    }

    public function salesReports(Request $request)
    {
        $days = in_array((int) $request->query('days'), [7, 30, 90], true) ? (int) $request->query('days') : 30;
        $since = now()->subDays($days - 1)->startOfDay();

        $daily = $this->dailySeries($days);
        $totalOrders = array_sum($daily['orders']);
        $totalSales = array_sum($daily['revenue']);
        $profit = $this->grossProfit($since, now());

        $monthly = $this->monthlySeries(12);
        $categorySales = $this->categorySales($since);

        $statusBreakdown = Order::where('created_at', '>=', $since)
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $channels = Order::revenue()->where('created_at', '>=', $since)
            ->selectRaw('channel, COUNT(*) as orders, COALESCE(SUM(total_amount), 0) as revenue')
            ->groupBy('channel')->get()->keyBy('channel');

        $payments = Order::revenue()->where('created_at', '>=', $since)
            ->selectRaw('payment_method, COUNT(*) as orders, COALESCE(SUM(total_amount), 0) as revenue')
            ->groupBy('payment_method')->orderByDesc('revenue')->get();

        $topProducts = Product::getTopProducts(10);

        return view('admin.sales-reports', compact(
            'days', 'daily', 'totalOrders', 'totalSales', 'profit', 'monthly', 'categorySales',
            'statusBreakdown', 'channels', 'payments', 'topProducts'
        ));
    }

    /**
     * Gross profit on goods: item revenue minus item cost at the time of sale.
     * Order-level discounts are spread over the goods; delivery fees are excluded.
     * Items sold before cost prices were recorded are left out of the margin.
     */
    private function grossProfit(Carbon $from, Carbon $to): array
    {
        $row = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', Order::REVENUE_STATUSES)
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereNotNull('order_items.cost')
            ->selectRaw('COALESCE(SUM(order_items.quantity * order_items.price * (1 - CASE WHEN orders.subtotal > 0 THEN orders.discount_amount / orders.subtotal ELSE 0 END)), 0) as net_sales')
            ->selectRaw('COALESCE(SUM(order_items.quantity * order_items.cost), 0) as cost')
            ->first();

        $sales = (float) $row->net_sales;
        $profit = $sales - (float) $row->cost;

        return [
            'sales' => round($sales, 2),
            'profit' => round($profit, 2),
            'margin' => $sales > 0 ? round($profit / $sales * 100, 1) : null,
        ];
    }

    private function percentChange(float $current, float $previous): ?float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /** Orders and revenue per day for the last N days (oldest first). */
    private function dailySeries(int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $rows = Order::revenue()->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as orders, SUM(total_amount) as revenue')
            ->groupBy('day')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->day)->toDateString());

        $series = ['labels' => [], 'orders' => [], 'revenue' => []];

        for ($date = $start->copy(); $date->lte(now()); $date->addDay()) {
            $row = $rows->get($date->toDateString());
            $series['labels'][] = $date->format('d M');
            $series['orders'][] = (int) ($row->orders ?? 0);
            $series['revenue'][] = round((float) ($row->revenue ?? 0), 2);
        }

        return $series;
    }

    /** Revenue per month for the last N months (grouped in PHP: works on MySQL and SQLite). */
    private function monthlySeries(int $months): array
    {
        $start = now()->subMonthsNoOverflow($months - 1)->startOfMonth();

        $byMonth = Order::revenue()->where('created_at', '>=', $start)
            ->get(['created_at', 'total_amount'])
            ->groupBy(fn ($order) => $order->created_at->format('Y-m'));

        $series = ['labels' => [], 'revenue' => [], 'orders' => []];

        for ($month = $start->copy(); $month->lte(now()); $month->addMonthNoOverflow()) {
            $group = $byMonth->get($month->format('Y-m'), collect());
            $series['labels'][] = $month->format('M Y');
            $series['revenue'][] = round((float) $group->sum('total_amount'), 2);
            $series['orders'][] = $group->count();
        }

        return $series;
    }

    private function categorySales(Carbon $since): Collection
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereIn('orders.status', Order::REVENUE_STATUSES)
            ->where('orders.created_at', '>=', $since)
            ->selectRaw('products.category as category, SUM(order_items.quantity * order_items.price) as revenue, SUM(order_items.quantity) as units')
            ->groupBy('products.category')
            ->orderByDesc('revenue')
            ->get();
    }
}
