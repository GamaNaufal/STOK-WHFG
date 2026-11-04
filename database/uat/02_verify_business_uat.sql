-- READ ONLY: jalankan setelah impor dan setelah UAT. Pilih DB development sendiri.
-- Ringkasan awal di bawah boleh berubah sesudah pengujian.
SELECT DATABASE() database_uji, @@hostname server_uji,
       CONVERT_TZ(UTC_TIMESTAMP(),'+00:00','+07:00') waktu_jakarta;
SELECT 'akun UAT26' objek, COUNT(*) jumlah FROM users WHERE email LIKE 'uat26.%@whfg.test'
UNION ALL SELECT 'master part', COUNT(*) FROM part_settings WHERE part_number LIKE 'UAT26%'
UNION ALL SELECT 'master lokasi', COUNT(*) FROM master_locations WHERE code LIKE 'UAT26%'
UNION ALL SELECT 'box lokal termasuk histori', COUNT(*) FROM boxes WHERE part_number LIKE 'UAT26%'
UNION ALL SELECT 'box ekspor termasuk histori', COUNT(*) FROM export_stock_boxes WHERE part_number LIKE 'UAT26%'
UNION ALL SELECT 'delivery lokal termasuk histori', COUNT(*) FROM delivery_orders WHERE customer_name LIKE 'UAT26%'
UNION ALL SELECT 'delivery ekspor termasuk histori', COUNT(*) FROM export_delivery_plans WHERE plan_code LIKE 'UAT26%';
-- Baseline: akun9, part34, lokasi100, box lokal37, box ekspor127,
-- delivery lokal12, delivery ekspor17. Sebagian sengaja arsip/expired.
SELECT email, role, is_active FROM users WHERE email LIKE 'uat26.%@whfg.test' ORDER BY role,email;
SELECT part_number,qty_box FROM part_settings WHERE part_number LIKE 'UAT26%' ORDER BY part_number;
SELECT d.plan_code,d.customer_name,d.delivery_deadline,d.total_pcs,d.total_boxes,d.status,d.deleted_at,
       p.status pallet_plan_status,p.revision,
       (SELECT COUNT(*) FROM export_fulfillment_boxes f JOIN export_planned_pallet_items z ON z.id=f.export_planned_pallet_item_id JOIN export_planned_pallets y ON y.id=z.export_planned_pallet_id WHERE y.export_pallet_plan_id=p.id) reserved_boxes,
       (SELECT COUNT(*) FROM export_fulfillment_boxes f JOIN export_planned_pallet_items z ON z.id=f.export_planned_pallet_item_id JOIN export_planned_pallets y ON y.id=z.export_planned_pallet_id WHERE y.export_pallet_plan_id=p.id AND f.placed_at IS NOT NULL) placed_boxes
FROM export_delivery_plans d LEFT JOIN export_pallet_plans p ON p.export_delivery_plan_id=d.id
WHERE d.plan_code LIKE 'UAT26%' ORDER BY d.id;
SELECT d.customer_name,d.delivery_date,d.status,i.part_number,i.quantity,i.fulfilled_quantity
FROM delivery_orders d JOIN delivery_order_items i ON i.delivery_order_id=d.id
WHERE d.customer_name LIKE 'UAT26%' ORDER BY d.id;
SELECT b.box_number,b.part_number,b.pcs_quantity,b.first_received_at,b.state,b.is_not_full,b.origin,
       p.location_code,f.placed_at,f.picking_required,n.acknowledged_at
FROM export_stock_boxes b LEFT JOIN export_stock_pallets p ON p.id=b.current_pallet_id
LEFT JOIN export_fulfillment_boxes f ON f.export_stock_box_id=b.id
LEFT JOIN export_stock_allocation_notices n ON n.id=f.allocation_notice_id
WHERE b.part_number LIKE 'UAT26%' ORDER BY b.part_number,b.first_received_at,b.id;
SELECT r.id,d.plan_code,r.part_number,r.pcs_quantity,r.status,r.revision
FROM export_not_full_requests r JOIN export_delivery_plans d ON d.id=r.export_delivery_plan_id
WHERE d.plan_code LIKE 'UAT26%' ORDER BY r.id;

-- INTEGRITAS: setiap SELECT berikut seharusnya mengembalikan 0 baris.
SELECT 'I01 identitas lokal hilang' masalah,b.id,b.box_number
FROM boxes b LEFT JOIN box_identities i ON i.box_number=b.box_number
WHERE b.part_number LIKE 'UAT26%' AND i.box_number IS NULL;

