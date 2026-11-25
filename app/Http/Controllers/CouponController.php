<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index()
    {
        $coupons = Coupon::withCount(['orders as uses_count' => fn ($q) => $q->where('status', '!=', 'cancelled')])
            ->withSum(['orders as discount_given' => fn ($q) => $q->revenue()], 'discount_amount')
            ->orderByDesc('is_active')
            ->orderByRaw('expiry_date < ? asc', [now()])
            ->orderBy('expiry_date')
            ->get();

        return view('admin.coupons.index', compact('coupons'));
    }

    public function create()
    {
        return view('admin.coupons.create');
    }

    public function store(Request $request)
    {
        $request->merge(['name' => strtoupper(trim((string) $request->name))]);

        $data = $request->validate([
            'name' => 'required|string|max:50|alpha_dash|unique:coupons,name',
            'discount' => 'required|numeric|min:1|max:100',
            'min_order_amount' => 'nullable|numeric|min:0|max:100000000',
            'max_uses' => 'nullable|integer|min:1|max:10000000',
            'max_uses_per_customer' => 'nullable|integer|min:1|max:10000',
            'expiry_date' => 'required|date|after:now',
        ], [
            'name.unique' => 'A coupon with this code already exists.',
        ]);

        Coupon::create($data + ['is_active' => true]);

        return redirect()->route('admin.coupons')->with('success', "Coupon {$data['name']} created.");
    }

    /** Pause or resume a coupon without deleting it. */
    public function toggle(Coupon $coupon)
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);

        return back()->with('success', "Coupon {$coupon->name} " . ($coupon->is_active ? 'activated.' : 'paused.'));
    }

    public function destroy(Coupon $coupon)
    {
        // Orders keep their totals; their coupon_id is set to null.
        $coupon->delete();

        return redirect()->route('admin.coupons')->with('success', "Coupon {$coupon->name} deleted.");
    }
}
