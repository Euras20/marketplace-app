<?php

namespace App\Http\Controllers\Storefront;

use App\Models\Product;
use App\Services\StoreContext;
use Illuminate\Http\Request;

class ProductController
{
    public function __construct(private StoreContext $storeContext) {}

    public function index(Request $request)
    {
        $store = $this->storeContext->get();

        $query = Product::where('store_id', $store->id)
            ->where('status', Product::STATUS_PUBLISHED)
            ->with(['category', 'brand', 'variants' => fn ($q) => $q->where('is_active', true), 'media', 'attributeValues.definition']);

        // Category filter
        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request, $store) {
                $q->where('store_id', $store->id)
                  ->where('slug', $request->category);
            });
        }

        // Text search (name, slug, description, SKU, brand)
        if ($request->filled('q')) {
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $request->q) . '%';
            $query->where(function ($q) use ($term, $store) {
                $q->where('name', 'ilike', $term)
                  ->orWhere('slug', 'ilike', $term)
                  ->orWhere('description', 'ilike', $term)
                  ->orWhereHas('variants', fn ($v) => $v->where('store_id', $store->id)->where('sku', 'ilike', $term))
                  ->orWhereHas('brand', fn ($b) => $b->where('store_id', $store->id)->where('name', 'ilike', $term))
                  ->orWhereHas('category', fn ($c) => $c->where('store_id', $store->id)->where('name', 'ilike', $term))
                  ->orWhereHas('attributeValues', fn ($a) => $a->where('value_text', 'ilike', $term));
            });
        }

        // Brand filter (slug or name)
        if ($request->filled('brand')) {
            $brand = $request->brand;
            $query->whereHas('brand', function ($q) use ($brand, $store) {
                $q->where('store_id', $store->id)
                  ->where(function ($b) use ($brand) {
                      $b->where('slug', $brand)->orWhere('name', 'ilike', $brand);
                  });
            });
        }

        // Price range (active variants)
        $minPrice = $request->input('min_price') ?? $request->input('price_gte');
        $maxPrice = $request->input('max_price') ?? $request->input('price_lte');
        if ($minPrice !== null && is_numeric($minPrice)) {
            $query->whereHas('variants', function ($q) use ($minPrice) {
                $q->where('is_active', true)->where('price', '>=', $minPrice);
            });
        }
        if ($maxPrice !== null && is_numeric($maxPrice)) {
            $query->whereHas('variants', function ($q) use ($maxPrice) {
                $q->where('is_active', true)->where('price', '<=', $maxPrice);
            });
        }

        // In stock (available = on_hand - reserved > 0)
        if ($request->boolean('in_stock')) {
            $query->whereHas('variants', function ($q) use ($store) {
                $q->where('is_active', true)
                  ->whereHas('inventory', function ($inv) use ($store) {
                      $inv->where('store_id', $store->id)
                          ->whereRaw('on_hand - reserved > 0');
                  });
            });
        }

        // Attribute filters: ?attr[material]=silicone&attr[size]=148
        // or ?attr_material=silicone
        $attrs = $request->input('attr', []);
        if (!is_array($attrs)) {
            $attrs = [];
        }
        foreach ($request->all() as $key => $value) {
            if (str_starts_with($key, 'attr_') && $value !== null && $value !== '') {
                $attrs[substr($key, 5)] = $value;
            }
        }
        foreach ($attrs as $code => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $query->whereHas('attributeValues', function ($q) use ($code, $value, $store) {
                $q->where('store_id', $store->id)
                  ->whereHas('definition', fn ($d) => $d->where('store_id', $store->id)->where('code', $code))
                  ->where(function ($v) use ($value) {
                      $v->where('value_text', $value)
                        ->orWhere('value_text', 'ilike', $value)
                        ->orWhere('value_integer', is_numeric($value) ? (int) $value : null)
                        ->orWhere('value_decimal', is_numeric($value) ? $value : null)
                        ->orWhere('value_boolean', filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE));
                  });
            });
        }

        // Promo / on-sale filter
        if ($request->boolean('promo') || $request->boolean('is_promo')) {
            $query->where('is_promo', true);
        }

        // Sorting
        $sort = $request->input('sort', 'newest');
        match ($sort) {
            'newest' => $query->orderByDesc('published_at')->orderByDesc('created_at'),
            'price_desc' => $query->orderByDesc(
                \App\Models\ProductVariant::select('price')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->where('is_active', true)
                    ->orderByDesc('price')
                    ->limit(1)
            ),
            'price_asc' => $query->orderBy(
                \App\Models\ProductVariant::select('price')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->where('is_active', true)
                    ->orderBy('price')
                    ->limit(1)
            ),
            default => $query->orderByDesc('published_at'),
        };

        // Limit (homepage sections) or paginate
        $limit = $request->integer('limit');
        if ($limit > 0 && $limit <= 100) {
            $products = $query->limit($limit)->get();
            return response()->json(['data' => $products]);
        }

        $products = $query->paginate(20);

        return response()->json($products);
    }

    public function show(string $slug)
    {
        $store = $this->storeContext->get();

        $product = Product::where('store_id', $store->id)
            ->where('slug', $slug)
            ->where('status', Product::STATUS_PUBLISHED)
            ->with(['category', 'brand', 'variants', 'media'])
            ->firstOrFail();

        // Load attributes only if the tables exist
        try {
            $product->load('attributeValues.definition');
        } catch (\Throwable $e) {
            // ignore missing attribute tables
        }

        return response()->json($product);
    }
}
