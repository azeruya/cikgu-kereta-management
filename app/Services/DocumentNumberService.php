<?php

namespace App\Services;

use App\Models\Transaction;

class DocumentNumberService
{
    public static function makeQuotationNumber(): string
    {
        return 'QUO-' . now()->format('YmdHis');
    }

    public static function makeInvoiceNumber(): string
    {
        return 'INV-' . now()->format('YmdHis');
    }

    public static function makeReceiptNumber(): string
    {
        return 'REC-' . now()->format('YmdHis');
    }
}