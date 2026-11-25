<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * GET /api/v1/users?search=&sort=-created_at&filter[role]=admin&filter[is_active]=true&page=1&per_page=15
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = ListQuery::for($request)
            ->searchable(['name', 'email'])
            ->sortable(['id', 'name', 'email', 'role', 'is_active', 'created_at'])
            ->filters(['role', 'is_active'])
            ->paginate(User::query()->withCount('orders'));

        return ApiResponse::paginated($paginator, UserResource::class);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->loadCount('orders'));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return new UserResource($user);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_if($request->user()->is($user), 422, 'You cannot delete your own account.');

        $user->delete();

        return ApiResponse::message('User deleted.');
    }
}
