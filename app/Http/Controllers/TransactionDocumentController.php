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

        return $pdf->download($this->quotationNumber($transaction) . '.pdf');
    }

    public function invoice(Request $request, $id)
    {
        $transaction = $this->getTransaction($request, $id);

        $pdf = Pdf::loadView('pdf.transaction-invoice', compact('transaction'));

        return $pdf->download($this->invoiceNumber($transaction) . '.pdf');
    }

    public function receipt(Request $request, $id)
    {
        $transaction = $this->getTransaction($request, $id);

        $pdf = Pdf::loadView('pdf.transaction-receipt', compact('transaction'));

        return $pdf->download($this->receiptNumber($transaction) . '.pdf');
    }

    protected function quotationNumber(Transaction $transaction): string
    {
        return 'QUO-' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT);
    }

    protected function invoiceNumber(Transaction $transaction): string
    {
        return 'INV-' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT);
    }

    protected function receiptNumber(Transaction $transaction): string
    {
        return 'REC-' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT);
    }
}