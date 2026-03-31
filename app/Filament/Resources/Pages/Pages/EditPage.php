<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $seo = $data['seo'] ?? null;
        if ($seo === null || $seo === [] || $seo === '') {
            unset($data['seo']);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewStore')
                ->label('عرض الصفحة')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->url(fn (): string => route('page.show', ['slug' => $this->record->slug]))
                ->openUrlInNewTab()
                ->visible(fn (): bool => (bool) $this->record->is_published),
            DeleteAction::make(),
        ];
    }
}