SELECT 'I02 satu box lokal beberapa pallet aktif' masalah,b.box_number,COUNT(*) jumlah
FROM pallet_boxes pb JOIN boxes b ON b.id=pb.box_id JOIN pallets p ON p.id=pb.pallet_id
WHERE b.part_number LIKE 'UAT26%' AND b.deleted_at IS NULL AND b.is_withdrawn=0
AND (b.expired_status IS NULL OR b.expired_status NOT IN ('expired','handled')) AND p.deleted_at IS NULL
GROUP BY b.id,b.box_number HAVING COUNT(*)>1;

SELECT 'I03 ringkasan pallet lokal tidak cocok' masalah,p.pallet_number,i.part_number,
       i.box_quantity ringkasan_box,COALESCE(x.n,0) box_fisik,i.pcs_quantity ringkasan_pcs,COALESCE(x.pcs,0) pcs_fisik
FROM pallet_items i JOIN pallets p ON p.id=i.pallet_id
LEFT JOIN (SELECT pb.pallet_id,b.part_number,COUNT(*) n,SUM(b.pcs_quantity) pcs
    FROM pallet_boxes pb JOIN boxes b ON b.id=pb.box_id
    WHERE b.deleted_at IS NULL AND b.is_withdrawn=0
    AND (b.expired_status IS NULL OR b.expired_status NOT IN ('expired','handled')) GROUP BY pb.pallet_id,b.part_number) x
ON x.pallet_id=i.pallet_id AND x.part_number=i.part_number
WHERE p.pallet_number LIKE 'UAT26%' AND p.deleted_at IS NULL
AND (i.box_quantity<>COALESCE(x.n,0) OR i.pcs_quantity<>COALESCE(x.pcs,0));

SELECT 'I04 box lokal usable tanpa posisi aktif' masalah,b.id,b.box_number
FROM boxes b LEFT JOIN pallet_boxes x ON x.box_id=b.id
LEFT JOIN pallets p ON p.id=x.pallet_id AND p.deleted_at IS NULL
LEFT JOIN stock_locations s ON s.pallet_id=p.id
WHERE b.part_number LIKE 'UAT26%' AND b.deleted_at IS NULL AND b.is_withdrawn=0
AND (b.expired_status IS NULL OR b.expired_status NOT IN ('expired','handled'))
GROUP BY b.id,b.box_number HAVING SUM(CASE WHEN p.id IS NOT NULL AND s.id IS NOT NULL THEN 1 ELSE 0 END)=0;

SELECT 'I05 box ekspor stored tanpa pallet/lokasi aktif' masalah,b.id,b.box_number
FROM export_stock_boxes b LEFT JOIN export_stock_pallets p ON p.id=b.current_pallet_id
LEFT JOIN master_locations l ON l.id=p.master_location_id
WHERE b.part_number LIKE 'UAT26%' AND b.deleted_at IS NULL AND b.state='stored'
AND (p.id IS NULL OR p.retired_at IS NOT NULL OR l.id IS NULL OR l.scope<>'export' OR l.is_occupied<>1);

SELECT 'I06 dua pallet ekspor aktif pada satu lokasi' masalah,p.master_location_id,COUNT(*) jumlah
FROM export_stock_pallets p JOIN master_locations l ON l.id=p.master_location_id
WHERE l.code LIKE 'UAT26%' AND p.retired_at IS NULL
GROUP BY p.master_location_id HAVING COUNT(*)>1;

SELECT 'I07 satu box direservasi lebih sekali' masalah,f.export_stock_box_id,COUNT(*) jumlah
FROM export_fulfillment_boxes f JOIN export_stock_boxes b ON b.id=f.export_stock_box_id
WHERE b.part_number LIKE 'UAT26%' GROUP BY f.export_stock_box_id HAVING COUNT(*)>1;

SELECT 'I08 part/PCS reservasi tidak cocok' masalah,b.box_number,b.pcs_quantity,i.pcs_per_box
FROM export_fulfillment_boxes f JOIN export_stock_boxes b ON b.id=f.export_stock_box_id
JOIN export_planned_pallet_items i ON i.id=f.export_planned_pallet_item_id
WHERE b.part_number LIKE 'UAT26%'
AND (b.part_setting_id<>i.part_setting_id OR f.part_setting_id<>i.part_setting_id
     OR b.pcs_quantity<>i.pcs_per_box OR f.pcs_quantity<>i.pcs_per_box OR b.box_number<>f.box_number);

