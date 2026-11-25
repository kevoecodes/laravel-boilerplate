<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiResponse
{
    /**
     * Standard list envelope: { data: [...], meta: { current_page, per_page, total, last_page } }.
     *
     * @param  class-string<JsonResource>  $resource
     */
    public static function paginated(LengthAwarePaginator $paginator, string $resource): JsonResponse
    {
        return response()->json([
            'data' => $resource::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public static function message(string $message, int $status = 200): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
