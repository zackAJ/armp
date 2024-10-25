# Task 1

[link](https://docs.google.com/spreadsheets/d/1nCGT8O52qvnom6S8_1ZJ1QsaJx_3YRE3yISDwtPw2PM/edit?usp=sharing)

# Task 2

## OrderController

```php

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
```

## OrderCollection 

```php

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
```

## Task 3

```php

        $employees = [
            ['name' => 'John', 'city' => 'Dallas'],
            ['name' => 'Jane', 'city' => 'Austin'],
            ['name' => 'Jake', 'city' => 'Dallas'],
            ['name' => 'Jill', 'city' => 'Dallas'],
        ];

        $offices = [
            ['office' => 'Dallas HQ', 'city' => 'Dallas'],
            ['office' => 'Dallas South', 'city' => 'Dallas'],
            ['office' => 'Austin Branch', 'city' => 'Austin'],
        ];

        $employeesByCity = collect($employees)->groupBy('city');

        $officesByCity = collect($offices)->groupBy('city');

        $output = $officesByCity

            ->map(
                function ($offices, $city) use ($employeesByCity) {
                    return $offices
                        ->map(
                            fn ($office) => [
                                $office['office'] => $employeesByCity[$city]->pluck('name'),
                            ]);
                }
            );
```

## Task 5

Task 5: Q&A
Answer the following questions

A) Explain this code:


```php

        Schedule::command('app:run')
            ->withoutOverlapping()
            ->hourly()
            ->onOneServer()
            ->runInBackground();

```

we're Scheduling a command to run every hour, `withoutOverlapping` allow us to use atomic locks to insure that only one instance of the command is running in our server, `onOneServer` will lock this command for all the servers connected to our cache server, this means that only one server can acquire the lock at a time therefore only one instance of  the command is running across all of our servers.

B) What is the difference between the Context and Cache Facades? Provide examples to illustrate your explanation.

Context is a new feature that was added in Laravel 11, it allows you to share data across all process from middlewares to controllers to queues, it's very helpful when logging and to communicate between multiple components, as far as I know it doesn't use cache.

Cache Facade allows you to store key values via multiple drivers and implementations and gives you atomic locks as well, Cache is shared across multiple requests unlike Context which dies after a process is terminated.

I'm  making a laravel package laravel-debounce and I'm using two implementations with caching and context.

Example:

1- We can cache data that is relatively static, like a list of countries.
every request will get access to the same cached countries if it's still withing the `ttl`
```php
    $countries = Cache::get('countries') ?? Cache::remember('countries',3600,fn()=>country::all());
```
2- using Context we can achieve the same thing but for the current process only, meaning the countries will be queried at leas once. also for now context doesn't support ttl.

```php
    $countries = Context::get('countries') ?? Cache::add('countries',country::all());
```

C) What's the difference between $query->update(), $model->update(), and $model->updateQuietly() in Laravel, and when would you use each?

$query->update() uses database query builder without interacting with eloquent level, usually needed in migrations for example.
$model->update() uses eloquent ORM therefore events are dispatched and observer methods are called as well.
$model->updateQuietly() allows you to update without event from eloquent level without reaching form query builder or SQL.