SELECT 'I09 penempatan pemenuhan tidak sesuai fisik' masalah,b.box_number
FROM export_fulfillment_boxes f JOIN export_stock_boxes b ON b.id=f.export_stock_box_id
JOIN export_planned_pallet_items z ON z.id=f.export_planned_pallet_item_id
JOIN export_planned_pallets p ON p.id=z.export_planned_pallet_id
LEFT JOIN export_stock_pallets s ON s.id=b.current_pallet_id
WHERE b.part_number LIKE 'UAT26%' AND b.deleted_at IS NULL AND f.placed_at IS NOT NULL
AND (f.placed_by IS NULL OR f.placed_location_id IS NULL OR f.placed_location_id<>p.master_location_id
     OR s.id IS NULL OR s.export_planned_pallet_id IS NULL OR s.export_planned_pallet_id<>p.id
     OR s.master_location_id<>p.master_location_id OR b.state<>'stored');

SELECT 'I10 alokasi final tidak tepat' masalah,d.plan_code,i.part_number,i.pcs_per_box,i.box_quantity,
       COALESCE(a.n,0) dialokasikan
FROM export_delivery_plan_items i JOIN export_delivery_plans d ON d.id=i.export_delivery_plan_id
JOIN export_pallet_plans p ON p.export_delivery_plan_id=d.id
LEFT JOIN (SELECT y.export_pallet_plan_id,x.part_setting_id,
    SUM(box_quantity) n,SUM(box_quantity*pcs_per_box) pcs
    FROM export_planned_pallet_items x JOIN export_planned_pallets y ON y.id=x.export_planned_pallet_id
    GROUP BY y.export_pallet_plan_id,x.part_setting_id) a ON a.export_pallet_plan_id=p.id AND a.part_setting_id=i.part_setting_id
WHERE d.plan_code LIKE 'UAT26%' AND d.deleted_at IS NULL AND p.status='final'
AND (COALESCE(a.n,0)<>i.box_quantity OR COALESCE(a.pcs,0)<>i.pcs_quantity);

SELECT 'I11 stok PCS tidak positif' masalah,'domestic' scope,b.box_number,b.pcs_quantity
FROM boxes b WHERE b.part_number LIKE 'UAT26%' AND b.deleted_at IS NULL AND b.pcs_quantity<1
UNION ALL SELECT 'I11 stok PCS tidak positif','export',b.box_number,b.pcs_quantity
FROM export_stock_boxes b WHERE b.part_number LIKE 'UAT26%' AND b.deleted_at IS NULL AND b.pcs_quantity<1;

SELECT 'I12 master bersama/lokasi ekspor salah' masalah,b.box_number
FROM export_stock_boxes b JOIN part_settings s ON s.id=b.part_setting_id
LEFT JOIN export_stock_pallets p ON p.id=b.current_pallet_id LEFT JOIN master_locations l ON l.id=p.master_location_id
WHERE b.part_number LIKE 'UAT26%' AND (s.id IS NULL OR (l.id IS NOT NULL AND l.scope<>'export'));

-- Dijalankan segera setelah NF04 sebelum rencana/receipt lain part itu:
-- Jumlah PCS harus240, apakah sebelum atau sesudah split; sesudah split fisik4box.
SELECT 'NF-SPLIT kontrol PCS' pemeriksaan,SUM(pcs_quantity) pcs,COUNT(*) box_fisik
FROM export_stock_boxes WHERE part_number='UAT26-E-NF-SPLIT' AND deleted_at IS NULL;

-- Hak akses existing sengaja tidak diubah oleh seed. Tidak ada baris berarti default role.
SELECT * FROM role_feature_access ORDER BY role;

-- Identitas ekspor juga wajib tercatat pada registri global.
SELECT 'I18 identitas ekspor hilang' masalah,b.id,b.box_number
FROM export_stock_boxes b LEFT JOIN box_identities i ON i.box_number=b.box_number
WHERE b.part_number LIKE 'UAT26%' AND i.box_number IS NULL;
SELECT 'I19 ID box lintas lingkup' masalah,b.box_number
FROM boxes b JOIN export_stock_boxes e ON e.box_number=b.box_number;
SELECT 'I20 kode rencana belum diregistrasi' masalah,p.id,p.plan_code
FROM export_delivery_plans p LEFT JOIN export_delivery_plan_codes c ON c.code=p.plan_code AND c.export_delivery_plan_id=p.id
WHERE p.plan_code LIKE 'UAT26%' AND c.code IS NULL;
