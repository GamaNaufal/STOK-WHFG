<?php

// Invoked via stdin inside the application container. No write commands or data dumps.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$root = getcwd();
if (! is_file($root.'/artisan') || ! is_file($root.'/vendor/autoload.php')) {
    fwrite(STDERR, "STOP: APP_DIR harus menunjuk root Laravel di container.\n");
    exit(1);
}

$connection = null;
$readOnly = false;
try {
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $report = [
        'php' => PHP_VERSION,
        'laravel' => $app->version(),
        'app_env' => config('app.env'),
        'app_debug' => (bool) config('app.debug'),
        'app_key_present' => filled(config('app.key')),
        'timezone' => config('app.timezone'),
        'config_cached' => $app->configurationIsCached(),
        'cache_driver' => config('cache.default'),
        'queue_driver' => config('queue.default'),
        'session_driver' => config('session.driver'),
        'maintenance_driver' => config('app.maintenance.driver'),
        'storage_writable' => is_writable($root.'/storage'),
        'bootstrap_cache_writable' => is_writable($root.'/bootstrap/cache'),
        'public_hot_present' => is_file($root.'/public/hot'),
        'customer_writes_enabled' => config('export_customer_stock.writes_enabled'),
    ];
    $manifest = $root.'/public/build/manifest.json';
    $report['build_manifest_present'] = is_file($manifest);
    $report['build_missing_files'] = [];
    if (is_file($manifest)) {
        $entries = json_decode(file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
        foreach ($entries as $entry) {
            foreach (array_merge([$entry['file'] ?? ''], $entry['css'] ?? []) as $file) {
                if ($file !== '' && ! is_file($root.'/public/build/'.$file)) {
                    $report['build_missing_files'][] = $file;
                }
            }
        }
    }
    $report['build_missing_files'] = array_values(array_unique($report['build_missing_files']));
    $connection = DB::connection();
    $report['db_driver'] = $connection->getDriverName();
    if ($report['db_driver'] !== 'mysql') {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
        fwrite(STDERR, "STOP: ringkasan SQL ini hanya untuk koneksi MySQL/MariaDB.\n");
        exit(1);
    }
    // Enforced by the server, including subsequent schema/count queries.
    $connection->statement('START TRANSACTION READ ONLY');
    $readOnly = true;
    $report['database'] = $connection->selectOne('SELECT VERSION() AS version, DATABASE() AS name, @@collation_database AS collation, @@sql_mode AS sql_mode, @@session.time_zone AS session_timezone');
    $report['database_size'] = $connection->selectOne('SELECT COUNT(*) AS table_count, ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS estimated_mb FROM information_schema.tables WHERE table_schema = DATABASE()');
    $report['table_engines'] = $connection->select('SELECT engine, COUNT(*) AS table_count FROM information_schema.tables WHERE table_schema = DATABASE() GROUP BY engine');
    $report['migrations'] = Schema::hasTable('migrations')
        ? DB::table('migrations')->orderBy('migration')->get(['migration', 'batch'])->all() : [];
    foreach (['users', 'part_settings', 'master_locations', 'pallets', 'boxes', 'stock_inputs', 'stock_withdrawals', 'delivery_orders', 'delivery_pick_sessions', 'not_full_box_requests', 'audit_logs', 'export_stock_boxes', 'export_delivery_plans', 'export_customers'] as $table) {
        $report['row_counts'][$table] = Schema::hasTable($table) ? DB::table($table)->count() : null;
    }
    foreach (['delivery_orders', 'delivery_pick_sessions', 'not_full_box_requests'] as $table) {
        if (Schema::hasTable($table)) {
            $report['statuses'][$table] = DB::table($table)->select('status')->selectRaw('COUNT(*) AS total')->groupBy('status')->get()->all();
        }
    }
    if (Schema::hasColumn('boxes', 'reactivated_until')) {
        $report['reactivation'] = DB::table('boxes')->whereNull('deleted_at')->where('is_withdrawn', false)
            ->selectRaw('COUNT(*) AS stored_boxes')
            ->selectRaw('SUM(CASE WHEN reactivated_until > CURRENT_TIMESTAMP THEN 1 ELSE 0 END) AS future_deadlines')
            ->selectRaw('SUM(CASE WHEN reactivated_until <= CURRENT_TIMESTAMP THEN 1 ELSE 0 END) AS elapsed_deadlines')
            ->get()->first();
        $report['future_deadline_statuses'] = DB::table('boxes')->whereNull('deleted_at')->where('is_withdrawn', false)
            ->where('reactivated_until', '>', now())->select('expired_status')->selectRaw('COUNT(*) AS total')->groupBy('expired_status')->get()->all();
    }
    $report['note'] = 'Read-only observations, not deployment approval, backup verification or a replacement for rehearsal. Counts may change while production is in use.';
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    // Exception messages may contain connection details or SQL values. Do not print them.
    fwrite(STDERR, 'STOP: pemeriksaan gagal ('.get_class($error)."). Minta PIC memeriksa error secara privat; jangan membagikan credential.\n");
    exit(1);
} finally {
    if ($readOnly) {
        $connection->statement('ROLLBACK');
    }
}
