<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * GET /api/v1/orders?search=&sort=-total&filter[status]=pending,processing&filter[user_id]=1&filter[from]=2025-01-01&filter[to]=2025-12-31
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = ListQuery::for($request)
            ->searchable(['order_number', 'user.name', 'user.email', 'product.name'])
            ->sortable(['id', 'order_number', 'quantity', 'total', 'status', 'created_at'])
            ->filters([
                'status',
                'user_id',
                'product_id',
                'from' => fn (Builder $q, $v) => $q->whereDate('created_at', '>=', $v),
                'to' => fn (Builder $q, $v) => $q->whereDate('created_at', '<=', $v),
            ])
            ->paginate(Order::query()->with(['user', 'product']));

        return ApiResponse::paginated($paginator, OrderResource::class);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['unit_price'] ??= Product::findOrFail($data['product_id'])->price;

        $order = Order::create($data)->load(['user', 'product']);

        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load(['user', 'product']));
    }

    public function update(UpdateOrderRequest $request, Order $order): OrderResource
    {
        $data = $request->validated();

        if (! isset($data['unit_price']) && isset($data['product_id']) && (int) $data['product_id'] !== $order->product_id) {
            $data['unit_price'] = Product::findOrFail($data['product_id'])->price;
        }

        if (array_key_exists('unit_price', $data) && $data['unit_price'] === null) {
            unset($data['unit_price']);
        }

        $order->update($data);

        return new OrderResource($order->load(['user', 'product']));
    }

    public function destroy(Order $order): JsonResponse
    {
        $order->delete();

        return ApiResponse::message('Order deleted.');
    }
}
