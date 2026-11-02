import xml.etree.ElementTree as ET
import re

with open('scratch/onvif_profiles.xml', 'r', encoding='utf-8') as f:
    text = f.read()

tokens = re.findall(r'token="([^"]+)"', text)
names = re.findall(r'<tt:Name>([^<]+)</tt:Name>', text)
resolutions = re.findall(r'<tt:Width>(\d+)</tt:Width>\s*<tt:Height>(\d+)</tt:Height>', text)

print('Tokens found:', tokens)
print('Names found:', names)
print('Resolutions:', resolutions)
