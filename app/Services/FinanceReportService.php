<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ExpenseType;
use App\Enums\InvoiceStatus;
use App\Filament\Pages\PointOfSale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinanceReportService
{
    /** Mirror of PointOfSale::CATEGORY_TABS so reports and POS agree on sales categories. */
    public const SALES_CATEGORIES = PointOfSale::CATEGORY_TABS;

    /** Bucket for invoice lines whose item/service carries no category. */
    protected const UNCATEGORIZED = 'أخرى';

    /**
     * Today's headline numbers for the dashboard.
     *
     * @param  int[]  $centerIds  empty = all centers
     * @return array{sales: int, expenses: int, net: int, invoice_count: int, low_stock_count: int}
     */
    public function todayStats(array $centerIds): array
    {
        $today = now()->toDateString();

        $invoices = DB::table('invoices')
            ->where('status', InvoiceStatus::Paid->value)
            ->whereDate('created_at', $today)
            ->when($centerIds !== [], fn (Builder $query) => $query->whereIn('center_id', $centerIds))
            ->selectRaw('COALESCE(SUM(total), 0) as sales, COUNT(*) as invoice_count')
            ->first();

        $expenses = (int) DB::table('expenses')
            ->whereDate('date', $today)
            ->when($centerIds !== [], fn (Builder $query) => $query->whereIn('center_id', $centerIds))
            ->sum('amount');

        $prefix = DB::getTablePrefix();

        // One grouped pass over the movement log; items with no movements count
        // as zero stock, so a fresh item below its threshold shows up too.
        $lowStock = DB::table('inventory_items')
            ->leftJoin('stock_movements', 'stock_movements.inventory_item_id', '=', 'inventory_items.id')
            ->where('inventory_items.is_active', true)
            ->when($centerIds !== [], fn (Builder $query) => $query->whereIn('inventory_items.center_id', $centerIds))
            ->groupBy('inventory_items.id', 'inventory_items.min_stock_threshold')
            ->havingRaw("COALESCE(SUM({$prefix}stock_movements.quantity), 0) <= {$prefix}inventory_items.min_stock_threshold")
            ->select('inventory_items.id');

        $lowStockCount = DB::query()->fromSub($lowStock, 'low_stock')->count();

        $sales = (int) ($invoices->sales ?? 0);

        return [
            'sales' => $sales,
            'expenses' => $expenses,
            'net' => $sales - $expenses,
            'invoice_count' => (int) ($invoices->invoice_count ?? 0),
            'low_stock_count' => $lowStockCount,
        ];
    }

    /**
     * Paid-invoice revenue per day (and per center when $perCenter) in [$from, $to].
     *
     * @param  int[]  $centerIds  empty = all centers
     * @return Collection<int, object{date: string, center_id: int|null, total: int}>
     */
    public function dailyRevenue(string $from, string $to, array $centerIds, bool $perCenter = false): Collection
    {
        $query = DB::table('invoices')
            ->where('status', InvoiceStatus::Paid->value)
            ->whereRaw('DATE(created_at) BETWEEN ? AND ?', [$from, $to])
            ->when($centerIds !== [], fn (Builder $q) => $q->whereIn('center_id', $centerIds))
            ->selectRaw('DATE(created_at) as date, SUM(total) as total')
            ->groupByRaw('DATE(created_at)');

        if ($perCenter) {
            $query->addSelect('center_id')->groupBy('center_id');
        }

        return $query
            ->orderBy('date')
            ->get()
            ->map(fn (object $row): object => (object) [
                'date' => (string) $row->date,
                'center_id' => $perCenter ? (int) $row->center_id : null,
                'total' => (int) $row->total,
            ]);
    }

    /**
     * Gross sales per category (sum of line totals of PAID invoices in [$from, $to]).
     * Inventory lines bucket by inventory_items.category, service lines by their
     * service category name; lines with neither land in 'أخرى'.
     *
     * @param  int[]  $centerIds  empty = all centers
     * @return Collection<int, object{category: string, total: int}>
     */
    public function salesByCategory(string $from, string $to, array $centerIds): Collection
    {
        $prefix = DB::getTablePrefix();

        // The fallback is inlined (it is a class constant, not user input):
        // with a `?` placeholder ONLY_FULL_GROUP_BY would not recognize the
        // GROUP BY expression as the one in the SELECT list, and a bare
        // `category` alias resolves to the inventory_items column instead.
        $fallback = DB::connection()->getPdo()->quote(self::UNCATEGORIZED);
        $bucket = "COALESCE({$prefix}inventory_items.category, {$prefix}service_categories.name, {$fallback})";

        return DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('inventory_items', 'inventory_items.id', '=', 'invoice_items.inventory_item_id')
            ->leftJoin('services', 'services.id', '=', 'invoice_items.service_id')
            ->leftJoin('service_categories', 'service_categories.id', '=', 'services.category_id')
            ->where('invoices.status', InvoiceStatus::Paid->value)
            ->whereRaw("DATE({$prefix}invoices.created_at) BETWEEN ? AND ?", [$from, $to])
            ->when($centerIds !== [], fn (Builder $q) => $q->whereIn('invoices.center_id', $centerIds))
            ->selectRaw("{$bucket} as category, SUM({$prefix}invoice_items.line_total) as total")
            ->groupByRaw($bucket)
            ->orderByDesc('total')
            ->get()
            ->map(fn (object $row): object => (object) [
                'category' => (string) $row->category,
                'total' => (int) $row->total,
            ]);
    }

    /**
     * Totals for the unified income + expenses report.
     *
     * @param  array{from?: ?string, to?: ?string, center_ids?: int[], movement?: string, category?: ?string}  $filters
     * @return array{income: int, expenses: int, net: int}
     */
    public function unifiedTotals(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);

        $income = 0;

        if ($this->includesIncome($filters)) {
            $query = $this->incomeQuery($filters);

            // A sales-category filter reports the gross line totals of that
            // category; otherwise income is the invoice total net of discount.
            $income = $this->isSalesCategory($filters['category'])
                ? (int) $query->sum('invoice_items.line_total')
                : (int) $query->sum('invoices.total');
        }

        $expenses = 0;

        if ($this->includesExpenses($filters)) {
            $expenses = (int) $this->expenseQuery($filters)->sum('amount');
        }

        return [
            'income' => $income,
            'expenses' => $expenses,
            'net' => $income - $expenses,
        ];
    }

    /**
     * Paginated unified rows, newest first: one row per invoice / per expense,
     * aggregated in SQL via UNION ALL. Same filter semantics as unifiedTotals,
     * so totals and rows always agree.
     *
     * @param  array{from?: ?string, to?: ?string, center_ids?: int[], movement?: string, category?: ?string}  $filters
     */
    public function unifiedRows(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        $filters = $this->normalizeFilters($filters);
        $prefix = DB::getTablePrefix();

        $parts = [];

        if ($this->includesIncome($filters)) {
            $query = $this->incomeQuery($filters);

            if ($this->isSalesCategory($filters['category'])) {
                $query
                    ->selectRaw(
                        "DATE({$prefix}invoices.created_at) as date,"
                        ." 'income' as kind,"
                        ." CAST({$prefix}invoices.number AS CHAR) as reference,"
                        ." {$prefix}invoices.beneficiary_name as description,"
                        .' ? as category,'
                        ." {$prefix}invoices.center_id as center_id,"
                        ." SUM({$prefix}invoice_items.line_total) as amount",
                        [$filters['category']],
                    )
                    // Selected invoice columns are functionally dependent on the key.
                    ->groupBy('invoices.id');
            } else {
                $query->selectRaw(
                    "DATE({$prefix}invoices.created_at) as date,"
                    ." 'income' as kind,"
                    ." CAST({$prefix}invoices.number AS CHAR) as reference,"
                    ." {$prefix}invoices.beneficiary_name as description,"
                    .' NULL as category,'
                    ." {$prefix}invoices.center_id as center_id,"
                    ." {$prefix}invoices.total as amount",
                );
            }

            $parts[] = $query;
        }

        if ($this->includesExpenses($filters)) {
            $parts[] = $this->expenseQuery($filters)->selectRaw(
                "{$prefix}expenses.date as date,"
                ." 'expense' as kind,"
                ." CAST({$prefix}expenses.id AS CHAR) as reference,"
                ." {$prefix}expenses.payee as description,"
                ." {$prefix}expenses.type as category,"
                ." {$prefix}expenses.center_id as center_id,"
                ." {$prefix}expenses.amount as amount",
            );
        }

        if ($parts === []) {
            // Contradictory filters (e.g. movement=income with an expense-type
            // category) legitimately select nothing.
            $parts[] = DB::table('expenses')
                ->whereRaw('1 = 0')
                ->selectRaw(
                    "{$prefix}expenses.date as date, '' as kind, '' as reference,"
                    ." '' as description, NULL as category, 0 as center_id, 0 as amount",
                );
        }

        $union = array_shift($parts);

        foreach ($parts as $part) {
            $union = $union->unionAll($part);
        }

        return DB::query()
            ->fromSub($union, 'movements')
            ->orderByDesc('date')
            ->orderBy('kind')
            ->orderByDesc('reference')
            ->paginate($perPage)
            ->through(fn (object $row): object => (object) [
                'date' => (string) $row->date,
                'kind' => (string) $row->kind,
                'reference' => (string) $row->reference,
                'description' => (string) $row->description,
                'category' => $row->category === null ? null : (string) $row->category,
                'center_id' => (int) $row->center_id,
                'amount' => (int) $row->amount,
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{from: ?string, to: ?string, center_ids: int[], movement: string, category: ?string}
     */
    protected function normalizeFilters(array $filters): array
    {
        return [
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'center_ids' => array_map(intval(...), $filters['center_ids'] ?? []),
            'movement' => $filters['movement'] ?? 'all',
            'category' => $filters['category'] ?? null,
        ];
    }

    protected function isSalesCategory(?string $category): bool
    {
        return $category !== null && in_array($category, self::SALES_CATEGORIES, true);
    }

    protected function isExpenseCategory(?string $category): bool
    {
        return $category !== null && in_array($category, ExpenseType::values(), true);
    }

    /** @param  array{movement: string, category: ?string}  $filters */
    protected function includesIncome(array $filters): bool
    {
        return $filters['movement'] !== 'expenses' && ! $this->isExpenseCategory($filters['category']);
    }

    /** @param  array{movement: string, category: ?string}  $filters */
    protected function includesExpenses(array $filters): bool
    {
        return $filters['movement'] !== 'income' && ! $this->isSalesCategory($filters['category']);
    }

    /**
     * Paid invoices under the unified filters; with a sales-category filter the
     * invoice_items/category joins are attached and lines restricted to it.
     *
     * @param  array{from: ?string, to: ?string, center_ids: int[], movement: string, category: ?string}  $filters
     */
    protected function incomeQuery(array $filters): Builder
    {
        $prefix = DB::getTablePrefix();

        $query = DB::table('invoices')
            ->where('invoices.status', InvoiceStatus::Paid->value)
            ->when($filters['from'] !== null, fn (Builder $q) => $q->whereRaw("DATE({$prefix}invoices.created_at) >= ?", [$filters['from']]))
            ->when($filters['to'] !== null, fn (Builder $q) => $q->whereRaw("DATE({$prefix}invoices.created_at) <= ?", [$filters['to']]))
            ->when($filters['center_ids'] !== [], fn (Builder $q) => $q->whereIn('invoices.center_id', $filters['center_ids']));

        if ($this->isSalesCategory($filters['category'])) {
            $query
                ->join('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
                ->leftJoin('inventory_items', 'inventory_items.id', '=', 'invoice_items.inventory_item_id')
                ->leftJoin('services', 'services.id', '=', 'invoice_items.service_id')
                ->leftJoin('service_categories', 'service_categories.id', '=', 'services.category_id')
                ->whereRaw(
                    "COALESCE({$prefix}inventory_items.category, {$prefix}service_categories.name, ?) = ?",
                    [self::UNCATEGORIZED, $filters['category']],
                );
        }

        return $query;
    }

    /**
     * Expenses under the unified filters.
     *
     * @param  array{from: ?string, to: ?string, center_ids: int[], movement: string, category: ?string}  $filters
     */
    protected function expenseQuery(array $filters): Builder
    {
        return DB::table('expenses')
            ->when($filters['from'] !== null, fn (Builder $q) => $q->where('expenses.date', '>=', $filters['from']))
            ->when($filters['to'] !== null, fn (Builder $q) => $q->where('expenses.date', '<=', $filters['to']))
            ->when($filters['center_ids'] !== [], fn (Builder $q) => $q->whereIn('expenses.center_id', $filters['center_ids']))
            ->when($this->isExpenseCategory($filters['category']), fn (Builder $q) => $q->where('expenses.type', $filters['category']));
    }
}
