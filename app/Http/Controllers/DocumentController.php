<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Transaction;
use App\Services\DiscountService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;

class DocumentController extends Controller
{
    public function __construct(
        protected DiscountService $discountService,
    ) {}

    // =============================================
    // INVOICE PDF
    // =============================================

    public function invoice(Transaction $transaction): Response
    {
        $transaction->load(['customer', 'items.product', 'payments.paymentMethod', 'user']);

        $discountBreakdown = [];
        if ($transaction->discount_json) {
            $result            = $this->discountService->apply(
                (float) $transaction->subtotal,
                $transaction->discount_json
            );
            $discountBreakdown = $result['breakdown'];
        }

        $pdf = Pdf::loadView('pdf.invoice', [
            'transaction'       => $transaction,
            'discountBreakdown' => $discountBreakdown,
            'discountSummary'   => $this->discountService->formatSummary($transaction->discount_json ?? []),
        ])
        ->setPaper('A4')
        ->setOptions([
            'defaultFont' => 'Arial',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
        ]);

        return $pdf->download("Invoice-{$transaction->invoice_number}.pdf");
    }

     // =============================================
    // SURAT JALAN PDF
    // =============================================
 
    // =============================================
    // SURAT JALAN PDF
    // Kertas: Continuous 1/2 = 9.5" x 5.5" = 241mm x 140mm
    // DomPDF points: 1mm = 2.8346pt
    //   width  = 241 * 2.8346 = 683pt
    //   height = 140 * 2.8346 = 397pt
    // =============================================
 
    public function suratJalan(Delivery $delivery): Response
    {
        $delivery->load([
            'transaction.customer',
            'transaction.payments.paymentMethod',
            'items.product.unit',
            'items.transactionItem',
            'user',
        ]);
 
        $pdf = Pdf::loadView('pdf.surat-jalan', [
            'delivery' => $delivery,
        ])
        ->setPaper([0, 0, 683, 397])
        ->setOptions([
            'defaultFont'          => 'Arial',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => false,
            'dpi'                  => 96,
        ]);
 
        return $pdf->download("SuratJalan-{$delivery->do_number}.pdf");
    }
}