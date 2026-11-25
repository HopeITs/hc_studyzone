<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Roles;
use App\Support\FinanceDisplay;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;

class AdmissionPreview extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected string $view = 'filament.pages.admission-preview';

    public const DAY = '2026-11-25';

    public const CENTERS = ['north', 'south'];

    public const TYPES = ['daily', 'monthly', 'free'];

    public string $center = 'north';

    public string $mode = 'daily';

    public bool $collect = true;

    public string $amount = '';

    public string $start = '';

    public string $end = '';

    #[Locked]
    public string $detail = '';

    #[Locked]
    public bool $history = false;

    #[Locked]
    public bool $scheduling = false;

    #[Locked]
    public array $prices = [];

    #[Locked]
    public array $visits = [];

    #[Locked]
    public bool $recorded = false;

    public static function canAccess(): bool
    {
        return app()->environment('local') && auth()->user()?->role === Roles::GeneralAdmin();
    }

    public static function getNavigationLabel(): string
    {
        return __('general.admission_preview.title');
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public function mount(): void
    {
        foreach (['north' => [100, 1500], 'south' => [50, 1200]] as $center => [$daily, $monthly]) {
            foreach (['daily' => $daily, 'monthly' => $monthly] as $type => $amount) {
                $this->prices[$center][$type] = [
                    ['amount' => intdiv($amount, 2), 'start' => '2026-10-01', 'end' => '2026-10-31'],
                    ['amount' => $amount, 'start' => '2026-11-01', 'end' => null],
                ];
            }

            $this->visits[$center] = [
                ['type' => 'daily', 'amount' => $daily, 'collected' => true],
                ['type' => 'daily', 'amount' => $daily, 'collected' => false],
                ['type' => 'monthly', 'amount' => 0, 'collected' => false],
                ['type' => 'free', 'amount' => 0, 'collected' => false],
            ];
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('currency')
                ->label(fn (): string => $this->currency())
                ->icon('heroicon-o-arrows-right-left')
                ->color('gray')
                ->action(function (): void {
                    FinanceDisplay::toggle();
                    $this->reset('amount');
                    $this->resetValidation('amount');
                }),
        ];
    }

    public function currency(): string
    {
        return __('general.admission_preview.'.(FinanceDisplay::enabled() ? 'old' : 'new'));
    }

    public function money(int $amount): string
    {
        return FinanceDisplay::format($amount).' '.$this->currency();
    }

    public function openDetail(string $type): void
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        $this->detail = $type;
        $this->history = false;
        $this->scheduling = false;
        $this->resetValidation();
    }

    public function closeDetail(): void
    {
        $this->detail = '';
        $this->scheduling = false;
    }

    public function showHistory(bool $history): void
    {
        $this->history = $history;
    }

    public function openSchedule(): void
    {
        $this->scheduling = in_array($this->detail, ['daily', 'monthly'], true);
        $this->reset('amount', 'end');
        $this->start = Carbon::parse(self::DAY)->addDay()->toDateString();
        $this->resetValidation();
    }

    public function schedule(): void
    {
        abort_unless($this->scheduling, 403);

        $this->validate([
            'center' => ['required', 'in:'.implode(',', self::CENTERS)],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'start' => ['required', 'date_format:Y-m-d', 'after:'.self::DAY],
            'end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
        ]);

        $amount = (int) $this->amount;
        $rows = &$this->prices[$this->center][$this->detail];
        $last = array_key_last($rows);

        if (FinanceDisplay::enabled() && $amount % 100 !== 0) {
            $this->addError('amount', __('general.admission_preview.amount_multiple'));

            return;
        }

        if ($this->start <= $rows[$last]['start'] || ($rows[$last]['end'] && $this->start <= $rows[$last]['end'])) {
            $this->addError('start', __('general.admission_preview.overlap'));

            return;
        }

        $rows[$last]['end'] ??= Carbon::parse($this->start)->subDay()->toDateString();
        $rows[] = [
            'amount' => FinanceDisplay::enabled() ? intdiv($amount, 100) : $amount,
            'start' => $this->start,
            'end' => $this->end ?: null,
        ];
        $this->scheduling = false;
        $this->history = true;
    }

    public function status(array $row): string
    {
        if ($row['start'] > self::DAY) {
            return 'scheduled';
        }

        return $row['end'] && $row['end'] < self::DAY ? 'expired' : 'active';
    }

    public function currentDailyPrice(): int
    {
        foreach ($this->prices[$this->center]['daily'] ?? [] as $row) {
            if ($this->status($row) === 'active') {
                return $row['amount'];
            }
        }

        return 0;
    }

    public function recordVisit(): void
    {
        $this->validate([
            'center' => ['required', 'in:'.implode(',', self::CENTERS)],
            'mode' => ['required', 'in:'.implode(',', self::TYPES)],
        ]);

        $this->visits[$this->center][] = [
            'type' => $this->mode,
            'amount' => $this->mode === 'daily' ? $this->currentDailyPrice() : 0,
            'collected' => $this->mode === 'daily' && $this->collect,
        ];
        $this->recorded = true;
    }
}
