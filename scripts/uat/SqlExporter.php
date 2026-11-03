<?php

declare(strict_types=1);

use Illuminate\Support\Facades\{DB, Schema};

final class UatSqlExporter
{
    private array $rows=[];
    private array $foreign=[];
    private array $deferred=[];

    public static function tables(): array
    {
        return array_values(array_filter(Schema::getTableListing(schemaQualified: false), fn ($t)=>!in_array($t,
            ['migrations','sqlite_sequence','cache','cache_locks','sessions','jobs','job_batches','failed_jobs',
                'password_reset_tokens','pallet_number_sequences','export_inventory_locks','operation_locks','role_feature_access'],true)));
    }

    private function offset(string $table): string { return '@uat_id_'.$table; }
    private function literal(string $s): string { return $s==='' ? "''" : "CONVERT(0x".bin2hex($s).' USING utf8mb4)'; }
    private function ref(string $table, mixed $id): string
    {
        if (!$id) return 'NULL';
        if (!isset($this->rows[$table][(int)$id])) return 'NULL';
        return '('.$this->offset($table).' + '.(int)$id.')';
    }
    private function modelTable(?string $model): ?string
    {
        $class='App\\Models\\'.class_basename($model ?? '');
        return class_exists($class) ? (new $class)->getTable() : null;
    }

    private function json(mixed $value, ?string $context=null, ?string $key=null): string
    {
        $map=['user_id'=>'users','created_by'=>'users','updated_by'=>'users','received_by'=>'users','stored_by'=>'users',
            'placed_by'=>'users','approved_by'=>'users','prepared_by'=>'users','ready_by'=>'users','finalized_by'=>'users',
            'handled_by'=>'users','pallet_id'=>'pallets','box_id'=>'boxes','source_box_id'=>'export_stock_boxes',
            'replacement_box_id'=>'boxes','delivery_order_id'=>'delivery_orders','assigned_delivery_order_id'=>'delivery_orders',
            'pick_session_id'=>'delivery_pick_sessions','part_setting_id'=>'part_settings','master_location_id'=>'master_locations',
            'location_id'=>'master_locations','placed_location_id'=>'master_locations','target_location_id'=>'master_locations',
            'source_location_id'=>'master_locations','export_delivery_plan_id'=>'export_delivery_plans',
            'export_pallet_plan_id'=>'export_pallet_plans','export_planned_pallet_id'=>'export_planned_pallets',
            'export_planned_pallet_item_id'=>'export_planned_pallet_items','export_stock_box_id'=>'export_stock_boxes',
            'export_stock_pallet_id'=>'export_stock_pallets','allocation_notice_id'=>'export_stock_allocation_notices'];
        $map+=['stock_input_id'=>'stock_inputs','pallet_item_id'=>'pallet_items','handling_id'=>'expired_box_handlings',
            'transaction_id'=>'expired_box_transactions','planned_item_id'=>'export_planned_pallet_items',
            'plan_id'=>'export_pallet_plans'];
        if ($value===null) return 'NULL';
        if (is_bool($value)) return "JSON_EXTRACT('".($value ? 'true' : 'false')."','$')";
        if (is_int($value) || is_float($value)) {
            if ($key==='source_box_id' && ($context==='boxes' || str_starts_with($context ?? '', 'expired_'))) return $this->ref('boxes',$value);
            if ($key==='id' && $context) return $this->ref($context,$value);
            return isset($map[$key ?? '']) ? $this->ref($map[$key],$value) : (string)$value;
        }
        if (is_string($value)) return $this->value($value);
        if (is_object($value) && (array)$value===[]) return 'JSON_OBJECT()';
        if (is_object($value)) $value=(array)$value;
        if (array_is_list($value)) return 'JSON_ARRAY('.implode(',',array_map(fn ($v)=>$this->json($v,$context),$value)).')';
        $export=str_starts_with($context ?? '', 'export_');
        $children=['pallets'=>$export ? 'export_planned_pallets' : 'pallets',
            'items'=>$export ? 'export_planned_pallet_items' : ($context==='delivery_orders' ? 'delivery_order_items' : 'pallet_items'),
            'boxes'=>$export ? 'export_stock_boxes' : 'boxes','pallet_boxes'=>'pallet_boxes',
            'stockLocation'=>'stock_locations','last_expired_report'=>'expired_box_reports',
            'stock_box'=>'export_stock_boxes','stockBox'=>'export_stock_boxes'];
        $parts=[];
        foreach ($value as $k=>$v) {
            $parts[]=$this->literal((string)$k);
            $parts[]=$this->json($v,$children[$k] ?? $context,(string)$k);
        }
        return 'JSON_OBJECT('.implode(',',$parts).')';
    }

