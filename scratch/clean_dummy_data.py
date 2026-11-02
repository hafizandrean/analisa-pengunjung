import sqlite3, os

conn = sqlite3.connect('database/analisa_pengunjung.sqlite')
cur = conn.cursor()

# 1. Hapus demo camera cam-1 dan duplikat tess
cur.execute("DELETE FROM cameras WHERE id = 'cam-1'")
cur.execute("DELETE FROM cameras WHERE id = 'cam-6ae3093eaf6b2'")

# Update nama dan lokasi kamera CCTV toko yang riil
cur.execute("""
    UPDATE cameras 
    SET name = 'Kamera CCTV Toko Utama', location = 'Pintu Masuk Restoran'
    WHERE id = 'cam-6ae30b77d51af'
""")

# 2. Hapus seluruh data event counting palsu / demo
cur.execute("DELETE FROM counting_events")
cur.execute("DELETE FROM system_logs")

conn.commit()

# Verifikasi
cur.execute("SELECT id, name, source_type, source_uri, location FROM cameras")
print("Kamera yang tersisa:")
for r in cur.fetchall():
    print(" -", r)

cur.execute("SELECT count(*) FROM counting_events")
print("Total counting_events:", cur.fetchone()[0])
conn.close()

# 3. Hapus dummy file di uploads/
test_f1 = 'uploads/evidence/2026-10-21/evt-test-1792555314.jpg'
test_f2 = 'uploads/evidence/2026-10-21/evt_test_countiva.jpg'
for tf in [test_f1, test_f2]:
    if os.path.exists(tf):
        os.remove(tf)
        print('Hapus:', tf)

print('Clean-up selesai!')
