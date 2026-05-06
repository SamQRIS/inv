<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    // ============================================================
    // TAMBAH STOK (barang masuk)
    // ============================================================

    /**
     * @param  Product    $product
     * @param  int        $quantity
     * @param  Warehouse  $warehouse     Stok masuk ke gudang mana
     * @param  string     $referenceType 'purchase', 'transfer', 'manual', 'initial', dll
     * @param  int|null   $referenceId
     * @param  string|null $notes
     */
    public function addStock(
        Product   $product,
        int       $quantity,
        Warehouse $warehouse,
        string    $referenceType = 'manual',
        ?int      $referenceId   = null,
        ?string   $notes         = null
    ): StockMovement {
        return DB::transaction(function () use ($product, $quantity, $warehouse, $referenceType, $referenceId, $notes) {

            $ps          = $this->getOrCreateProductStock($product, $warehouse);
            $stockBefore = $ps->quantity;
            $stockAfter  = $stockBefore + $quantity;

            $ps->update(['quantity' => $stockAfter]);
            $product->syncTotalStock();

            return StockMovement::create([
                'product_id'     => $product->id,
                'warehouse_id'   => $warehouse->id,
                'type'           => 'in',
                'quantity'       => $quantity,
                'stock_before'   => $stockBefore,
                'stock_after'    => $stockAfter,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'notes'          => $notes,
                'user_id'        => Auth::id(),
                'moved_at'       => now(),
            ]);
        });
    }

    // ============================================================
    // KURANGI STOK (pengiriman / penjualan)
    // ============================================================

    /**
     * @param  Product    $product
     * @param  int        $quantity
     * @param  Warehouse  $warehouse     Stok diambil dari gudang mana
     * @param  string     $referenceType
     * @param  int        $referenceId
     * @param  string|null $notes
     */
    public function reduceStock(
        Product   $product,
        int       $quantity,
        Warehouse $warehouse,
        string    $referenceType,
        int       $referenceId,
        ?string   $notes = null
    ): StockMovement {
        return DB::transaction(function () use ($product, $quantity, $warehouse, $referenceType, $referenceId, $notes) {

            // Lock row untuk hindari race condition
            $ps = ProductStock::where('product_id', $product->id)
                ->where('warehouse_id', $warehouse->id)
                ->lockForUpdate()
                ->first();

            if (!$ps || $ps->quantity < $quantity) {
                $available = $ps?->quantity ?? 0;
                throw ValidationException::withMessages([
                    'stock' => "Stok {$product->name} di gudang [{$warehouse->name}] tidak mencukupi. "
                             . "Tersedia: {$available}, Dibutuhkan: {$quantity}.",
                ]);
            }

            $stockBefore = $ps->quantity;
            $stockAfter  = $stockBefore - $quantity;

            $ps->update(['quantity' => $stockAfter]);
            $product->syncTotalStock();

            return StockMovement::create([
                'product_id'     => $product->id,
                'warehouse_id'   => $warehouse->id,
                'type'           => 'out',
                'quantity'       => $quantity,
                'stock_before'   => $stockBefore,
                'stock_after'    => $stockAfter,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'notes'          => $notes,
                'user_id'        => Auth::id(),
                'moved_at'       => now(),
            ]);
        });
    }

    // ============================================================
    // TRANSFER ANTAR GUDANG
    // ============================================================

    /**
     * Pindahkan stok dari satu gudang ke gudang lain.
     * Menghasilkan 2 StockMovement: out dari asal, in ke tujuan.
     *
     * @return array{out: StockMovement, in: StockMovement}
     */
    public function transferStock(
        Product   $product,
        int       $quantity,
        Warehouse $fromWarehouse,
        Warehouse $toWarehouse,
        ?string   $notes = null
    ): array {
        if ($fromWarehouse->id === $toWarehouse->id) {
            throw ValidationException::withMessages([
                'warehouse' => 'Gudang asal dan tujuan tidak boleh sama.',
            ]);
        }

        return DB::transaction(function () use ($product, $quantity, $fromWarehouse, $toWarehouse, $notes) {

            // Kurangi dari asal
            $psFrom      = ProductStock::where('product_id', $product->id)
                ->where('warehouse_id', $fromWarehouse->id)
                ->lockForUpdate()
                ->first();

            if (!$psFrom || $psFrom->quantity < $quantity) {
                $available = $psFrom?->quantity ?? 0;
                throw ValidationException::withMessages([
                    'stock' => "Stok {$product->name} di [{$fromWarehouse->name}] tidak mencukupi. "
                             . "Tersedia: {$available}, Dibutuhkan: {$quantity}.",
                ]);
            }

            $fromBefore = $psFrom->quantity;
            $fromAfter  = $fromBefore - $quantity;
            $psFrom->update(['quantity' => $fromAfter]);

            // Tambah ke tujuan
            $psTo      = $this->getOrCreateProductStock($product, $toWarehouse);
            $toBefore  = $psTo->quantity;
            $toAfter   = $toBefore + $quantity;
            $psTo->update(['quantity' => $toAfter]);

            // Sync total
            $product->syncTotalStock();

            $referenceNotes = $notes ?? "Transfer dari [{$fromWarehouse->name}] ke [{$toWarehouse->name}]";

            // Movement: keluar dari asal
            $movOut = StockMovement::create([
                'product_id'      => $product->id,
                'warehouse_id'    => $fromWarehouse->id,
                'to_warehouse_id' => $toWarehouse->id,
                'type'            => 'out',
                'quantity'        => $quantity,
                'stock_before'    => $fromBefore,
                'stock_after'     => $fromAfter,
                'reference_type'  => 'transfer',
                'notes'           => $referenceNotes,
                'user_id'         => Auth::id(),
                'moved_at'        => now(),
            ]);

            // Movement: masuk ke tujuan
            $movIn = StockMovement::create([
                'product_id'      => $product->id,
                'warehouse_id'    => $toWarehouse->id,
                'to_warehouse_id' => null,
                'type'            => 'in',
                'quantity'        => $quantity,
                'stock_before'    => $toBefore,
                'stock_after'     => $toAfter,
                'reference_type'  => 'transfer',
                'reference_id'    => $movOut->id, // referensi ke movement asal
                'notes'           => $referenceNotes,
                'user_id'         => Auth::id(),
                'moved_at'        => now(),
            ]);

            return ['out' => $movOut, 'in' => $movIn];
        });
    }

    // ============================================================
    // VALIDASI STOK
    // ============================================================

    /**
     * Pastikan stok tersedia di gudang tertentu
     */
    public function ensureStockAvailable(Product $product, int $qty, Warehouse $warehouse): void
    {
        $available = $product->stockAt($warehouse);
        if ($available < $qty) {
            throw ValidationException::withMessages([
                'stock' => "Stok {$product->name} di gudang [{$warehouse->name}] tidak mencukupi. "
                         . "Tersedia: {$available}, Dibutuhkan: {$qty}.",
            ]);
        }
    }

    /**
     * Pastikan stok total (semua gudang) tersedia
     */
    public function ensureTotalStockAvailable(Product $product, int $qty): void
    {
        if ($product->stock_quantity < $qty) {
            throw ValidationException::withMessages([
                'stock' => "Total stok {$product->name} tidak mencukupi. "
                         . "Tersedia: {$product->stock_quantity}, Dibutuhkan: {$qty}.",
            ]);
        }
    }

    // ============================================================
    // PENYESUAIAN STOK (Opname)
    // ============================================================

    /**
     * Set stok produk di gudang tertentu ke nilai yang diinginkan (opname)
     */
    public function adjustStock(
        Product   $product,
        Warehouse $warehouse,
        int       $newQuantity,
        ?string   $notes = null
    ): StockMovement {
        return DB::transaction(function () use ($product, $warehouse, $newQuantity, $notes) {

            $ps          = $this->getOrCreateProductStock($product, $warehouse);
            $stockBefore = $ps->quantity;
            $diff        = $newQuantity - $stockBefore;

            $ps->update(['quantity' => $newQuantity]);
            $product->syncTotalStock();

            return StockMovement::create([
                'product_id'     => $product->id,
                'warehouse_id'   => $warehouse->id,
                'type'           => 'adjustment',
                'quantity'       => abs($diff),
                'stock_before'   => $stockBefore,
                'stock_after'    => $newQuantity,
                'reference_type' => 'opname',
                'notes'          => $notes ?? "Penyesuaian stok: {$stockBefore} → {$newQuantity}",
                'user_id'        => Auth::id(),
                'moved_at'       => now(),
            ]);
        });
    }

    // ============================================================
    // INTERNAL
    // ============================================================

    private function getOrCreateProductStock(Product $product, Warehouse $warehouse): ProductStock
    {
        return ProductStock::firstOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
            ['quantity' => 0, 'minimum_stock' => 0]
        );
    }
}