<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Transaction;
use App\Services\DiscountService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

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

    public function suratJalan(Delivery $delivery): Response
    {
        $delivery->load([
            'transaction.customer',
            'items.product',
            'shipments.items',
            'user',
        ]);

        $pdf = Pdf::loadView('pdf.surat-jalan', [
            'delivery' => $delivery,
        ])
        ->setPaper('A4')
        ->setOptions(['defaultFont' => 'Arial']);

        return $pdf->download("SuratJalan-{$delivery->do_number}.pdf");
    }
}