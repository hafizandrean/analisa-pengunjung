with open('cameras.php', 'r', encoding='utf-8') as f:
    text = f.read()

text = text.replace("openManualAddWithPreset('HIKVISION')", "openManualAddWithPreset('XIONGMAI')")
text = text.replace('Hikvision / Dahua</div>', 'Xiongmai / XMeye / SPC</div>')

with open('cameras.php', 'w', encoding='utf-8', newline='\r\n') as f:
    f.write(text)

print('Updated openManualAddWithPreset in cameras.php!')
