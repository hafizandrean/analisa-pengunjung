"""
Fix All R2 - One-shot script untuk:
1. Memindahkan semua straggler flat files ke evidence/YYYY-MM-DD/IN|OUT/
2. Memperbaiki snapshot_path di DB semua events → pakai R2 key (bukan r2.dev URL)
"""

import boto3, sqlite3, json, os
from botocore.config import Config

DB_PATH = 'database/analisa_pengunjung.sqlite'

# Load settings from DB
conn = sqlite3.connect(DB_PATH)
cur = conn.cursor()
cur.execute("SELECT setting_key, setting_value FROM app_settings WHERE setting_key LIKE 'r2%'")
settings = {r[0]: r[1] for r in cur.fetchall()}

# Fetch all events
cur.execute("SELECT id, direction, snapshot_path, metadata_json FROM counting_events")
all_events = cur.fetchall()
conn.close()

account_id = settings['r2_account_id']
bucket = settings['r2_bucket']
access_key = settings['r2_access_key_id']
secret_key = settings['r2_secret_access_key']

s3 = boto3.client('s3',
    endpoint_url=f'https://{account_id}.r2.cloudflarestorage.com',
    aws_access_key_id=access_key,
    aws_secret_access_key=secret_key,
    config=Config(signature_version='s3v4'),
    region_name='auto'
)

# List all R2 files
print("Mengambil daftar file dari R2...")
resp = s3.list_objects_v2(Bucket=bucket, Prefix='evidence/')
all_r2_files = {o['Key']: o['Size'] for o in resp.get('Contents', [])}
print(f"Total file R2: {len(all_r2_files)}")

# Identify stragglers (flat: evidence/YYYY-MM-DD/filename.jpg — only 2 slashes)
stragglers = {k: v for k, v in all_r2_files.items() if k.count('/') == 2}
structured = {k: v for k, v in all_r2_files.items() if k.count('/') > 2}
print(f"Structured (IN/OUT): {len(structured)}")
print(f"Straggler flat: {len(stragglers)}")

# Build lookup: event_id → direction from DB
event_lookup = {}
for eid, direction, snap, meta in all_events:
    if eid:
        event_lookup[eid] = direction

# Move straggler files to correct IN/OUT path
print("\n--- Memindahkan straggler files ---")
moved = 0
for key, size in stragglers.items():
    # key = evidence/2026-10-21/evt-xxx-yyy.jpg
    parts = key.split('/')
    if len(parts) != 3:
        continue
    date_str = parts[1]
    filename = parts[2]  # evt-xxx-yyy.jpg

    # Extract event_id from filename (remove .jpg extension)
    event_id = filename.replace('.jpg', '')

    direction = event_lookup.get(event_id, None)
    if not direction:
        # Try to infer from filename pattern or default to IN
        print(f"  ⚠️  event_id={event_id} tidak ada di DB, default ke IN")
        direction = 'IN'

    dir_folder = direction.upper()
    new_key = f"evidence/{date_str}/{dir_folder}/{filename}"

    if new_key in all_r2_files:
        # Sudah ada di lokasi yang benar, hapus straggler
        print(f"  ♻️  {key} → sudah ada di {new_key}, hapus straggler")
        s3.delete_object(Bucket=bucket, Key=key)
        moved += 1
        continue

    # Copy ke lokasi baru
    copy_source = {'Bucket': bucket, 'Key': key}
    try:
        s3.copy_object(Bucket=bucket, Key=new_key, CopySource=copy_source, MetadataDirective='COPY')
        s3.delete_object(Bucket=bucket, Key=key)
        all_r2_files[new_key] = size
        print(f"  ✅ {key} → {new_key}")
        moved += 1
    except Exception as e:
        print(f"  ❌ Gagal pindah {key}: {e}")

print(f"\nTotal dipindah: {moved}")

# Now fix snapshot_path in DB for ALL events
# Build final R2 file index after moves
print("\n--- Memperbarui snapshot_path di DB ---")
conn = sqlite3.connect(DB_PATH)
cur = conn.cursor()

# Re-list R2 after moves
resp2 = s3.list_objects_v2(Bucket=bucket, Prefix='evidence/')
final_r2 = {o['Key'] for o in resp2.get('Contents', [])}

updated = 0
skipped = 0
not_found = 0

for eid, direction, snap, meta_raw in all_events:
    if not eid:
        continue

    dir_folder = (direction or 'IN').upper()
    filename = f"{eid}.jpg"

    # Look for this event's file in R2 (check both IN and OUT)
    for try_dir in [dir_folder, 'IN' if dir_folder == 'OUT' else 'OUT']:
        # We don't know the exact date; extract from current snapshot_path or find it
        candidate_key = None

        # Try to extract date from current snapshot_path
        if snap:
            import re
            m = re.search(r'(\d{4}-\d{2}-\d{2})', snap)
            if m:
                date_str = m.group(1)
                candidate_key = f"evidence/{date_str}/{try_dir}/{filename}"
                if candidate_key in final_r2:
                    break
        candidate_key = None

    # Search all matching keys for this event_id
    matching_keys = [k for k in final_r2 if k.endswith(f"/{filename}")]
    
    if not matching_keys:
        # No file in R2 for this event
        not_found += 1
        continue

    # Pick the best match (prefer IN/OUT matching DB direction)
    best_key = None
    for k in matching_keys:
        if f"/{dir_folder}/" in k:
            best_key = k
            break
    if not best_key:
        best_key = matching_keys[0]

    # Update snapshot_path in DB to R2 key
    current_snap = snap or ''
    if current_snap == best_key:
        skipped += 1
        continue

    cur.execute("UPDATE counting_events SET snapshot_path = ? WHERE id = ?", (best_key, eid))
    
    # Also update metadata_json with r2_key
    try:
        meta = json.loads(meta_raw) if meta_raw else {}
    except Exception:
        meta = {}
    meta['r2_key'] = best_key
    cur.execute("UPDATE counting_events SET metadata_json = ? WHERE id = ?", (json.dumps(meta), eid))

    updated += 1
    print(f"  ✅ {eid[:30]} → {best_key}")

conn.commit()
conn.close()

print(f"\n=== SELESAI ===")
print(f"  snapshot_path diupdate : {updated}")
print(f"  Sudah benar (skip)     : {skipped}")
print(f"  Tidak ada di R2        : {not_found}")
print(f"  Straggler dipindah     : {moved}")
print("\n✅ Semua file R2 kini terstruktur rapi dan snapshot_path di DB sudah benar!")
