<?php

/**
 * ZPL (Zebra Programming Language) codes cannot be viewed on standard displays.
 * Geliştiriciler termal yazıcı etiketlerini bilgisayar ortamında test edebilsin diye
 * bu betik, ZPL kodunu ücretsiz ve açık kaynaklı Labelary API'sine gönderip PNG olarak kaydeder.
 */

// A sample ZPL code (Normally generated from your shipment reference number)
$zplCode = "^XA\n"
    . "^PW800\n"
    . "^LL600\n"
    . "^FO60,50^A0N,36,36^FDSURAT KARGO - TEST^FS\n"
    . "^FO60,110^BCN,180,Y,N,N^FDDK260809200001^FS\n"
    . "^FO60,340^A0N,28,28^FDAlici: Ahmet Yilmaz^FS\n"
    . "^FO60,390^A0N,24,24^FDSiparis Ref: ORDER-123456^FS\n"
    . "^XZ";

echo "ZPL kodu PNG etiket görseline dönüştürülüyor...\n";

// Labelary parameters: 8dpmm (203 dpi) print density, 4x6 inches label size, label index 0
$url = "http://api.labelary.com/v1/printers/8dpmm/labels/4x6/0/";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $zplCode);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: image/png' // Request image format response
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $outputPath = __DIR__ . '/kargo_etiket_onizleme.png';
    file_put_contents($outputPath, $response);
    echo "Başarılı! ZPL etiket görseli kaydedildi:\n";
    echo "Dosya: {$outputPath}\n";
    echo "Bu resmi tarayıcınızda açıp barkodun taranabilirliğini test edebilirsiniz.\n";
} else {
    echo "ZPL görsel dönüşümü başarısız oldu. Hata Kodu: {$httpCode}\n";
    echo "Labelary Yanıtı: {$response}\n";
}
