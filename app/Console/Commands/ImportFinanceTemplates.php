<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Center;
use App\Services\FinanceTemplateImporter;
use Illuminate\Console\Command;

class ImportFinanceTemplates extends Command
{
    protected $signature = 'finance:import
        {file : مسار ملف الاكسل المعبأ}
        {--center= : رقم المركز (id)}
        {--user= : رقم المستخدم الذي يُنسب له الإدخال (اختياري)}
        {--dry-run : فحص الملف فقط بدون حفظ}
        {--include-locked : استيراد الأسطر الواقعة ضمن أشهر مقفلة (تجاوز قفل الشهر)}';

    protected $description = 'استيراد نماذج الاكسل المالية (مواد / مصاريف / فواتير سابقة)';

    public function handle(FinanceTemplateImporter $importer): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->error("الملف غير موجود: {$file}");

            return self::FAILURE;
        }

        $centerId = (int) $this->option('center');

        if ($centerId < 1 || Center::query()->whereKey($centerId)->doesntExist()) {
            $this->error('حدد مركزاً صحيحاً: --center=رقم');
            $this->table(['id', 'المركز'], Center::query()->get()->map(
                fn (Center $c) => [$c->id, $c->name]
            )->all());

            return self::FAILURE;
        }

        $userId = $this->option('user') !== null ? (int) $this->option('user') : null;
        $dryRun = (bool) $this->option('dry-run');
        $includeLocked = (bool) $this->option('include-locked');

        try {
            $result = $importer->import($file, $centerId, $userId, $dryRun, $includeLocked);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($importer->errors !== []) {
            $this->error('لم يُحفظ أي شيء — صحّح هذه الأسطر ثم أعد المحاولة:');

            foreach ($importer->errors as $error) {
                $this->line('  • '.$error);
            }

            return self::FAILURE;
        }

        $label = match ($result['type']) {
            'inventory' => 'مادة',
            'expenses' => 'مصروف',
            'invoices' => 'فاتورة',
        };

        $this->info($dryRun
            ? "فحص ناجح ✅ — {$result['created']} {$label} جاهزة للاستيراد (لم يُحفظ شيء بعد)."
            : "تم ✅ — استُورد {$result['created']} {$label}.");

        return self::SUCCESS;
    }
}
