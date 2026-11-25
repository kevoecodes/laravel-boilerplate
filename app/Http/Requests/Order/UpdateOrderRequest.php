<?php

namespace App\Http\Requests\Order;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'product_id' => ['sometimes', 'required', 'integer', 'exists:products,id'],
            'quantity' => ['sometimes', 'required', 'integer', 'min:1'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'required', Rule::in(Order::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
