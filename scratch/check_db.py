import sqlite3, json

conn = sqlite3.connect('database/analisa_pengunjung.sqlite')
cur = conn.cursor()

cur.execute("SELECT name FROM sqlite_master WHERE type='table'")
print("Tables:", [r[0] for r in cur.fetchall()])

cur.execute("PRAGMA table_info(counting_events)")
cols = cur.fetchall()
print("counting_events cols:", [c[1] for c in cols])

cur.execute("PRAGMA table_info(app_settings)")
cols2 = cur.fetchall()
print("app_settings cols:", [c[1] for c in cols2])

# Check recent events
cur.execute("SELECT * FROM counting_events ORDER BY event_timestamp DESC LIMIT 5")
rows = cur.fetchall()
col_names = [d[0] for d in cur.description]
print("\nColumn names:", col_names)
for r in rows:
    row_dict = dict(zip(col_names, r))
    snap = row_dict.get('snapshot_path', 'NO_COL')
    eid = row_dict.get('event_id', row_dict.get('id', '?'))
    print(f"  id={str(eid)[:30]} dir={row_dict.get('direction','?')} snap={str(snap)[:60]}")

# Check app_settings
cur.execute("SELECT * FROM app_settings WHERE setting_key LIKE 'r2%'")
rows2 = cur.fetchall()
col_names2 = [d[0] for d in cur.description]
print("\napp_settings (r2*):", col_names2)
for r in rows2:
    print(" ", dict(zip(col_names2, r)))

conn.close()
