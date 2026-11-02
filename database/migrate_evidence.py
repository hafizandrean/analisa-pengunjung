import sqlite3
import os

db_path = os.path.abspath(os.path.join(os.path.dirname(__file__), "analisa_pengunjung.sqlite"))
print("Migrating database:", db_path)

conn = sqlite3.connect(db_path)
cursor = conn.cursor()

# 1. Cek kolom snapshot_path di counting_events
cursor.execute("PRAGMA table_info(counting_events)")
columns = [col[1] for col in cursor.fetchall()]

if "snapshot_path" not in columns:
    print("Adding column 'snapshot_path' to counting_events...")
    cursor.execute("ALTER TABLE counting_events ADD COLUMN snapshot_path TEXT NULL")
    print("Column 'snapshot_path' added successfully.")
else:
    print("Column 'snapshot_path' already exists.")

# 2. Tambahkan default settings untuk Evidence & Cloudflare R2
default_settings = [
    ("evidence_storage_mode", "hybrid_r2"),
    ("evidence_retention_days", "14"),
    ("r2_account_id", "21d6193577f6f49e1c8d495034b899bc"),
    ("r2_bucket", "countiva"),
    ("r2_access_key_id", ""),
    ("r2_secret_access_key", ""),
    ("r2_public_domain", "")
]

for key, val in default_settings:
    cursor.execute("SELECT COUNT(*) FROM app_settings WHERE setting_key = ?", (key,))
    if cursor.fetchone()[0] == 0:
        cursor.execute("INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)", (key, val))
        print(f"Added setting: {key} = {val}")

conn.commit()
conn.close()
print("Migration completed.")
