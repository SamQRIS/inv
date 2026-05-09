<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Transaction;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    public function __construct(
        protected StockService $stockService,
    ) {}

    // =========================================================
    // CREATE DELIVERY ORDER
    // =========================================================

    public function createDelivery(Transaction $transaction): Delivery
    {
        return DB::transaction(function () use ($transaction) {
            $delivery = Delivery::create([
                'do_number'      => Delivery::generateDoNumber(),
                'transaction_id' => $transaction->id,
                'user_id'        => Auth::id(),
                'do_date'        => today()->toDateString(),
                'status'         => 'pending',
            ]);

            foreach ($transaction->items as $item) {
                DeliveryItem::create([
                    'delivery_id'         => $delivery->id,
                    'transaction_item_id' => $item->id,
                    'product_id'          => $item->product_id,
                    'qty_ordered'         => $item->quantity,
                    'qty_delivered'       => 0,
                ]);
            }

            return $delivery->fresh('items');
        });
    }

    // =========================================================
    // PROCESS SHIPMENT (parsial)
    // =========================================================

    /**
     * @param  Delivery  $delivery
     * @param  array     $shipmentData {
     *   shipment_date: string,
     *   driver_name:   ?string,
     *   vehicle_number:?string,
     *   warehouse_id:  ?int   ← opsional, fallback ke default jika tidak ada
     *   notes:         ?string,
     *   items: [
     *     { delivery_item_id: int, qty_shipped: int, warehouse_id?: int }
     *   ]
     * }
     */
    public function processShipment(Delivery $delivery, array $shipmentData): Shipment
    {
        return DB::transaction(function () use ($delivery, $shipmentData) {

            // ── Resolve warehouse ────────────────────────────────────
            // Prioritas: warehouse per item → warehouse di header → default
            $defaultWarehouse = $this->resolveWarehouse(
                $shipmentData['warehouse_id'] ?? null
            );

            // ── Validasi qty & stok — semua item dicek dulu sebelum ada yang diproses
            $errors = [];

            foreach ($shipmentData['items'] as $itemData) {
                $deliveryItem = DeliveryItem::with('product')->findOrFail($itemData['delivery_item_id']);
                $product      = $deliveryItem->product;
                $qtyShipped   = (int) ($itemData['qty_shipped'] ?? 0);
                $remaining    = $deliveryItem->qtyRemaining();

                // Resolve warehouse untuk item ini
                $itemWarehouse = isset($itemData['warehouse_id']) && $itemData['warehouse_id']
                    ? Warehouse::findOrFail($itemData['warehouse_id'])
                    : $defaultWarehouse;

                // Cek 1: qty kirim tidak melebihi sisa DO
                if ($qtyShipped > $remaining) {
                    $errors[] = "• {$product->name}: qty kirim ({$qtyShipped}) " .
                                "melebihi sisa DO ({$remaining}).";
                    continue;
                }

                // Cek 2: stok di gudang mencukupi
                $stockAtWarehouse = $product->stockAt($itemWarehouse);
                if ($stockAtWarehouse < $qtyShipped) {
                    $errors[] = "• {$product->name}: stok di gudang [{$itemWarehouse->name}] " .
                                "tidak mencukupi. Tersedia: {$stockAtWarehouse}, " .
                                "Dibutuhkan: {$qtyShipped}.";
                }
            }

            // Jika ada error, batalkan semua — jangan proses sebagian
            if (!empty($errors)) {
                throw ValidationException::withMessages([
                    'items' => implode("\n", $errors),
                ]);
            }

            // ── Buat Shipment ────────────────────────────────────────
            $shipment = Shipment::create([
                'shipment_number' => $this->generateShipmentNumber(),
                'delivery_id'     => $delivery->id,
                'shipment_date'   => $shipmentData['shipment_date'],
                'driver_name'     => $shipmentData['driver_name'] ?? null,
                'vehicle_number'  => $shipmentData['vehicle_number'] ?? null,
                'notes'           => $shipmentData['notes'] ?? null,
            ]);

            // ── Proses tiap item ─────────────────────────────────────
            foreach ($shipmentData['items'] as $itemData) {
                $deliveryItem = DeliveryItem::findOrFail($itemData['delivery_item_id']);
                $product      = $deliveryItem->product;
                $qtyShipped   = (int) $itemData['qty_shipped'];

                // Warehouse per item (jika ada) override warehouse header
                $warehouse = isset($itemData['warehouse_id']) && $itemData['warehouse_id']
                    ? Warehouse::findOrFail($itemData['warehouse_id'])
                    : $defaultWarehouse;

                ShipmentItem::create([
                    'shipment_id'      => $shipment->id,
                    'delivery_item_id' => $deliveryItem->id,
                    'product_id'       => $product->id,
                    'qty_shipped'      => $qtyShipped,
                    'warehouse_id'     => $warehouse->id,
                ]);

                // Update qty_delivered
                $deliveryItem->increment('qty_delivered', $qtyShipped);

                // Kurangi stok
                $this->stockService->reduceStock(
                    $product,
                    $qtyShipped,
                    $warehouse,
                    'shipment',
                    $shipment->id,
                    "Pengiriman {$shipment->shipment_number}"
                );
            }

            // ── Update status ────────────────────────────────────────
            $this->updateDeliveryStatus($delivery);

            return $shipment->fresh('items');
        });
    }

    // =========================================================
    // HELPERS
    // =========================================================

    /**
     * Resolve warehouse — tidak lempar exception jika null,
     * fallback ke warehouse default.
     */
    private function resolveWarehouse(?int $warehouseId): Warehouse
    {
        if ($warehouseId) {
            return Warehouse::findOrFail($warehouseId);
        }

        // Fallback ke warehouse default
        $default = Warehouse::where('is_default', true)
            ->where('is_active', true)
            ->first();

        if (!$default) {
            // Fallback ke warehouse aktif pertama
            $default = Warehouse::where('is_active', true)
                ->orderBy('sort_order')
                ->first();
        }

        if (!$default) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Tidak ada gudang aktif. Silakan buat gudang terlebih dahulu.',
            ]);
        }

        return $default;
    }

    private function updateDeliveryStatus(Delivery $delivery): void
    {
        $delivery->refresh();
        $allDelivered = $delivery->items->every(fn($item) => $item->isFullyDelivered());
        $anyDelivered = $delivery->items->some(fn($item) => $item->qty_delivered > 0);

        $status = match(true) {
            $allDelivered => 'completed',
            $anyDelivered => 'partial',
            default       => 'pending',
        };

        $delivery->update(['status' => $status]);

        // Update delivery_status di transaksi
        $transaction  = $delivery->transaction;
        $allCompleted = $transaction->deliveries->every(fn($d) => $d->status === 'completed');
        $anyShipped   = $transaction->deliveries->some(fn($d) => $d->status !== 'pending');

        $txStatus = match(true) {
            $allCompleted => 'delivered',
            $anyShipped   => 'partial',
            default       => 'pending',
        };

        $transaction->update(['delivery_status' => $txStatus]);
    }

    private function generateShipmentNumber(): string
    {
        $prefix = 'SHP';
        $date   = now()->format('Ymd');
        $last   = Shipment::whereDate('created_at', today())->orderByDesc('id')->first();
        $seq    = $last ? ((int) substr($last->shipment_number, -4)) + 1 : 1;
        return sprintf('%s-%s-%04d', $prefix, $date, $seq);
    }
}