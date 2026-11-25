<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'users' => User::count(),
                'active_users' => User::where('is_active', true)->count(),
                'products' => Product::count(),
                'low_stock_products' => Product::where('stock', '<', 10)->count(),
                'orders' => Order::count(),
                'pending_orders' => Order::where('status', 'pending')->count(),
                'revenue' => (float) Order::where('status', 'completed')->sum('total'),
                'orders_by_status' => Order::query()
                    ->selectRaw('status, count(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status'),
                'recent_orders' => OrderResource::collection(
                    Order::with(['user', 'product'])->latest()->limit(5)->get()
                )->resolve(),
            ],
        ]);
    }
}
