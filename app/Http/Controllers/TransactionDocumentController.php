<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class TransactionDocumentController extends Controller
{
    protected function getTransaction(Request $request, $id): Transaction
    {
        $user = $request->user();

        return Transaction::query()
            ->where('branch_id', $user->branch_id)
            ->where('id', $id)
            ->with([
                'customer:id,name,phone,email,address',
                'vehicle:id,license_plate,make,model,year',
                'items.part:id,name,variant,sku'
            ])
            ->firstOrFail();
    }

    public function quotation(Request $request, $id)
    {
        $transaction = $this->getTransaction($request, $id);

        $pdf = Pdf::loadView('pdf.transaction-quotation', compact('transaction'));

        return $pdf->download(($transaction->quotation_number ?? 'quotation') . '.pdf');
    }

    public function invoice(Request $request, $id)
    {
        $transaction = $this->getTransaction($request, $id);

        $pdf = Pdf::loadView('pdf.transaction-invoice', compact('transaction'));

        return $pdf->download(($transaction->invoice_number ?? 'invoice') . '.pdf');
    }

    public function receipt(Request $request, $id)
    {
        $transaction = $this->getTransaction($request, $id);

        $pdf = Pdf::loadView('pdf.transaction-receipt', compact('transaction'));

        return $pdf->download(($transaction->receipt_number ?? 'receipt') . '.pdf');
    }
}