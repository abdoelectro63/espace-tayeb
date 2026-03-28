<?php

namespace App\Services\Shipping;

use App\Models\ShippingInvoiceImport;

final class ShippingInvoiceImportRecordResult
{
    /**
     * @param  list<array{tracking_key: string, carrier: string}>  $alreadyPaidRejectedLines
     */
    public function __construct(
        public readonly ?ShippingInvoiceImport $import,
        public readonly int $alreadyPaidRejectedCount,
        public readonly array $alreadyPaidRejectedLines = [],
    ) {}
}
