<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // real auth via admin middleware
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'category_id' => 'required|integer|exists:categories,id',
            'brand_id' => 'nullable|integer|exists:brands,id',
            'description' => 'nullable|string',
            'is_promo' => 'boolean',
            'external_url' => 'nullable|url|max:2048',
            'image_urls' => 'nullable|array',
            'image_urls.*' => 'url|max:2048',
            'images' => 'nullable|array',
            'images.*' => 'file|image|max:5120',
            'attributes' => 'nullable|array',
            'attributes.*' => 'nullable',
            'variants' => 'required|array|min:1',
            'variants.*.sku' => 'required|string|max:100',
            'variants.*.variant_name' => 'nullable|string|max:255',
            'variants.*.price' => 'required|numeric|min:0',
            'variants.*.compare_at_price' => 'nullable|numeric|min:0',
            'variants.*.is_active' => 'boolean',
        ];
    }
}
