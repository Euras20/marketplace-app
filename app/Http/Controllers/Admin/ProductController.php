<?php

namespace App\Http\Controllers\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CatalogueService;
use App\Services\StoreContext;
use App\Http\Requests\Admin\StoreProductRequest;
use Illuminate\Http\Request;

class ProductController
{
    public function __construct(
        private StoreContext $storeContext,
        private CatalogueService $catalogue
    ) {}

    public function index(Request $request)
    {
        $store = $this->storeContext->get();

        $products = Product::where('store_id', $store->id)
            ->with(['category', 'brand', 'variants.inventory'])
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->boolean('is_promo'), fn ($q) => $q->where('is_promo', true))
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return response()->json($products);
    }

    public function store(StoreProductRequest $request)
    {
        $store = $this->storeContext->get();
        $validated = $request->validated();

        // Ensure category/brand belong to this store
        $category = \App\Models\Category::where('id', $validated['category_id'])
            ->where('store_id', $store->id)
            ->firstOrFail();

        if (!empty($validated['brand_id'])) {
            \App\Models\Brand::where('id', $validated['brand_id'])
                ->where('store_id', $store->id)
                ->firstOrFail();
        }

        $product = Product::create([
            'store_id' => $store->id,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'category_id' => $validated['category_id'],
            'brand_id' => $validated['brand_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_promo' => (bool) ($validated['is_promo'] ?? false),
            'status' => Product::STATUS_DRAFT,
        ]);

        // Media from uploaded files
        $uploaded = $request->file('images', []);
        $sort = 0;
        foreach ($uploaded as $file) {
            $path = $file->store('products/' . $product->id, 'public');
            \App\Models\ProductMedia::create([
                'store_id' => $store->id,
                'product_id' => $product->id,
                'storage_disk' => 'public',
                'storage_path' => $path,
                'alt_text' => $product->name,
                'sort_order' => $sort,
                'is_primary' => $sort === 0,
            ]);
            $sort++;
        }

        // Media from URLs (additional or instead of uploads)
        $imageUrls = $validated['image_urls'] ?? [];
        foreach ($imageUrls as $url) {
            \App\Models\ProductMedia::create([
                'store_id' => $store->id,
                'product_id' => $product->id,
                'storage_disk' => 'url',
                'storage_path' => $url,
                'alt_text' => $product->name,
                'sort_order' => $sort,
                'is_primary' => $sort === 0,
            ]);
            $sort++;
        }

        foreach ($validated['variants'] as $v) {
            $variant = ProductVariant::create([
                'store_id' => $store->id,
                'product_id' => $product->id,
                'sku' => $v['sku'],
                'variant_name' => $v['variant_name'] ?? null,
                'price' => $v['price'],
                'compare_at_price' => $v['compare_at_price'] ?? null,
                'is_active' => $v['is_active'] ?? true,
            ]);

            // Optional initial stock
            if (isset($v['quantity'])) {
                \App\Models\Inventory::create([
                    'store_id' => $store->id,
                    'product_variant_id' => $variant->id,
                    'on_hand' => (int) $v['quantity'],
                    'reserved' => 0,
                ]);
            }
        }

        $this->saveAttributes($product, $request->input('attributes'));

        return response()->json($product->load(['category', 'brand', 'variants.inventory', 'media', 'attributeValues']), 201);
    }

    public function show(Product $product)
    {
        if ($product->store_id !== $this->storeContext->get()->id) {
            abort(404);
        }
        return response()->json($product->load(['category', 'brand', 'variants.inventory', 'media', 'attributeValues.definition']));
    }

    public function update(Request $request, Product $product)
    {
        if ($product->store_id !== $this->storeContext->get()->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'description' => 'nullable|string',
            'is_promo' => 'boolean',
        ]);

        $product->update($validated);

        $this->saveAttributes($product, $request->input('attributes'));

        return response()->json($product->fresh(['category', 'brand', 'variants', 'attributeValues.definition']));
    }

    public function publish(Product $product, CatalogueService $catalogue)
    {
        $product = $catalogue->publish($product);
        return response()->json($product);
    }

    public function archive(Product $product, CatalogueService $catalogue)
    {
        $product = $catalogue->archive($product);
        return response()->json($product);
    }

    private function saveAttributes(\App\Models\Product $product, $attributes): void
    {
        if (is_string($attributes)) {
            $decoded = json_decode($attributes, true);
            $attributes = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($attributes) || $attributes === []) {
            return;
        }
        foreach ($attributes as $defId => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            \App\Models\ProductAttributeValue::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'attribute_definition_id' => (int) $defId,
                ],
                [
                    'store_id' => $product->store_id,
                    'value_text' => is_scalar($value) ? (string) $value : json_encode($value),
                ]
            );
        }
    }
}
