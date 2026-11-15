<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

class CatalogueService
{
    public function __construct(private StoreContext $storeContext) {}

    /**
     * Publish a product only if it has at least one active variant
     * and required store-specific attributes are present.
     */
    public function publish(Product $product): Product
    {
        $this->assertSameStore($product);

        if (!$product->canBePublished()) {
            throw new \RuntimeException('Product must have at least one active variant before publishing.');
        }

        // Attribute validation can be extended here using the store's definitions
        $product->update([
            'status' => Product::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        return $product->fresh();
    }

    public function archive(Product $product): Product
    {
        $this->assertSameStore($product);

        $product->update([
            'status' => Product::STATUS_ARCHIVED,
        ]);

        return $product->fresh();
    }

    public function assertSameStore(Product $product): void
    {
        if ($product->store_id !== $this->storeContext->get()->id) {
            abort(404);
        }
    }

    /**
     * Scope a query to the current store.
     */
    public function scopeToCurrentStore($query)
    {
        return $query->where('store_id', $this->storeContext->get()->id);
    }
}
