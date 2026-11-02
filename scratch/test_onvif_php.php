<?php
$ip = "192.168.18.30";
$port = 8899;

function postSoap($url, $xml) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/soap+xml; charset=utf-8'
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

$devXml = postSoap("http://{$ip}:{$port}/onvif/device_service", '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:tds="http://www.onvif.org/ver10/device/wsdl"><soap:Body><tds:GetDeviceInformation /></soap:Body></soap:Envelope>');

preg_match('/<tds:Model>([^<]+)<\/tds:Model>/', $devXml, $mModel);
preg_match('/<tds:SerialNumber>([^<]+)<\/tds:SerialNumber>/', $devXml, $mSerial);
$model = $mModel[1] ?? 'Generic ONVIF';
$serial = $mSerial[1] ?? '';

echo "Model: $model, Serial: $serial\n";

$mediaXml = postSoap("http://{$ip}:{$port}/onvif/media_service", '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:trt="http://www.onvif.org/ver10/media/wsdl" xmlns:tt="http://www.onvif.org/ver10/schema"><soap:Body><trt:GetStreamUri><trt:StreamSetup><tt:Stream>RTP-Unicast</tt:Stream><tt:Transport><tt:Protocol>RTSP</tt:Protocol></tt:Transport></trt:StreamSetup><trt:ProfileToken>001</trt:ProfileToken></trt:GetStreamUri></soap:Body></soap:Envelope>');

preg_match('/<tt:Uri>([^<]+)<\/tt:Uri>/', $mediaXml, $mUri);
$rtspUri = html_entity_decode($mUri[1] ?? '');
echo "RTSP URI: $rtspUri\n";
