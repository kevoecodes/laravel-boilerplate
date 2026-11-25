<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * GET /api/v1/products?search=&sort=-price&filter[status]=active&filter[category]=Books&filter[min_price]=10&filter[max_price]=99
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = ListQuery::for($request)
            ->searchable(['name', 'sku', 'category'])
            ->sortable(['id', 'name', 'sku', 'category', 'price', 'stock', 'status', 'created_at'])
            ->filters([
                'status',
                'category',
                'min_price' => fn (Builder $q, $v) => $q->where('price', '>=', (float) $v),
                'max_price' => fn (Builder $q, $v) => $q->where('price', '<=', (float) $v),
                'in_stock' => fn (Builder $q, $v) => filter_var($v, FILTER_VALIDATE_BOOL)
                    ? $q->where('stock', '>', 0)
                    : $q->where('stock', 0),
            ])
            ->paginate(Product::query());

        return ApiResponse::paginated($paginator, ProductResource::class);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());

        return new ProductResource($product);
    }

    public function destroy(Product $product): JsonResponse
    {
        abort_if($product->orders()->exists(), 409, 'Product has orders and cannot be deleted. Set it inactive instead.');

        $product->delete();

        return ApiResponse::message('Product deleted.');
    }
}
