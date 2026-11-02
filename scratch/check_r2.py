import boto3, sqlite3
from botocore.config import Config

conn = sqlite3.connect('database/analisa_pengunjung.sqlite')
cur = conn.cursor()
cur.execute("SELECT setting_key, setting_value FROM app_settings WHERE setting_key LIKE 'r2%'")
settings = {r[0]: r[1] for r in cur.fetchall()}
conn.close()

s3 = boto3.client('s3',
    endpoint_url='https://' + settings['r2_account_id'] + '.r2.cloudflarestorage.com',
    aws_access_key_id=settings['r2_access_key_id'],
    aws_secret_access_key=settings['r2_secret_access_key'],
    config=Config(signature_version='s3v4'),
    region_name='auto'
)

resp = s3.list_objects_v2(Bucket=settings['r2_bucket'], Prefix='evidence/')
files = [(o['Key'], o['Size']) for o in resp.get('Contents', [])]
print(f'Total files in R2: {len(files)}')

# Straggler = not in IN/ or OUT/ subdir (only 2 slashes deep: evidence/YYYY-MM-DD/file)
flat = [f for f in files if f[0].count('/') == 2]
deep = [f for f in files if f[0].count('/') > 2]
print(f'Properly structured (IN/OUT): {len(deep)}')
print(f'Straggler flat files: {len(flat)}')
for f in flat:
    print(f'  {f[0]} ({f[1]} bytes)')
