<?php

namespace App\Http\Controllers\Admin;

use App\Models\Brand;
use App\Services\StoreContext;
use Illuminate\Http\Request;

class BrandController
{
    public function __construct(private StoreContext $storeContext) {}

    public function index()
    {
        return Brand::where('store_id', $this->storeContext->get()->id)->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $brand = Brand::create([
            ...$validated,
            'store_id' => $this->storeContext->get()->id,
        ]);

        return response()->json($brand, 201);
    }

    public function update(Request $request, Brand $brand)
    {
        if ($brand->store_id !== $this->storeContext->get()->id) {
            abort(404);
        }

        $brand->update($request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255',
            'is_active' => 'boolean',
        ]));

        return response()->json($brand);
    }

    public function destroy(Brand $brand)
    {
        if ($brand->store_id !== $this->storeContext->get()->id) {
            abort(404);
        }

        $brand->delete();

        return response()->noContent();
    }
}