    private function value(mixed $v): string
    {
        if ($v===null) return 'NULL';
        if (is_int($v) || is_float($v)) return (string)$v;
        if (preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?Z?)?$/D',(string)$v)) {
            $base=new DateTimeImmutable('2026-10-31 10:00:00',new DateTimeZone('Asia/Jakarta'));
            if (strlen($v)===10) {
                $days=(int)$base->setTime(0,0)->diff(new DateTimeImmutable($v,new DateTimeZone('Asia/Jakarta')))->format('%r%a');
                return 'DATE(DATE_ADD(@uat_base_at, INTERVAL '.$days.' DAY))';
            }
            $date=new DateTimeImmutable($v,new DateTimeZone('Asia/Jakarta'));
            $delta=$date->getTimestamp()-$base->getTimestamp();
            $expr='DATE_ADD(@uat_base_at, INTERVAL '.$delta.' SECOND)';
            return str_contains($v,'T') ? "DATE_FORMAT(CONVERT_TZ($expr,'+07:00','+00:00'),'%Y-%m-%dT%H:%i:%s.000000Z')" : $expr;
        }
        if (preg_match('/^EXP-(\d{8})-(\d{6})$/D',(string)$v,$m)) {
            $date=DateTimeImmutable::createFromFormat('!Ymd',$m[1]);
            $base=new DateTimeImmutable('2026-10-31');
            $days=(int)$base->diff($date)->format('%r%a');
            return "CONCAT('EXP-',DATE_FORMAT(DATE_ADD(@uat_base_at,INTERVAL $days DAY),'%Y%m%d'),'-',LPAD("
                .$this->ref('expired_box_transactions',(int)$m[2]).",6,'0'))";
        }
        $decoded=json_decode((string)$v);
        if ((str_starts_with((string)$v,'{') || str_starts_with((string)$v,'[')) && json_last_error()===JSON_ERROR_NONE) {
            return $this->json($decoded);
        }
        return $this->literal((string)$v);
    }

    public function write(string $path, string $host): void
    {
        foreach (self::tables() as $t) {
            $rows=DB::table($t)->get()->map(fn ($r)=>(array)$r)->all();
            if (!$rows) continue;
            $this->rows[$t]=array_column($rows,null,array_key_exists('id',$rows[0])?'id':($t==='export_delivery_plan_codes'?'code':'box_number'));
            foreach (Schema::getForeignKeys($t) as $fk) {
                if (count($fk['columns'])!==1) throw new RuntimeException('Composite FK perlu serializer khusus: '.$t);
                $this->foreign[$t][$fk['columns'][0]]=$fk['foreign_table'];
            }
            if ($t==='export_delivery_plan_codes') $this->foreign[$t]['export_delivery_plan_id']='export_delivery_plans';
            // These logical references deliberately have no FK so QA history survives deletion.
            if ($t==='expired_box_handlings') {
                $this->foreign[$t]['source_box_id']='boxes';
                $this->foreign[$t]['replacement_box_id']='boxes';
            }
        }
        $tables=array_keys($this->rows); $ordered=[];
        while ($tables) {
            $progress=false;
            foreach ($tables as $i=>$t) {
                $dependencies=array_diff(array_values($this->foreign[$t] ?? []),[$t],$ordered);
                $dependencies=array_intersect($dependencies,array_keys($this->rows));
                // Domestic master locations point back to pallets; defer the nullable edge.
                if ($t==='master_locations') $dependencies=array_diff($dependencies,['pallets']);
                // Physical export pallets/boxes also have nullable planning edges.
                if (!$dependencies) { $ordered[]=$t; unset($tables[$i]); $progress=true; }
            }
            if (!$progress) throw new RuntimeException('Unresolved FK cycle: '.implode(',',$tables));
        }
        $sql=<<<'UAT_SQL_HEADER'
-- STOK WHFG | Paket UAT26 | MySQL 8 | DEVELOPMENT ONLY
-- Jalankan migrasi aplikasi terlebih dahulu. Baca docs/UAT_BUSINESS_GUIDE.md.
-- Tambah data; tidak mengubah user/master/stok existing. Impor ulang ditolak.
-- Jangan jalankan saat operator/scheduler sedang mengubah inventory development.
-- Nama database harus cocok dengan @uat_expected_database di bawah.
SET @uat_expected_database = 'stock';
SET @uat_expected_host = '__UAT_EXPECTED_HOST__';
SET @uat_base_at = CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '+07:00');
DELIMITER $$
DROP PROCEDURE IF EXISTS uat26_load_whfg_20261031$$
CREATE PROCEDURE uat26_load_whfg_20261031()
SQL SECURITY INVOKER
BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;
  IF DATABASE() IS NULL OR DATABASE() <> @uat_expected_database THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='UAT26: database tidak sesuai. Pilih DB development dan periksa header SQL.';
  END IF;
  IF @@hostname <> @uat_expected_host THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='UAT26: server berbeda dari development lokal. Periksa koneksi dan header SQL.';
  END IF;
  IF @@foreign_key_checks <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='UAT26: aktifkan FOREIGN_KEY_CHECKS sebelum impor.';
  END IF;
  START TRANSACTION;
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE' AND engine<>'InnoDB') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='UAT26: membutuhkan tabel InnoDB untuk rollback atomik.';
  END IF;
  IF EXISTS (SELECT 1 FROM users WHERE email LIKE 'uat26.%@whfg.test' OR name LIKE 'UAT26%')
     OR EXISTS (SELECT 1 FROM part_settings WHERE part_number LIKE 'UAT26%')
     OR EXISTS (SELECT 1 FROM master_locations WHERE code LIKE 'UAT26%')
     OR EXISTS (SELECT 1 FROM pallets WHERE pallet_number LIKE 'UAT26%')
     OR EXISTS (SELECT 1 FROM delivery_orders WHERE customer_name LIKE 'UAT26%')
     OR EXISTS (SELECT 1 FROM export_delivery_plans WHERE plan_code LIKE 'UAT26%')
     OR EXISTS (SELECT 1 FROM export_delivery_plan_codes WHERE code LIKE 'UAT26%')
     OR EXISTS (SELECT 1 FROM boxes WHERE box_number REGEXP '^980[01][0-9]{4}$')
     OR EXISTS (SELECT 1 FROM box_identities WHERE box_number REGEXP '^980[01][0-9]{4}$')
     OR EXISTS (SELECT 1 FROM not_full_box_requests WHERE box_number REGEXP '^980[01][0-9]{4}$')
     OR EXISTS (SELECT 1 FROM export_stock_boxes WHERE box_number REGEXP '^980[01][0-9]{4}$')
     OR EXISTS (SELECT 1 FROM export_not_full_requests WHERE JSON_UNQUOTE(JSON_EXTRACT(preparation,'$.box_number')) REGEXP '^980[01][0-9]{4}$') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='UAT26: namespace/ID uji sudah digunakan. Tidak ada fixture yang ditambahkan.';
  END IF;
  IF EXISTS (SELECT 1 FROM delivery_pick_sessions WHERE status IN ('scanning','blocked')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='UAT26: selesaikan/batalkan sesi picking domestik aktif melalui aplikasi dahulu.';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM export_inventory_locks WHERE id=1)
     OR NOT EXISTS (SELECT 1 FROM pallet_number_sequences WHERE id=1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='UAT26: allocator/lock belum tersedia. Jalankan migration terbaru.';
  END IF;
UAT_SQL_HEADER;
        $sql=str_replace('__UAT_EXPECTED_HOST__',str_replace("'","''",$host),$sql);
        foreach ($ordered as $t) {
            if (array_key_exists('id',reset($this->rows[$t]))) {
                // Keep IDs above both live rows and the allocator's historical high-water mark.
                $sql.="\n  SELECT GREATEST(COALESCE((SELECT MAX(id) FROM `$t`),0),"
                    ."COALESCE((SELECT AUTO_INCREMENT-1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='$t'),0))"
                    .' INTO '.$this->offset($t).';';
            }
        }
        foreach ($ordered as $t) {
            $sql.="\n\n  -- $t: ".count($this->rows[$t])." rows\n";
            foreach ($this->rows[$t] as $row) {
                $values=[];
                unset($row['plan_code_key']); // Generated by MySQL, never explicitly inserted.
                foreach ($row as $k=>$v) {
                    if ($k==='id') $values[]=$this->ref($t,$v);
                    elseif ($t==='master_locations' && $k==='current_pallet_id' && $v) {
                        $values[]='NULL';
                        $this->deferred[]='UPDATE `master_locations` SET `current_pallet_id`='.$this->ref('pallets',$v).' WHERE id='.$this->ref($t,$row['id']).';';
                    } elseif ($t==='audit_logs' && $k==='model_id') {
                        $table=$this->modelTable($row['model']); $values[]=$table ? $this->ref($table,$v) : 'NULL';
                    } elseif ($t==='audit_logs' && in_array($k,['old_values','new_values']) && $v!==null) {
                        $values[]=$this->json(json_decode($v),$this->modelTable($row['model']));
                    } elseif ($t==='expired_box_handlings' && $k==='source_snapshot' && $v!==null) {
                        $values[]=$this->json(json_decode($v),'boxes');
                    } elseif (isset($this->foreign[$t][$k])) $values[]=$this->ref($this->foreign[$t][$k],$v);
                    else $values[]=$this->value($v);
                }
                $sql.='  INSERT INTO `'.$t.'` (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',$values).");\n";
            }
        }
        foreach ($this->deferred as $line) $sql.='  '.$line."\n";
        $sql.="  COMMIT;\nEND$$\nCALL uat26_load_whfg_20261031()$$\nDROP PROCEDURE uat26_load_whfg_20261031$$\nDELIMITER ;\n\nSELECT 'UAT26 siap diuji' AS hasil, @uat_base_at AS tanggal_acuan, DATABASE() AS database_uji;\n";
        file_put_contents($path,$sql);
    }
}
