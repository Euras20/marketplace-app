<?php

namespace App\Http\Controllers\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Services\StoreContext;
use Illuminate\Http\Request;

class CategoryController
{
    public function __construct(private StoreContext $storeContext) {}

    /**
     * GET /api/storefront/categories
     * Active categories with name, slug, and the image of the first published product.
     */
    public function index()
    {
        $store = $this->storeContext->get();

        $categories = Category::where('store_id', $store->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function (Category $category) use ($store) {
                $firstProduct = Product::where('store_id', $store->id)
                    ->where('category_id', $category->id)
                    ->where('status', Product::STATUS_PUBLISHED)
                    ->orderBy('published_at')
                    ->first();

                $image = null;
                if ($firstProduct) {
                    $media = ProductMedia::where('product_id', $firstProduct->id)
                        ->orderByDesc('is_primary')
                        ->orderBy('sort_order')
                        ->first();

                    if ($media) {
                        $image = [
                            'storage_disk' => $media->storage_disk,
                            'storage_path' => $media->storage_path,
                            'alt_text' => $media->alt_text,
                        ];
                    }
                }

                return [
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'image' => $image,
                ];
            });

        return response()->json(['data' => $categories]);
    }
}
