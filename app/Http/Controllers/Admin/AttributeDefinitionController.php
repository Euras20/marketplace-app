<?php

namespace App\Http\Controllers\Admin;

use App\Models\ProductAttributeDefinition;
use App\Services\StoreContext;
use Illuminate\Http\Request;

class AttributeDefinitionController
{
    public function __construct(private StoreContext $storeContext) {}

    public function index()
    {
        return ProductAttributeDefinition::where('store_id', $this->storeContext->get()->id)
            ->orderBy('sort_order')
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:100',
            'label' => 'required|string|max:255',
            'data_type' => 'required|in:text,integer,decimal,boolean,date,select',
            'is_required' => 'boolean',
            'is_filterable' => 'boolean',
            'options' => 'nullable|array',
            'sort_order' => 'integer',
        ]);

        $definition = ProductAttributeDefinition::create([
            ...$validated,
            'store_id' => $this->storeContext->get()->id,
        ]);

        return response()->json($definition, 201);
    }

    public function update(Request $request, ProductAttributeDefinition $definition)
    {
        if ($definition->store_id !== $this->storeContext->get()->id) {
            abort(404);
        }

        $definition->update($request->validate([
            'label' => 'sometimes|string|max:255',
            'is_required' => 'boolean',
            'is_filterable' => 'boolean',
            'options' => 'nullable|array',
            'sort_order' => 'integer',
        ]));

        return response()->json($definition);
    }
}
