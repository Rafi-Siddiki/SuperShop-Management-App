<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    /**
     * Download an order invoice as PDF. Previously this route was public, so
     * anyone could download any customer's invoice by guessing the ID.
     */
    public function show(Order $order)
    {
        $user = Auth::user();
        abort_unless($user->isStaff() || $order->user_id === $user->id, 404);

        $order->load('items.product', 'coupon', 'user');

        $pdf = Pdf::loadView('invoice.show', compact('order'));

        return $pdf->download('invoice_' . $order->number . '.pdf');
    }
}
