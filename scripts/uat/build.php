<?php

// Generates a portable MySQL fixture from a brand-new SQLite database only.
// Never seeds the application's configured MySQL database.
declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../../vendor/autoload.php';
$root = realpath(__DIR__.'/../..');
$scratch = $root.'/storage/framework/testing/uat26';
if (!is_dir($scratch)) mkdir($scratch, 0777, true);
$reuse=in_array('--export-existing',$argv,true);
$sqlite = $reuse ? trim(file_get_contents($scratch.'/last-fixture.txt')) : $scratch.'/fixture-'.bin2hex(random_bytes(5)).'.sqlite';
if (!str_starts_with(str_replace('\\','/',$sqlite),str_replace('\\','/',$root).'/storage/framework/testing/uat26/fixture-')) {
    throw new RuntimeException('Unsafe fixture path.');
}
if (!$reuse) touch($sqlite);
foreach (['APP_ENV'=>'testing', 'DB_CONNECTION'=>'sqlite', 'DB_DATABASE'=>$sqlite,
    'SESSION_DRIVER'=>'array', 'CACHE_STORE'=>'array', 'MAIL_MAILER'=>'array'] as $k=>$v) {
    putenv("$k=$v"); $_ENV[$k]=$v; $_SERVER[$k]=$v;
}
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('testing') || DB::getDriverName() !== 'sqlite'
    || !str_contains(DB::connection()->getDatabaseName(), 'fixture-')) {
    throw new RuntimeException('Builder hanya boleh memakai SQLite fixture baru.');
}
if (!$reuse && DB::select("SELECT name FROM sqlite_master WHERE type='table'") !== []) {
    throw new RuntimeException('Database fixture harus kosong.');
}
if (!$reuse) Artisan::call('migrate', ['--force'=>true]);
require __DIR__.'/FixtureBuilder.php';
require __DIR__.'/SqlExporter.php';
$manifest = $reuse ? json_decode(file_get_contents($root.'/database/uat/manifest.json'),true,512,JSON_THROW_ON_ERROR) : (new UatFixtureBuilder)->build();
$out = $root.'/database/uat';
if (!is_dir($out)) mkdir($out, 0777, true);
$envFile=Dotenv\Dotenv::parse(file_get_contents($root.'/.env'));
if (!in_array($envFile['DB_HOST'] ?? '',['127.0.0.1','localhost','::1'],true)) throw new RuntimeException('Baca metadata hanya di MySQL lokal.');
$read=new PDO('mysql:host='.$envFile['DB_HOST'].';port='.($envFile['DB_PORT'] ?? 3306).';dbname='.$envFile['DB_DATABASE'],
    $envFile['DB_USERNAME'],$envFile['DB_PASSWORD'] ?? '',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$host=(string)$read->query('SELECT @@hostname')->fetchColumn();
(new UatSqlExporter)->write($out.'/01_seed_business_uat.sql',$host);
$manifest['masters']['parts']=DB::table('part_settings')->orderBy('part_number')->get(['part_number','qty_box'])->all();
$manifest['masters']['locations']=DB::table('master_locations')->orderBy('scope')->orderBy('code')->get(['scope','code','is_occupied'])->all();
foreach ($manifest['export']['plans'] as &$entry) {
    $row=DB::table('export_delivery_plans')->where('plan_code',$entry['code'])->first();
    $entry['current_status']=$row->status; $entry['archived']=$row->deleted_at!==null;
}
unset($entry);
foreach ($manifest['domestic']['orders'] as &$entry) {
    $entry['current_status']=DB::table('delivery_orders')->where('customer_name',$entry['customer'])->value('status');
}
unset($entry);
file_put_contents($out.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
// Local validation uses this schema/fixture, never the application's stock DB.
file_put_contents($scratch.'/last-fixture.txt', $sqlite);
echo json_encode(['sqlite'=>$sqlite, 'rows'=>array_sum(array_map(fn ($t)=>DB::table($t)->count(), UatSqlExporter::tables())),
    'users'=>DB::table('users')->count(), 'domestic_boxes'=>DB::table('boxes')->count(),
    'export_boxes'=>DB::table('export_stock_boxes')->count(), 'export_plans'=>DB::table('export_delivery_plans')->count(),
    'output'=>$out], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
