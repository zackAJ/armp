<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class OrderCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {

        //FIX:  use enums for status
        $completedOrderExists = $this->collection->where('status', 'completed')->count() > 0;

        return $this->collection->map(fn ($resource) => [

            'order_id' => $resource->id,

            'customer_name' => $resource->whenLoaded('customer', fn () => $resource->customer->name),

            'total_amount' => $resource->whenLoaded('items', fn () => $this->calculateTotal($resource->items)),

            'items_count' => $resource->whenCounted(
                'items',
                fn () => $resource->items_count,
                $resource->whenLoaded('items', fn () => $resource->items->count())
            ),

            'last_added_to_cart' => $resource->whenLoaded('items', fn () => $resource->items->last()),

            'completed_order_exists' => $completedOrderExists,

            'created_at' => $resource->created_at,
        ])
            ->toArray();
    }

    //TODO: when I have the time, calculate SQL level with DB::raw() and
    //use eloquent scopes to add the totol_amount to the order
    public function calculateTotal(Collection $items): int
    {
        return $items->reduce(function (int $accumulator, CartItem $item) {
            return $accumulator + ($item->quantity * $item->price);
        }, 0);
    }
}
