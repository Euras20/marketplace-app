<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Services\TrackingService;
use App\Services\StoreContext;
use Illuminate\Http\Request;

class DeliveryController
{
    public function __construct(
        private StoreContext $storeContext,
        private TrackingService $tracking
    ) {}

    public function preparing(Order $order)
    {
        $order = $this->tracking->updateStatus($order, Order::STATUS_IN_PREPARATION, auth()->id());

        return response()->json($order);
    }

    public function shipped(Order $order)
    {
        $order = $this->tracking->updateStatus($order, Order::STATUS_SHIPPED, auth()->id());

        return response()->json($order);
    }

    public function delivered(Order $order)
    {
        $order = $this->tracking->updateStatus($order, Order::STATUS_DELIVERED, auth()->id());

        return response()->json($order);
    }

    public function showTracking(Order $order)
    {
        if ($order->store_id !== $this->storeContext->get()->id) {
            abort(404);
        }

        return response()->json($order->tracking);
    }
}
