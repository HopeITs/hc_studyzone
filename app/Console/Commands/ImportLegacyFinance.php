<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Center;
use App\Services\LegacyDatabaseImporter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ImportLegacyFinance extends Command
{
    protected $signature = 'finance:import-legacy
        {center : رقم المركز (id)}
        {connection : اسم الاتصال في config/database.php، أو اسم قاعدة البيانات القديمة الملحقة بـ SQL Server}
        {--user= : رقم المستخدم الذي يُنسب له الاستيراد (اختياري)}
        {--cutoff= : تاريخ حذف الأصفار YYYY-MM-DD — ما قبله يُقسم على 100؛ بدونه تُحوَّل كل المبالغ}
        {--dry-run : تجربة كاملة بدون حفظ أي شيء}';

    protected $description = 'استيراد بيانات النظام القديم DBRestaurantProV5 (مواد، رصيد افتتاحي، فواتير، مصاريف) مع تقرير مطابقة';

    public function handle(LegacyDatabaseImporter $importer): int
    {
        $centerId = (int) $this->argument('center');

        if ($centerId < 1 || Center::query()->whereKey($centerId)->doesntExist()) {
            $this->error('حدد مركزاً صحيحاً.');
            $this->table(['id', 'المركز'], Center::query()->get()->map(
                fn (Center $c) => [$c->id, $c->name]
            )->all());

            return self::FAILURE;
        }

        $cutoff = $this->option('cutoff') !== null
            ? Carbon::parse((string) $this->option('cutoff'))->startOfDay()
            : null;

        $this->line($cutoff === null
            ? 'العملة: كل المبالغ القديمة ستُقسم على 100 (النظام القديم كله قبل حذف الأصفار).'
            : "العملة: المبالغ المؤرخة قبل {$cutoff->toDateString()} فقط ستُقسم على 100.");

        try {
            $legacy = $this->resolveLegacyConnection((string) $this->argument('connection'));
            $stats = $importer->import(
                $legacy,
                $centerId,
                $this->option('user') !== null ? (int) $this->option('user') : null,
                (bool) $this->option('dry-run'),
                $cutoff,
            );
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (\PDOException $e) {
            $this->error('تعذر الاتصال بقاعدة البيانات القديمة: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(['المرحلة', 'في القديم', 'أُنشئ', 'تخطى (مستورد سابقاً)'], [
            ['المواد', $stats['items']['legacy'], $stats['items']['created'], $stats['items']['skipped']],
            ['الفواتير', $stats['invoices']['legacy'], $stats['invoices']['created'], $stats['invoices']['skipped']],
            ['المصاريف', $stats['expenses']['legacy'], $stats['expenses']['created'], $stats['expenses']['skipped']],
        ]);

        foreach ($importer->warnings as $warning) {
            $this->warn('• '.$warning);
        }

        if ($this->option('dry-run')) {
            $this->info('تجربة فقط ✅ — لم يُحفظ شيء. أعد التشغيل بدون --dry-run للاستيراد الفعلي.');

            return self::SUCCESS;
        }

        $report = $this->reconciliationReport($importer, $legacy, $centerId, $cutoff);

        $this->info('تم الاستيراد ✅ — تقرير المطابقة: '.$report);

        return self::SUCCESS;
    }

    /**
     * The argument is either a connection name from config/database.php or,
     * more conveniently, the name of an attached legacy database applied over
     * the 'legacy' connection template.
     */
    private function resolveLegacyConnection(string $name): ConnectionInterface
    {
        if (config("database.connections.{$name}") === null) {
            config(['database.connections.legacy.database' => $name]);
            DB::purge('legacy');
            $name = 'legacy';
        }

        $connection = DB::connection($name);
        $connection->select('select 1'); // fail here, with a clear message

        return $connection;
    }

    private function reconciliationReport(
        LegacyDatabaseImporter $importer,
        ConnectionInterface $legacy,
        int $centerId,
        ?Carbon $cutoff,
    ): string {
        $rows = $importer->reconcile($legacy, $centerId, $cutoff);

        $labels = [
            'items_count' => 'عدد المواد',
            'stock_units' => 'وحدات المخزون (رصيد افتتاحي)',
            'invoices_count' => 'عدد الفواتير',
            'invoices_total' => 'إجمالي الفواتير (عملة جديدة)',
            'expenses_count' => 'عدد المصاريف',
            'expenses_total' => 'إجمالي المصاريف (عملة جديدة)',
        ];

        $table = [];
        $markdown = "# تقرير مطابقة الاستيراد — مركز {$centerId}\n\n"
            .'التاريخ: '.now()->format('Y-m-d H:i')."\n\n"
            ."| البند | النظام القديم | النظام الجديد | مطابق؟ |\n|---|---|---|---|\n";

        foreach ($rows as $key => $pair) {
            $match = $pair['legacy'] == $pair['imported'] ? '✅' : '❌';
            $table[] = [$labels[$key], $pair['legacy'], $pair['imported'], $match];
            $markdown .= "| {$labels[$key]} | {$pair['legacy']} | {$pair['imported']} | {$match} |\n";
        }

        if ($importer->warnings !== []) {
            $markdown .= "\n## ملاحظات\n";

            foreach ($importer->warnings as $warning) {
                $markdown .= "- {$warning}\n";
            }
        }

        $this->newLine();
        $this->table(['البند', 'النظام القديم', 'النظام الجديد', 'مطابق؟'], $table);

        $path = 'legacy-import/center-'.$centerId.'-'.now()->format('Ymd-His').'.md';
        Storage::disk('local')->put($path, $markdown);

        return Storage::disk('local')->path($path);
    }
}
