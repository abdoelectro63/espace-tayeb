<?php

namespace App\Filament\Resources\ManualInvoices\Pages;

use App\Filament\Resources\ManualInvoices\ManualInvoiceResource;
use App\Models\ManualInvoice;
use App\Models\ManualInvoiceLine;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditManualInvoice extends EditRecord
{
    protected static string $resource = ManualInvoiceResource::class;

    /** @var list<array<string, mixed>>|null */
    protected ?array $pendingLineRows = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->record instanceof ManualInvoice && $this->record->trashed()) {
            abort(404);
        }
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var \App\Models\ManualInvoice $record */
        $record = $this->record;

        $data['line_rows'] = $record->lines()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ManualInvoiceLine $l): array => [
                'designation' => $l->designation,
                'quantity' => $l->quantity,
                'unit_price' => $l->unit_price,
            ])
            ->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $lines = $data['line_rows'] ?? [];
        if (! is_array($lines) || $lines === []) {
            throw ValidationException::withMessages([
                'line_rows' => ['Ajoutez au moins une ligne produit.'],
            ]);
        }

        $valid = 0;
        foreach ($lines as $row) {
            if (is_array($row) && trim((string) ($row['designation'] ?? '')) !== '') {
                $valid++;
            }
        }
        if ($valid === 0) {
            throw ValidationException::withMessages([
                'line_rows' => ['Chaque ligne doit avoir une désignation.'],
            ]);
        }

        $this->pendingLineRows = $lines;
        unset($data['line_rows']);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var \App\Models\ManualInvoice $record */
        $record = $this->record;

        $lines = $this->pendingLineRows ?? [];
        $this->pendingLineRows = null;

        $record->lines()->delete();

        foreach (array_values($lines) as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $designation = trim((string) ($row['designation'] ?? ''));
            if ($designation === '') {
                continue;
            }
            $record->lines()->create([
                'sort_order' => $i,
                'designation' => $designation,
                'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
                'unit_price' => round((float) ($row['unit_price'] ?? 0), 2),
            ]);
        }
    }
}
