<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

class OrderController extends Controller
{
    // a controller method should not format
    // I'll use resource collection for that
    public function index()
    {
        $orders = Order::query()
            ->latest('completed_at')
            ->with([
                'customer' => fn (Builder $q) => $q->select(['id', 'name']),
                'items' => fn (Builder $q) => $q->select(['id', 'quantity', 'price', 'created_at']),
            ])
            ->withCount('items')
            ->get();

        return view('orders.index', OrderResource::collection($orders)->toArray(request()));
    }
}
