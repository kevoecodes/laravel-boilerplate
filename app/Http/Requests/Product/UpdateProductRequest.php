<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sku' => ['sometimes', 'required', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($this->route('product'))],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:100'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0'],
            'status' => ['sometimes', 'required', Rule::in(Product::STATUSES)],
        ];
    }
}
