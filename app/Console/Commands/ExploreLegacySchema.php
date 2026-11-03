<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExploreLegacySchema extends Command
{
    protected $signature = 'finance:legacy-schema
        {connection : اسم الاتصال، أو اسم قاعدة البيانات القديمة الملحقة بـ SQL Server}
        {--rows=3 : عدد الأسطر النموذجية من كل جدول}';

    protected $description = 'استكشاف جداول وأعمدة قاعدة البيانات القديمة لبناء الـ mapping في config/legacy-finance.php';

    public function handle(): int
    {
        $name = (string) $this->argument('connection');

        if (config("database.connections.{$name}") === null) {
            config(['database.connections.legacy.database' => $name]);
            DB::purge('legacy');
            $name = 'legacy';
        }

        try {
            $connection = DB::connection($name);
            $tables = $connection->getSchemaBuilder()->getTables();
        } catch (\Throwable $e) {
            $this->error('تعذر الاتصال: '.$e->getMessage());

            return self::FAILURE;
        }

        $sampleRows = max(0, (int) $this->option('rows'));
        $markdown = '# بنية قاعدة البيانات القديمة — '.$this->argument('connection')."\n\n"
            .'التاريخ: '.now()->format('Y-m-d H:i')."\n";

        foreach ($tables as $table) {
            $tableName = $table['name'];
            $count = $connection->table($tableName)->count();
            $columns = $connection->getSchemaBuilder()->getColumns($tableName);

            $this->info("▸ {$tableName} ({$count} سطر)");
            $markdown .= "\n## {$tableName} — {$count} سطر\n\n| العمود | النوع |\n|---|---|\n";

            foreach ($columns as $column) {
                $this->line("    {$column['name']} : {$column['type']}");
                $markdown .= "| {$column['name']} | {$column['type']} |\n";
            }

            if ($sampleRows > 0 && $count > 0) {
                $markdown .= "\n```\n";

                foreach ($connection->table($tableName)->limit($sampleRows)->get() as $row) {
                    $markdown .= json_encode($row, JSON_UNESCAPED_UNICODE)."\n";
                }

                $markdown .= "```\n";
            }
        }

        $path = 'legacy-import/schema-'.$this->argument('connection').'-'.now()->format('Ymd-His').'.md';
        Storage::disk('local')->put($path, $markdown);

        $this->newLine();
        $this->info('التقرير الكامل (مع أسطر نموذجية): '.Storage::disk('local')->path($path));
        $this->line('بعد المراجعة: صحّح أسماء الجداول/الأعمدة في config/legacy-finance.php ثم شغّل finance:import-legacy --dry-run.');

        return self::SUCCESS;
    }
}
