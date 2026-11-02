<?php

namespace App\Filament\Pages;

use App\Enums\InvoiceStatus;
use App\Enums\Roles;
use App\Models\CashShift;
use App\Models\Center;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Student;
use App\Services\PosService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PointOfSale extends Page
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static string|\UnitEnum|null $navigationGroup = 'النظام المالي';

    protected static ?string $slug = 'pos';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.point-of-sale';

    /**
     * Fixed catalog tabs. These are data (category names shared between
     * service categories and inventory item categories), not UI strings.
     */
    public const CATEGORY_TABS = [
        'دخوليات',
        'طباعة',
        'قرطاسية',
        'مشروبات',
        'مأكولات',
        'حجوزات',
        'خدمات',
    ];

    public ?int $centerId = null;

    public string $activeTab = 'all';

    public string $search = '';

    /**
     * Cart lines keyed by "i-{id}" (inventory) or "s-{id}" (service):
     * ['kind', 'id', 'name', 'unit_price', 'quantity'].
     *
     * @var array<string, array<string, mixed>>
     */
    public array $cart = [];

    public string $beneficiaryName = '';

    public ?string $studentNumber = null;

    /** UUID of the linked student, when the beneficiary is a registered one. */
    public ?string $studentId = null;

    public string $studentSearch = '';

    public string $discountType = 'amount';

    public $discountValue = null;

    public ?string $note = null;

    public static function getNavigationLabel(): string
    {
        return __('pos.title');
    }

    public function getTitle(): string
    {
        return __('pos.title');
    }

    public static function canAccess(): bool
    {
        return in_array(Auth::user()?->role, [
            Roles::GeneralAdmin(),
            Roles::CenterAdmin(),
            Roles::Cashier(),
        ], true);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->centerId = $this->effectiveCenterId();
    }

    /**
     * The center every read/write must act on. Center-bound roles
     * (CenterAdmin, Cashier) ALWAYS act on their own center — the public
     * $centerId property is client-writable and is never trusted for them.
     * Only a GeneralAdmin may pick a center through the dropdown.
     */
    public function effectiveCenterId(): ?int
    {
        $user = Auth::user();

        if (in_array($user->role, [Roles::CenterAdmin(), Roles::Cashier()], true)) {
            return $user->center_id !== null ? (int) $user->center_id : null;
        }

        return $this->centerId !== null ? (int) $this->centerId : null;
    }

    /** One place to tighten later if cashiers should lose the discount field. */
    public function canApplyDiscount(): bool
    {
        return in_array(Auth::user()->role, [
            Roles::GeneralAdmin(),
            Roles::CenterAdmin(),
            Roles::Cashier(),
        ], true);
    }

    /** Mirrors the cancel gate on InvoiceResource. */
    public function canCancelInvoice(): bool
    {
        return Auth::user()->role === Roles::GeneralAdmin();
    }

    public function isGeneralAdmin(): bool
    {
        return Auth::user()->role === Roles::GeneralAdmin();
    }

    /** @return Collection<int, Center> */
    public function getCentersProperty(): Collection
    {
        return Center::query()->orderBy('order')->get(['id', 'name']);
    }

    public function updatedCenterId(): void
    {
        $this->cart = [];
    }

    /** The currently open cash shift for the selected center, if any. */
    public function getOpenShiftProperty(): ?CashShift
    {
        $centerId = $this->effectiveCenterId();

        if (! $centerId) {
            return null;
        }

        return CashShift::openShiftForCenter($centerId);
    }

    /**
     * Catalog cards for the selected center, tab and search:
     * ['key', 'kind', 'id', 'name', 'category', 'unit_price', 'stock'(items only)].
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getCatalogProperty(): Collection
    {
        $centerId = $this->effectiveCenterId();

        if (! $centerId) {
            return collect();
        }

        $items = InventoryItem::query()
            ->where('center_id', $centerId)
            ->where('is_active', true)
            ->withSum('movements as stock_sum', 'quantity')
            ->when($this->activeTab !== 'all', fn ($q) => $q->where('category', $this->activeTab))
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->get()
            ->map(fn (InventoryItem $item) => [
                'key' => 'i-'.$item->id,
                'kind' => 'inventory',
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category,
                'unit_price' => (int) $item->sale_price,
                'stock' => (int) ($item->stock_sum ?? 0),
                'threshold' => (int) $item->min_stock_threshold,
            ]);

        $services = Service::query()
            ->where('is_active', true)
            ->when(
                $this->activeTab !== 'all',
                fn ($q) => $q->whereHas('category', fn ($c) => $c->where('name', $this->activeTab))
            )
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->with('category:id,name')
            ->orderBy('name')
            ->get();

        $prices = ServicePrice::activePricesFor($centerId, $services->pluck('id')->all());

        $services = $services
            ->filter(fn (Service $service) => array_key_exists($service->id, $prices))
            ->map(fn (Service $service) => [
                'key' => 's-'.$service->id,
                'kind' => 'service',
                'id' => $service->id,
                'name' => $service->name,
                'category' => $service->category?->name,
                'unit_price' => $prices[$service->id],
                'stock' => null,
            ]);

        return $items->concat($services)->values();
    }

    public function addToCart(string $key): void
    {
        if (isset($this->cart[$key])) {
            $this->cart[$key]['quantity']++;

            return;
        }

        $card = $this->catalog->firstWhere('key', $key);

        if (! $card) {
            return;
        }

        $this->cart[$key] = [
            'kind' => $card['kind'],
            'id' => $card['id'],
            'name' => $card['name'],
            'unit_price' => $card['unit_price'],
            'quantity' => 1,
        ];
    }

    public function removeLine(string $key): void
    {
        unset($this->cart[$key]);
    }

    public function normalizeQuantity(string $key): void
    {
        if (! isset($this->cart[$key])) {
            return;
        }

        $this->cart[$key]['quantity'] = max(1, (int) $this->cart[$key]['quantity']);
    }

    public function clearCart(): void
    {
        $this->reset([
            'cart', 'beneficiaryName', 'studentNumber', 'studentId', 'studentSearch',
            'discountType', 'discountValue', 'note',
        ]);
    }

    /**
     * Live student search results: ['id', 'name', 'phone'].
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getStudentResultsProperty(): Collection
    {
        $term = trim($this->studentSearch);

        if ($this->studentId !== null || mb_strlen($term) < 2) {
            return collect();
        }

        return Student::query()
            ->where(function ($query) use ($term): void {
                $query->where('firstname', 'like', "%{$term}%")
                    ->orWhere('lastname', 'like', "%{$term}%")
                    ->orWhere('father_name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            })
            ->orderBy('firstname')
            ->limit(8)
            ->get(['id', 'firstname', 'father_name', 'lastname', 'phone'])
            ->map(fn (Student $student) => [
                'id' => $student->id,
                'name' => $student->getFilamentName(),
                'phone' => $student->phone,
            ]);
    }

    public function selectStudent(string $studentId): void
    {
        $student = Student::query()->find($studentId);

        if (! $student) {
            return;
        }

        $this->studentId = $student->id;
        $this->beneficiaryName = $student->getFilamentName();
        $this->studentSearch = '';
    }

    public function clearStudent(): void
    {
        $this->reset(['studentId', 'studentSearch', 'beneficiaryName']);
    }

    public function getSubtotalProperty(): int
    {
        return (int) collect($this->cart)
            ->sum(fn (array $line) => max(1, (int) $line['quantity']) * (int) $line['unit_price']);
    }

    public function getDiscountPreviewProperty(): int
    {
        if (! $this->canApplyDiscount() || ! $this->discountValue) {
            return 0;
        }

        return app(PosService::class)->resolveDiscount(
            $this->subtotal,
            $this->discountType,
            (float) $this->discountValue,
        );
    }

    public function collect(): void
    {
        $this->submit(collect: true);
    }

    public function saveUnpaid(): void
    {
        $this->submit(collect: false);
    }

    protected function submit(bool $collect): void
    {
        $centerId = $this->effectiveCenterId();

        if (! $centerId) {
            $this->notifyDanger(__('pos.messages.select_center'));

            return;
        }

        if (empty($this->cart)) {
            $this->notifyDanger(__('pos.messages.cart_empty'));

            return;
        }

        if (trim($this->beneficiaryName) === '') {
            $this->notifyDanger(__('pos.messages.beneficiary_required'));

            return;
        }

        $lines = collect($this->cart)
            ->map(fn (array $line) => [
                'inventory_item_id' => $line['kind'] === 'inventory' ? (int) $line['id'] : null,
                'service_id' => $line['kind'] === 'service' ? (int) $line['id'] : null,
                'quantity' => max(1, (int) $line['quantity']),
            ])
            ->values()
            ->all();

        $service = app(PosService::class);

        $discount = $this->canApplyDiscount() && $this->discountValue
            ? $service->resolveDiscount($this->subtotal, $this->discountType, (float) $this->discountValue)
            : 0;

        try {
            $invoice = $service->createInvoice(
                centerId: $centerId,
                lines: $lines,
                beneficiaryName: trim($this->beneficiaryName),
                studentNumber: filled($this->studentNumber) ? trim($this->studentNumber) : null,
                discount: $discount,
                note: filled($this->note) ? trim($this->note) : null,
                collect: $collect,
                userId: Auth::id(),
                studentId: $this->studentId,
            );
        } catch (ValidationException $e) {
            $this->notifyDanger(collect($e->errors())->flatten()->first());

            return;
        }

        Notification::make()
            ->success()
            ->title($collect
                ? __('pos.messages.collected', ['number' => $invoice->number])
                : __('pos.messages.saved_unpaid', ['number' => $invoice->number]))
            ->send();

        $this->clearCart();

        if ($collect) {
            $this->dispatch('pos-open-print', url: route('pos.invoice.print', $invoice));
        }
    }

    /** @return Collection<int, Invoice> */
    public function getUnpaidInvoicesProperty(): Collection
    {
        $centerId = $this->effectiveCenterId();

        if (! $centerId) {
            return collect();
        }

        return Invoice::query()
            ->where('center_id', $centerId)
            ->where('status', InvoiceStatus::Unpaid->value)
            ->latest('id')
            ->limit(50)
            ->get();
    }

    public function collectInvoice(int $invoiceId): void
    {
        $invoice = $this->findAuthorizedInvoice($invoiceId);

        if (! $invoice) {
            return;
        }

        try {
            $invoice->markPaid();
        } catch (ValidationException $e) {
            $this->notifyDanger(collect($e->errors())->flatten()->first());

            return;
        }

        Notification::make()
            ->success()
            ->title(__('invoices.messages.collected'))
            ->send();

        $this->dispatch('pos-open-print', url: route('pos.invoice.print', $invoice));
    }

    public function cancelInvoice(int $invoiceId): void
    {
        if (! $this->canCancelInvoice()) {
            return;
        }

        $invoice = $this->findAuthorizedInvoice($invoiceId);

        if (! $invoice) {
            return;
        }

        try {
            $invoice->markCancelled();
        } catch (ValidationException $e) {
            $this->notifyDanger(collect($e->errors())->flatten()->first());

            return;
        }

        Notification::make()
            ->success()
            ->title(__('invoices.messages.cancelled'))
            ->send();
    }

    /** The invoice, only when it belongs to the center this cashier is selling for. */
    protected function findAuthorizedInvoice(int $invoiceId): ?Invoice
    {
        $centerId = $this->effectiveCenterId();

        if (! $centerId) {
            return null;
        }

        return Invoice::query()
            ->where('center_id', $centerId)
            ->find($invoiceId);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('unpaidInvoices')
                ->label(__('pos.actions.unpaid_invoices'))
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->badge(fn (): ?string => $this->unpaidInvoices->count() ?: null)
                ->modalHeading(__('pos.actions.unpaid_invoices'))
                ->modalContent(fn (): View => view('filament.pages.partials.pos-unpaid-invoices', [
                    'invoices' => $this->unpaidInvoices,
                    'canCancel' => $this->canCancelInvoice(),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->slideOver(),

            Action::make('teamAllocation')
                ->label(__('pos.actions.team_allocation'))
                ->icon('heroicon-o-user-group')
                ->color('info')
                ->visible(fn (): bool => (bool) $this->effectiveCenterId())
                ->modalHeading(__('pos.actions.team_allocation'))
                ->schema([
                    Repeater::make('lines')
                        ->label(__('pos.fields.items'))
                        ->schema([
                            Select::make('inventory_item_id')
                                ->label(__('invoices.fields.inventory_item'))
                                ->options(fn () => InventoryItem::query()
                                    ->where('center_id', $this->effectiveCenterId())
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id'))
                                ->searchable()
                                ->required(),

                            TextInput::make('quantity')
                                ->label(__('invoices.fields.quantity'))
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->default(1)
                                ->required(),
                        ])
                        ->columns(2)
                        ->minItems(1)
                        ->required(),

                    TextInput::make('recipient')
                        ->label(__('pos.fields.recipient'))
                        ->maxLength(255)
                        ->required(),

                    Textarea::make('note')
                        ->label(__('invoices.fields.note'))
                        ->maxLength(500),
                ])
                ->action(function (array $data): void {
                    try {
                        app(PosService::class)->recordTeamAllocation(
                            centerId: (int) $this->effectiveCenterId(),
                            lines: collect($data['lines'])
                                ->map(fn (array $line) => [
                                    'inventory_item_id' => (int) $line['inventory_item_id'],
                                    'quantity' => (int) $line['quantity'],
                                ])
                                ->values()
                                ->all(),
                            recipient: $data['recipient'],
                            note: $data['note'] ?? null,
                            userId: Auth::id(),
                        );
                    } catch (ValidationException $e) {
                        $this->notifyDanger(collect($e->errors())->flatten()->first());

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title(__('pos.messages.allocation_recorded'))
                        ->send();
                }),
        ];
    }

    protected function notifyDanger(?string $message): void
    {
        Notification::make()
            ->danger()
            ->title($message ?? __('pos.messages.failed'))
            ->send();
    }
}
