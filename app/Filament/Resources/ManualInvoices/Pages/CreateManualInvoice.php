<?php

namespace App\Filament\Resources\ManualInvoices\Pages;

use App\Filament\Resources\ManualInvoices\ManualInvoiceResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateManualInvoice extends CreateRecord
{
    protected static string $resource = ManualInvoiceResource::class;

    /** @var list<array<string, mixed>>|null */
    protected ?array $pendingLines = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $lines = $data['line_rows'] ?? [];
        if (! is_array($lines) || $lines === []) {
            throw ValidationException::withMessages([
                'line_rows' => ['Ajoutez au moins une ligne produit.'],
            ]);
        }

        $valid = 0;
        foreach ($lines as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (trim((string) ($row['designation'] ?? '')) !== '') {
                $valid++;
            }
        }
        if ($valid === 0) {
            throw ValidationException::withMessages([
                'line_rows' => ['Chaque ligne doit avoir une désignation.'],
            ]);
        }

        $this->pendingLines = $lines;
        unset($data['line_rows']);
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $rows = $this->pendingLines ?? [];
        foreach (array_values($rows) as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $designation = trim((string) ($row['designation'] ?? ''));
            if ($designation === '') {
                continue;
            }
            $this->record->lines()->create([
                'sort_order' => $i,
                'designation' => $designation,
                'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
                'unit_price' => round((float) ($row['unit_price'] ?? 0), 2),
            ]);
        }

        $this->pendingLines = null;
    }
}
