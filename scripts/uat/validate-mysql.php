<?php

// Creates/drops only whfg_uat_verify_* on the local MySQL server.
// The configured application database is read for schema only.
declare(strict_types=1);
require __DIR__.'/../../vendor/autoload.php';
$root=realpath(__DIR__.'/../..');
$env=Dotenv\Dotenv::parse(file_get_contents($root.'/.env'));
$host=$env['DB_HOST'] ?? '127.0.0.1';
$source=$env['DB_DATABASE'] ?? '';
if (!in_array($host,['127.0.0.1','localhost','::1'],true) || !preg_match('/^[a-zA-Z0-9_]+$/D',$source)) {
    throw new RuntimeException('Validator hanya mendukung MySQL lokal dengan nama database sederhana.');
}
$dsn='mysql:host='.$host.';port='.($env['DB_PORT'] ?? '3306').';charset=utf8mb4';
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$read=new PDO($dsn.';dbname='.$source,$env['DB_USERNAME'],$env['DB_PASSWORD'] ?? '',$options);
$server=new PDO($dsn,$env['DB_USERNAME'],$env['DB_PASSWORD'] ?? '',$options);
$name='whfg_uat_verify_'.bin2hex(random_bytes(5));
if ($name===$source || !preg_match('/^whfg_uat_verify_[a-f0-9]{10}$/D',$name)) throw new RuntimeException('Unsafe test DB.');
$report=['database'=>$name,'source_schema_only'=>$source,'server_version'=>$read->query('SELECT VERSION()')->fetchColumn()];
$server->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    $pdo=new PDO($dsn.';dbname='.$name,$env['DB_USERNAME'],$env['DB_PASSWORD'] ?? '',$options);
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $tables=$read->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $ddl=$read->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
        $pdo->exec($ddl);
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $pdo->exec('INSERT INTO export_inventory_locks (id) VALUES (1)');
    $pdo->exec('INSERT INTO pallet_number_sequences (id,next_number) VALUES (1,1)');
    $pdo->exec("INSERT INTO operation_locks (id,name) VALUES (1,'global_delivery_pick')");
    // Deliberately different target IDs: catches missing FK/JSON remapping.
    foreach ($tables as $i=>$table) {
        if (preg_match('/AUTO_INCREMENT/',$read->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1])) {
            $pdo->exec('ALTER TABLE `'.$table.'` AUTO_INCREMENT='.(1000+$i*100));
        }
    }
    $pdo->exec("INSERT INTO users (name,email,password,role,is_active) VALUES ('Existing dev user','existing@example.invalid','untouched','admin',1)");
    $pdo->exec("INSERT INTO part_settings (part_number,qty_box) VALUES ('EXISTING-DEV',7)");
    $pdo->exec("INSERT INTO master_locations (scope,code,is_occupied) VALUES ('domestic','EXISTING-DEV',0)");
    $sql=file_get_contents($root.'/database/uat/01_seed_business_uat.sql');
    $sql=str_replace("SET @uat_expected_database = 'stock';","SET @uat_expected_database = '$name';",$sql);
    $execute=function (string $script) use ($pdo): void {
        $delimiter=';'; $buffer='';
        foreach (explode("\n",$script) as $line) {
            if (preg_match('/^DELIMITER\s+(\S+)/',trim($line),$m)) { $delimiter=$m[1]; continue; }
            if (str_starts_with(ltrim($line),'--') || trim($line)==='') continue;
            $buffer.=$line."\n";
            if (str_ends_with(rtrim($buffer),$delimiter)) {
                $statement=substr(rtrim($buffer),0,-strlen($delimiter));
                $result=$pdo->query($statement);
                do { if ($result->columnCount()) $result->fetchAll(); } while ($result->nextRowset());
                $result->closeCursor(); $buffer='';
            }
        }
        if (trim($buffer)!=='') throw new RuntimeException('SQL buffer not empty.');
    };
    $counts=function () use ($pdo,$tables): array {
        $r=[]; foreach ($tables as $t) $r[$t]=(int)$pdo->query('SELECT COUNT(*) FROM `'.$t.'`')->fetchColumn(); return $r;
    };
    $before=$counts();
    // Force failure after the first INSERT; every inserted fixture row must roll back.
    $needle='  INSERT INTO'; $at=strpos($sql,$needle); $at=strpos($sql,$needle,$at+strlen($needle));
    $broken=substr_replace($sql,"  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='UAT26 rollback probe';\n",$at,0);
    try { $execute($broken); throw new RuntimeException('Rollback probe unexpectedly succeeded.'); }
    catch (PDOException $e) { if (!str_contains($e->getMessage(),'rollback probe')) throw $e; }
    if ($counts()!==$before) throw new RuntimeException('Partial fixture survived failed import.');
    $report['rollback_after_mid_import_failure']='pass';
    $execute($sql);
    $baseline=$counts();
    try { $execute($sql); throw new RuntimeException('Duplicate import unexpectedly succeeded.'); }
    catch (PDOException $e) { if (!str_contains($e->getMessage(),'namespace/ID')) throw $e; }
    if ($counts()!==$baseline) throw new RuntimeException('Duplicate import mutated rows.');
    $report['repeat_import_rejected_without_changes']='pass';
    $assert=function (string $label,string $query,int $want=0) use ($pdo,&$report): void {
        $actual=(int)$pdo->query($query)->fetchColumn();
        if ($actual!==$want) throw new RuntimeException("$label: expected $want, got $actual");
        $report[$label]='pass';
    };
    $assert('existing_user_unchanged',"SELECT COUNT(*) FROM users WHERE email='existing@example.invalid' AND password='untouched'",1);
    $assert('domestic_identity_registry',"SELECT COUNT(*) FROM boxes b LEFT JOIN box_identities i ON i.box_number=b.box_number WHERE b.part_number LIKE 'UAT26%' AND i.box_number IS NULL");
    $assert('domestic_pallet_summaries',"SELECT COUNT(*) FROM pallet_items i JOIN pallets p ON p.id=i.pallet_id LEFT JOIN (SELECT pb.pallet_id,b.part_number,COUNT(*) n,SUM(b.pcs_quantity) pcs FROM pallet_boxes pb JOIN boxes b ON b.id=pb.box_id WHERE b.deleted_at IS NULL AND b.is_withdrawn=0 AND b.expired_status IN ('active','warning') GROUP BY pb.pallet_id,b.part_number) x ON x.pallet_id=i.pallet_id AND x.part_number=i.part_number WHERE p.pallet_number LIKE 'UAT26%' AND (i.box_quantity<>COALESCE(x.n,0) OR i.pcs_quantity<>COALESCE(x.pcs,0))");
    $assert('reserved_exact_capacity',"SELECT COUNT(*) FROM export_fulfillment_boxes f JOIN export_stock_boxes b ON b.id=f.export_stock_box_id JOIN export_planned_pallet_items i ON i.id=f.export_planned_pallet_item_id WHERE b.part_number LIKE 'UAT26%' AND (f.pcs_quantity<>i.pcs_per_box OR b.pcs_quantity<>i.pcs_per_box OR b.part_setting_id<>i.part_setting_id)");
    $assert('no_duplicate_active_reservations',"SELECT COUNT(*) FROM (SELECT export_stock_box_id FROM export_fulfillment_boxes WHERE export_stock_box_id IS NOT NULL GROUP BY export_stock_box_id HAVING COUNT(*)>1) x");
    $assert('not_full_waiting',"SELECT COUNT(*) FROM export_not_full_requests r JOIN export_delivery_plans d ON d.id=r.export_delivery_plan_id WHERE d.plan_code IN ('UAT26-EX-NF-EXT','UAT26-EX-NF-DRAFT','UAT26-EX-NF-SPLIT','UAT26-EX-NF-SHORT','UAT26-EX-NF-EXIST') AND r.pcs_quantity=20 AND r.status='waiting'",5);
    $assert('not_full_existing_twenty',"SELECT COUNT(*) FROM export_stock_boxes WHERE box_number='98002401' AND pcs_quantity=20 AND state='stored' AND deleted_at IS NULL",1);
    $assert('archived_export_history',"SELECT COUNT(*) FROM export_stock_boxes WHERE box_number IN ('98002901','98002902','98002903','98002904') AND deleted_at IS NOT NULL",4);
    $assert('mixed_pallet_source',"SELECT COUNT(DISTINCT part_number) FROM export_stock_boxes WHERE box_number IN ('98002101','98002102','98002103')",2);
    $assert('domestic_pending_nf',"SELECT COUNT(*) FROM not_full_box_requests WHERE box_number IN ('98010071','98010073') AND status='pending'",2);
    $hash=$pdo->query("SELECT password FROM users WHERE email='uat26.admin@whfg.test'")->fetchColumn();
    if (!password_verify('UatWHFG!2026',$hash)) throw new RuntimeException('Login hash invalid.');
    $report['password_hash']='pass';
    $verification=preg_replace('/^\s*--.*$/m','',file_get_contents($root.'/database/uat/02_verify_business_uat.sql'));
    $selects=0;
    foreach (explode(';',$verification) as $query) {
        if (trim($query)==='') continue;
        if (!preg_match('/^\s*SELECT\b/i',$query)) throw new RuntimeException('Verification must remain read only.');
        $rows=$pdo->query($query)->fetchAll(PDO::FETCH_NUM); $selects++;
        if ($rows && preg_match('/^I\d{2} /',(string)$rows[0][0])) throw new RuntimeException('Integrity query failed: '.json_encode($rows));
    }
    $report['read_only_verification_queries']=$selects;
    require __DIR__.'/RuntimeChecks.php';
    $report['imported_fixture_service_checks']=uatRuntimeChecks($root,$name);
    $report['baseline_counts']=array_filter($baseline,fn ($n)=>$n>0);
    $report['fixture_only_counts']=[
        'users'=>(int)$pdo->query("SELECT COUNT(*) FROM users WHERE email LIKE 'uat26.%@whfg.test'")->fetchColumn(),
        'parts'=>(int)$pdo->query("SELECT COUNT(*) FROM part_settings WHERE part_number LIKE 'UAT26%'")->fetchColumn(),
        'locations'=>(int)$pdo->query("SELECT COUNT(*) FROM master_locations WHERE code LIKE 'UAT26%'")->fetchColumn(),
        'domestic_boxes'=>(int)$pdo->query("SELECT COUNT(*) FROM boxes WHERE part_number LIKE 'UAT26%'")->fetchColumn(),
        'export_boxes'=>(int)$pdo->query("SELECT COUNT(*) FROM export_stock_boxes WHERE part_number LIKE 'UAT26%'")->fetchColumn(),
    ];
    $report['validated_at']=date(DATE_ATOM);
    $report['application_database_writes']=0;
    file_put_contents($root.'/database/uat/validation.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
} finally {
    if (preg_match('/^whfg_uat_verify_[a-f0-9]{10}$/D',$name) && $name!==$source) $server->exec('DROP DATABASE `'.$name.'`');
}
