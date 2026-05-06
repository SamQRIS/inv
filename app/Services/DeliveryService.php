<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Transaction;
use App\Services\StockService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    public function __construct(
        protected StockService $stockService,
    ) {}
 
    /**
     * Generate Delivery Order dari transaksi
     */
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
 
            // Buat delivery items dari semua transaction items
            foreach ($transaction->items as $item) {
                DeliveryItem::create([
                    'delivery_id'        => $delivery->id,
                    'transaction_item_id'=> $item->id,
                    'product_id'         => $item->product_id,
                    'qty_ordered'        => $item->quantity,
                    'qty_delivered'      => 0,
                ]);
            }
 
            return $delivery->fresh('items');
        });
    }
 
    /**
     * Proses pengiriman parsial
     *
     * @param  Delivery  $delivery
     * @param  array     $shipmentData  {
     *   shipment_date: string,
     *   driver_name: ?string,
     *   vehicle_number: ?string,
     *   items: [{delivery_item_id, qty_shipped}],
     * }
     */
    public function processShipment(Delivery $delivery, array $shipmentData): Shipment
    {
        return DB::transaction(function () use ($delivery, $shipmentData) {
 
            // Validasi qty tidak melebihi sisa
            foreach ($shipmentData['items'] as $itemData) {
                $deliveryItem = DeliveryItem::findOrFail($itemData['delivery_item_id']);
                $remaining    = $deliveryItem->qtyRemaining();
 
                if ($itemData['qty_shipped'] > $remaining) {
                    throw ValidationException::withMessages([
                        'qty' => "Qty pengiriman ({$itemData['qty_shipped']}) melebihi sisa " .
                                 "({$remaining}) untuk produk ID {$deliveryItem->product_id}.",
                    ]);
                }
            }
 
            // Buat shipment
            $shipment = Shipment::create([
                'shipment_number' => $this->generateShipmentNumber(),
                'delivery_id'     => $delivery->id,
                'shipment_date'   => $shipmentData['shipment_date'],
                'driver_name'     => $shipmentData['driver_name'] ?? null,
                'vehicle_number'  => $shipmentData['vehicle_number'] ?? null,
                'notes'           => $shipmentData['notes'] ?? null,
            ]);
 
            // Buat shipment items + kurangi stok
            foreach ($shipmentData['items'] as $itemData) {
                $deliveryItem = DeliveryItem::findOrFail($itemData['delivery_item_id']);
                $product      = Product::findOrFail($deliveryItem->product_id);
 
                ShipmentItem::create([
                    'shipment_id'      => $shipment->id,
                    'delivery_item_id' => $deliveryItem->id,
                    'product_id'       => $product->id,
                    'qty_shipped'      => $itemData['qty_shipped'],
                ]);
 
                // Update qty_delivered di delivery item
                $deliveryItem->increment('qty_delivered', $itemData['qty_shipped']);
 
                // Kurangi stok
                $this->stockService->reduceStock(
                    $product,
                    $itemData['qty_shipped'],
                    'shipment',
                    $shipment->id,
                    "Pengiriman {$shipment->shipment_number}"
                );
            }
 
            // Update status delivery
            $this->updateDeliveryStatus($delivery);
 
            return $shipment->fresh('items');
        });
    }
 
    private function updateDeliveryStatus(Delivery $delivery): void
    {
        $delivery->refresh();
        $allDelivered = $delivery->items->every(fn($item) => $item->isFullyDelivered());
        $anyDelivered = $delivery->items->some(fn($item) => $item->qty_delivered > 0);
 
        $status = match (true) {
            $allDelivered => 'completed',
            $anyDelivered => 'partial',
            default       => 'pending',
        };
 
        $delivery->update(['status' => $status]);
 
        // Update delivery_status di transaksi
        $transaction  = $delivery->transaction;
        $deliveries   = $transaction->deliveries;
        $allCompleted = $deliveries->every(fn($d) => $d->status === 'completed');
        $anyShipped   = $deliveries->some(fn($d) => $d->status !== 'pending');
 
        $txStatus = match (true) {
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
