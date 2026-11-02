"""
Upload All Local Evidence to R2 + Cleanup
Script ini:
1. Scan semua file lokal di uploads/evidence/YYYY-MM-DD/
2. Upload yang belum ada di R2 ke path yang benar (evidence/YYYY-MM-DD/IN|OUT/)
3. Hapus file lokal setelah berhasil upload
4. Fix snapshot_path di DB untuk semua events
"""

import boto3, sqlite3, json, os, re
from botocore.config import Config
from datetime import datetime

DB_PATH = 'database/analisa_pengunjung.sqlite'
UPLOADS_DIR = 'uploads/evidence'

# Load settings
conn = sqlite3.connect(DB_PATH)
cur = conn.cursor()
cur.execute("SELECT setting_key, setting_value FROM app_settings WHERE setting_key LIKE 'r2%'")
settings = {r[0]: r[1] for r in cur.fetchall()}

# Fetch all events lookup
cur.execute("SELECT id, direction, snapshot_path FROM counting_events")
all_events_rows = cur.fetchall()
event_lookup = {r[0]: {'direction': r[1], 'snap': r[2]} for r in all_events_rows}
conn.close()

account_id = settings['r2_account_id']
bucket = settings['r2_bucket']

s3 = boto3.client('s3',
    endpoint_url=f'https://{account_id}.r2.cloudflarestorage.com',
    aws_access_key_id=settings['r2_access_key_id'],
    aws_secret_access_key=settings['r2_secret_access_key'],
    config=Config(signature_version='s3v4'),
    region_name='auto'
)

# Get all R2 keys
print("Fetching R2 file list...")
r2_keys = set()
paginator = s3.get_paginator('list_objects_v2')
for page in paginator.paginate(Bucket=bucket, Prefix='evidence/'):
    for obj in page.get('Contents', []):
        r2_keys.add(obj['Key'])
print(f"R2 has {len(r2_keys)} files")

# Scan local files
local_files = []
if os.path.exists(UPLOADS_DIR):
    for date_folder in os.listdir(UPLOADS_DIR):
        date_path = os.path.join(UPLOADS_DIR, date_folder)
        if not os.path.isdir(date_path):
            continue
        # Check files directly in date folder (flat, without IN/OUT)
        for fname in os.listdir(date_path):
            fpath = os.path.join(date_path, fname)
            if os.path.isfile(fpath) and fname.endswith('.jpg'):
                local_files.append({
                    'local_path': fpath,
                    'date_str': date_folder,
                    'filename': fname,
                    'in_subdir': False
                })
        # Check IN/OUT subdirs
        for subdir in ['IN', 'OUT']:
            subdir_path = os.path.join(date_path, subdir)
            if os.path.isdir(subdir_path):
                for fname in os.listdir(subdir_path):
                    fpath = os.path.join(subdir_path, fname)
                    if os.path.isfile(fpath) and fname.endswith('.jpg'):
                        local_files.append({
                            'local_path': fpath,
                            'date_str': date_folder,
                            'filename': fname,
                            'subdir': subdir,
                            'in_subdir': True
                        })

print(f"Local files: {len(local_files)}")

uploaded = 0
skipped = 0
cleaned = 0
errors = 0

conn = sqlite3.connect(DB_PATH)
cur = conn.cursor()

for f in local_files:
    fname = f['filename']
    date_str = f['date_str']
    local_path = f['local_path']

    # Determine event_id from filename
    event_id = fname.replace('.jpg', '')
    if event_id.startswith('evt-test') or event_id.startswith('evt_test'):
        print(f"  SKIP test file: {fname}")
        skipped += 1
        continue

    # Get direction from DB or from subdir
    if f['in_subdir']:
        dir_folder = f['subdir']
    else:
        ev = event_lookup.get(event_id, {})
        dir_folder = (ev.get('direction') or 'IN').upper()
        if dir_folder not in ['IN', 'OUT']:
            dir_folder = 'IN'

    r2_key = f"evidence/{date_str}/{dir_folder}/{fname}"

    if r2_key in r2_keys:
        # Already in R2, just delete local
        try:
            os.remove(local_path)
            cleaned += 1
        except Exception:
            pass
        # Update DB if needed
        ev = event_lookup.get(event_id, {})
        if ev.get('snap') != r2_key:
            cur.execute("UPDATE counting_events SET snapshot_path = ? WHERE id = ?", (r2_key, event_id))
            event_lookup[event_id] = {'direction': dir_folder, 'snap': r2_key}
        continue

    # Upload to R2
    try:
        with open(local_path, 'rb') as fh:
            s3.put_object(
                Bucket=bucket,
                Key=r2_key,
                Body=fh,
                ContentType='image/jpeg',
                Metadata={'event_id': event_id, 'uploaded_at': datetime.utcnow().isoformat()}
            )
        r2_keys.add(r2_key)

        # Update DB snapshot_path
        cur.execute("UPDATE counting_events SET snapshot_path = ? WHERE id = ?", (r2_key, event_id))
        event_lookup[event_id] = {'direction': dir_folder, 'snap': r2_key}

        # Delete local
        os.remove(local_path)
        uploaded += 1
        print(f"  Uploaded & cleaned: {r2_key}")
    except Exception as e:
        errors += 1
        print(f"  ERROR {fname}: {e}")

conn.commit()
conn.close()

print(f"\n=== DONE ===")
print(f"  Uploaded new:     {uploaded}")
print(f"  Already in R2:    {cleaned} (local deleted)")
print(f"  Skipped:          {skipped}")
print(f"  Errors:           {errors}")

# Run R2 straggler fix too
import subprocess
print("\nRunning R2 straggler fix...")
