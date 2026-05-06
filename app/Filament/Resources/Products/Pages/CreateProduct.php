<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;
    protected static ?string $title   = 'Produk Baru';
 
    /**
     * Setelah produk berhasil dibuat:
     * 1. Inisialisasi product_stocks per gudang dari form
     * 2. Catat stock movements awal
     * 3. Sync total stock ke products.stock_quantity
     */
    protected function afterCreate(): void
    {
        $product            = $this->record;
        $stockPerWarehouse  = $this->data['stock_per_warehouse'] ?? [];
 
        foreach ($stockPerWarehouse as $entry) {
            if (empty($entry['warehouse_id'])) continue;
 
            $warehouse = Warehouse::find($entry['warehouse_id']);
            if (!$warehouse) continue;
 
            $quantity     = (int) ($entry['quantity'] ?? 0);
            $minimumStock = (int) ($entry['minimum_stock'] ?? 0);
 
            // Buat / update product_stocks
            ProductStock::updateOrCreate(
                ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
                ['quantity' => $quantity, 'minimum_stock' => $minimumStock]
            );
 
            // Catat movement awal jika ada stok
            if ($quantity > 0) {
                StockMovement::create([
                    'product_id'     => $product->id,
                    'warehouse_id'   => $warehouse->id,
                    'type'           => 'in',
                    'quantity'       => $quantity,
                    'stock_before'   => 0,
                    'stock_after'    => $quantity,
                    'reference_type' => 'initial',
                    'notes'          => "Stok awal di {$warehouse->name}",
                    'user_id'        => Auth::id(),
                    'moved_at'       => now(),
                ]);
            }
        }
 
        // Sync total ke kolom stock_quantity di products
        $product->syncTotalStock();
    }
}
