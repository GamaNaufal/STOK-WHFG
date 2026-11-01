"""Package the already validated UAT files; does not connect to a database."""

from pathlib import Path
import hashlib
import json
import re
import zipfile


ROOT = Path(__file__).resolve().parents[2]
GUIDE = ROOT / "docs/UAT_BUSINESS_GUIDE.md"
guide = GUIDE.read_text(encoding="utf-8")
cases = []
for line in guide.splitlines():
    match = re.match(r"^\| ((?:UI|NF|[A-Z])\d{2}) \| (.*?) \| (.*?) \|$", line)
    if match and match[2] != "Belum diuji":
        cases.append((match[1], match[2]))
assert len(cases) == 116, f"Unexpected case count: {len(cases)}"
assert len({case[0] for case in cases}) == len(cases), "Duplicate case IDs"

config = (ROOT / "config/feature_access.php").read_text(encoding="utf-8")
features = re.findall(r"^\s{8}'([a-z_]+)' => \['label'", config, re.M)
readme = (ROOT / "database/uat/README.md").read_text(encoding="utf-8")
assert len(features) == 32, "Feature configuration changed; review coverage"
assert all(feature in readme for feature in features), "Missing feature coverage"

manifest = json.loads((ROOT / "database/uat/manifest.json").read_text(encoding="utf-8"))
assert len(manifest["masters"]["parts"]) == 35
assert len(manifest["masters"]["locations"]) == 100
validation = json.loads((ROOT / "database/uat/validation.json").read_text(encoding="utf-8"))
assert validation["application_database_writes"] == 0
assert validation["read_only_verification_queries"] == 22
assert validation["rollback_after_mid_import_failure"] == "pass"
assert validation["repeat_import_rejected_without_changes"] == "pass"

result = ROOT / "docs/UAT_RESULT_TEMPLATE.md"
header = """# Lembar hasil pengujian UAT26

Isi identitas pengujian dan hasil setiap kasus sesuai `UAT_BUSINESS_GUIDE.md`.
Seluruh baris dimulai sebagai **Belum diuji**; hasil validasi SQL/service tidak
menggantikan pemeriksaan UI oleh penguji.

- Nama database development:
- Tanggal impor / D0:
- Versi aplikasi / commit yang diuji:
- Penguji dan role:
- Browser / perangkat scanner:
- Lokasi screenshot / bukti:

Pilihan hasil: **Lulus / Gagal / Belum diuji / Tidak berlaku**.
Jika Tidak berlaku, tulis alasan. Untuk Gagal, cantumkan hasil aktual, ID/kode
transaksi, pesan error dan bukti. Langkah lengkap dan hasil yang diharapkan
berada pada kasus dengan ID yang sama dalam panduan.

| ID | Ringkasan langkah | Hasil | Penguji / tanggal | ID transaksi / hasil aktual / bukti |
| --- | --- | --- | --- | --- |
"""
rows = [f"| {key} | {step} | Belum diuji | | |" for key, step in cases]
result.write_text(header + "\n".join(rows) + "\n", encoding="utf-8")

files = [
    "database/uat/README.md",
    "database/uat/01_seed_business_uat.sql",
    "database/uat/02_verify_business_uat.sql",
    "database/uat/manifest.json",
    "database/uat/validation.json",
    "docs/UAT_BUSINESS_GUIDE.md",
    "docs/UAT_RESULT_TEMPLATE.md",
    "docs/EXPORT_MANUAL_TEST_GUIDE.md",
]
contents = {name: (ROOT / name).read_bytes() for name in files}
# The root README makes the preserved database/docs folder layout easy to follow.
contents["README.md"] = contents["database/uat/README.md"]
checksums = "\n".join(
    f"{hashlib.sha256(data).hexdigest()}  {name}"
    for name, data in sorted(contents.items())
) + "\n"
contents["CHECKSUMS.sha256"] = checksums.encode("utf-8")
output = ROOT / "storage/app/uat/STOK-WHFG-UAT26.zip"
output.parent.mkdir(parents=True, exist_ok=True)
with zipfile.ZipFile(output, "w", compression=zipfile.ZIP_DEFLATED) as archive:
    for name, data in contents.items():
        archive.writestr(name, data)
with zipfile.ZipFile(output) as archive:
    assert archive.testzip() is None
    assert set(archive.namelist()) == set(contents)
    for name, data in contents.items():
        assert archive.read(name) == data, f"Archive mismatch: {name}"
print(json.dumps({
    "archive": str(output), "bytes": output.stat().st_size,
    "files": len(contents), "manual_cases": len(cases),
    "feature_keys": len(features), "archive_integrity": "pass",
}, indent=2, ensure_ascii=True))
