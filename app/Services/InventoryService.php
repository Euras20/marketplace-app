<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(private StoreContext $storeContext) {}

    /**
     * Get or create the inventory record for a variant in the current store.
     */
    public function forVariant(ProductVariant $variant): Inventory
    {
        $this->assertSameStore($variant);

        return Inventory::firstOrCreate(
            [
                'store_id' => $variant->store_id,
                'product_variant_id' => $variant->id,
            ],
            [
                'on_hand' => 0,
                'reserved' => 0,
            ]
        );
    }

    /**
     * Reserve stock for an order (called at order placement).
     * Must be called inside a transaction with row lock.
     */
    public function reserve(ProductVariant $variant, int $quantity, ?int $orderId = null, string $actorType = 'system', ?int $actorId = null): Inventory
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be positive.');
        }

        $inventory = $this->lockAndLoad($variant);

        if ($inventory->available < $quantity) {
            throw new \RuntimeException('Insufficient available stock.');
        }

        $inventory->reserved += $quantity;
        $inventory->updated_at = now();
        $inventory->save();

        $this->recordMovement(
            $inventory,
            InventoryMovement::TYPE_RESERVATION,
            $quantity,
            $orderId,
            'Order reservation',
            $actorType,
            $actorId
        );

        return $inventory;
    }

    /**
     * Release a previous reservation (expiry, cancellation, rejection).
     */
    public function releaseReservation(ProductVariant $variant, int $quantity, ?int $orderId = null, string $reason = 'Reservation released', string $actorType = 'system', ?int $actorId = null): Inventory
    {
        $inventory = $this->lockAndLoad($variant);

        if ($inventory->reserved < $quantity) {
            throw new \RuntimeException('Cannot release more than currently reserved.');
        }

        $inventory->reserved -= $quantity;
        $inventory->updated_at = now();
        $inventory->save();

        $this->recordMovement(
            $inventory,
            InventoryMovement::TYPE_RESERVATION_RELEASE,
            $quantity,
            $orderId,
            $reason,
            $actorType,
            $actorId
        );

        return $inventory;
    }

    /**
     * Deduct physical stock on order approval (and release the matching reservation).
     */
    public function deductOnApproval(ProductVariant $variant, int $quantity, ?int $orderId = null, string $actorType = 'admin', ?int $actorId = null): Inventory
    {
        $inventory = $this->lockAndLoad($variant);

        $untracked = ((int) $inventory->on_hand === 0 && (int) $inventory->reserved === 0);
        if (!$untracked && ($inventory->reserved < $quantity || $inventory->on_hand < $quantity)) {
            throw new \RuntimeException('Stock integrity failure on approval.');
        }
        if ($untracked) {
            return $inventory;
        }

        $inventory->on_hand -= $quantity;
        $inventory->reserved -= $quantity;
        $inventory->updated_at = now();
        $inventory->save();

        $this->recordMovement(
            $inventory,
            InventoryMovement::TYPE_SALE_DEDUCTION,
            $quantity,
            $orderId,
            'Order approved – stock deducted',
            $actorType,
            $actorId
        );

        return $inventory;
    }

    /**
     * Manual stock adjustment (admin).
     */
    public function adjust(ProductVariant $variant, int $delta, string $reason, string $actorType = 'admin', ?int $actorId = null): Inventory
    {
        $inventory = $this->lockAndLoad($variant);

        $newOnHand = $inventory->on_hand + $delta;

        if ($newOnHand < $inventory->reserved) {
            throw new \RuntimeException('Adjustment would make on_hand lower than reserved.');
        }

        $inventory->on_hand = $newOnHand;
        $inventory->updated_at = now();
        $inventory->save();

        $this->recordMovement(
            $inventory,
            InventoryMovement::TYPE_MANUAL_ADJUSTMENT,
            $delta,
            null,
            $reason,
            $actorType,
            $actorId
        );

        return $inventory;
    }

    private function lockAndLoad(ProductVariant $variant): Inventory
    {
        $this->assertSameStore($variant);

        $inventory = Inventory::where('store_id', $variant->store_id)
            ->where('product_variant_id', $variant->id)
            ->lockForUpdate()
            ->first();

        if (!$inventory) {
            $inventory = $this->forVariant($variant);
            $inventory = Inventory::where('id', $inventory->id)->lockForUpdate()->first();
        }

        return $inventory;
    }

    private function recordMovement(Inventory $inventory, string $type, int $quantity, ?int $orderId, string $reason, string $actorType, ?int $actorId): void
    {
        InventoryMovement::create([
            'store_id' => $inventory->store_id,
            'product_variant_id' => $inventory->product_variant_id,
            'order_id' => $orderId,
            'movement_type' => $type,
            'quantity' => $quantity,
            'on_hand_after' => $inventory->on_hand,
            'reserved_after' => $inventory->reserved,
            'reason' => $reason,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
        ]);
    }

    private function assertSameStore(ProductVariant $variant): void
    {
        if ($variant->store_id !== $this->storeContext->get()->id) {
            abort(404);
        }
    }
}
