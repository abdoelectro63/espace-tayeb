<?php

namespace App\Services\Shipping;

use App\Models\ShippingInvoiceImport;
use App\Models\ShippingInvoiceImportLine;
use Illuminate\Support\Facades\DB;

class ShippingInvoiceImportRecorder
{
    public function __construct(
        private readonly ShippingInvoiceImporter $importer,
    ) {}

    public function recordFromText(string $rawText, string $carrierFilter, ?int $userId): ShippingInvoiceImportRecordResult
    {
        $analyzed = $this->importer->analyzeForImport($rawText, $carrierFilter);

        $alreadyPaidRows = array_values(array_filter(
            $analyzed,
            static fn (array $row): bool => ($row['match_status'] ?? '') === 'already_paid',
        ));

        /** @var list<array{tracking_key: string, carrier: string}> $alreadyPaidRejectedLines */
        $alreadyPaidRejectedLines = array_map(
            static fn (array $row): array => [
                'tracking_key' => (string) ($row['tracking_key'] ?? ''),
                'carrier' => (string) ($row['carrier'] ?? ''),
            ],
            $alreadyPaidRows,
        );

        $lines = array_values(array_filter(
            $analyzed,
            static fn (array $row): bool => ! in_array(($row['match_status'] ?? ''), ['not_found', 'already_paid'], true),
        ));

        // Pas d’enregistrement dans shipping_invoice_imports si aucune ligne à persister (أسطر الفاتورة = 0).
        if ($lines === []) {
            return new ShippingInvoiceImportRecordResult(
                import: null,
                alreadyPaidRejectedCount: count($alreadyPaidRejectedLines),
                alreadyPaidRejectedLines: $alreadyPaidRejectedLines,
            );
        }

        $import = DB::transaction(function () use ($lines, $carrierFilter, $userId) {
            $import = ShippingInvoiceImport::query()->create([
                'user_id' => $userId,
                'carrier_filter' => $carrierFilter,
            ]);

            foreach ($lines as $row) {
                ShippingInvoiceImportLine::query()->create(array_merge($row, [
                    'shipping_invoice_import_id' => $import->id,
                ]));
            }

            return $import->fresh(['lines']);
        });

        return new ShippingInvoiceImportRecordResult(
            import: $import,
            alreadyPaidRejectedCount: count($alreadyPaidRejectedLines),
            alreadyPaidRejectedLines: $alreadyPaidRejectedLines,
        );
    }
}
