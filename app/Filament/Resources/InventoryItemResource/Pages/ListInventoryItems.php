<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Enums\Roles;
use App\Exports\InventoryItemsExport;
use App\Filament\Resources\InventoryItemResource;
use App\Models\Center;
use App\Services\FinanceTemplateImporter;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListInventoryItems extends ListRecords
{
    protected static string $resource = InventoryItemResource::class;

    // The blank template staff fill before importing (kept in the repo).
    private const TEMPLATE_PATH = 'financial_templates/01_المواد_والمستودع.xlsx';

    // Errors shown in the notification; the rest are summarised as a count.
    private const MAX_ERRORS_SHOWN = 10;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('inventory.actions.export'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->action(function () {
                    return Excel::download(
                        new InventoryItemsExport($this->getFilteredTableQuery()),
                        'inventory-'.now()->format('Y-m-d').'.xlsx'
                    );
                }),

            ActionGroup::make([
                $this->importAction(),
                $this->downloadTemplateAction(),
            ])
                ->label(__('inventory.actions.import'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->button()
                ->visible(fn (): bool => InventoryItemResource::canCreate()),

            CreateAction::make(),
        ];
    }

    private function importAction(): Action
    {
        $isGeneralAdmin = Auth::user()->role === Roles::GeneralAdmin();

        return Action::make('import')
            ->label(__('inventory.import.upload'))
            ->icon('heroicon-o-arrow-down-tray')
            ->modalHeading(__('inventory.import.heading'))
            ->modalDescription(__('inventory.import.description'))
            ->modalSubmitActionLabel(__('inventory.import.submit'))
            ->schema([
                Select::make('center_id')
                    ->label(__('inventory.fields.center'))
                    ->options(fn () => Center::query()->get()->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->visible($isGeneralAdmin),

                FileUpload::make('file')
                    ->label(__('inventory.import.file'))
                    // Uploaded stock lists are internal business data: keep
                    // them on the private disk and delete them after import.
                    ->disk('local')
                    ->visibility('private')
                    ->directory('inventory-imports')
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->maxSize(5120)
                    ->required(),
            ])
            ->action(function (array $data, FinanceTemplateImporter $importer): void {
                $user = Auth::user();

                // Non-general admins can only ever import into their own center.
                $centerId = $user->role === Roles::GeneralAdmin()
                    ? (int) $data['center_id']
                    : (int) $user->center_id;

                $path = Storage::disk('local')->path($data['file']);

                try {
                    $result = $importer->import($path, $centerId, $user->id);
                } catch (\Throwable $e) {
                    report($e);
                    $this->notifyFailure([__('inventory.import.unreadable')]);

                    return;
                } finally {
                    Storage::disk('local')->delete($data['file']);
                }

                if ($result['type'] !== 'inventory') {
                    $this->notifyFailure([__('inventory.import.wrong_template')]);

                    return;
                }

                if ($importer->errors !== []) {
                    $this->notifyFailure($importer->errors);

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('inventory.import.success', ['count' => $result['created']]))
                    ->send();
            });
    }

    private function downloadTemplateAction(): Action
    {
        return Action::make('download_template')
            ->label(__('inventory.import.download_template'))
            ->icon('heroicon-o-document-arrow-down')
            ->action(fn (): BinaryFileResponse => response()->download(
                base_path(self::TEMPLATE_PATH),
                'نموذج_المواد_والمستودع.xlsx'
            ));
    }

    /** @param list<string> $errors */
    private function notifyFailure(array $errors): void
    {
        $shown = array_slice($errors, 0, self::MAX_ERRORS_SHOWN);
        $hidden = count($errors) - count($shown);

        if ($hidden > 0) {
            $shown[] = __('inventory.import.more_errors', ['count' => $hidden]);
        }

        // Messages quote cell values from the uploaded file: escape each line.
        $body = new HtmlString(implode('<br>', array_map(fn (string $line): string => '• '.e($line), $shown)));

        Notification::make()
            ->danger()
            ->title(__('inventory.import.failed'))
            ->body($body)
            ->persistent()
            ->send();
    }
}
