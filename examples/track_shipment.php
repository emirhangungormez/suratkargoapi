<?php

require_once __DIR__ . '/../vendor/autoload.php';

use SuratKargo\SuratKargoClient;
use SuratKargo\SuratKargoException;

$client = new SuratKargoClient([
    'shipping_username' => 'test_kullanici',
    'shipping_password' => 'test_sifre',
    'customer_code'     => '123456789',
    'web_password'      => 'web_sifre_123',
]);

// Put your shipment's reference number (OzelKargoTakipNo) or tracking number here
$referenceNo = 'DK260809200001';

try {
    echo "Referans Kodu {$referenceNo} için takip sorgulaması yapılıyor...\n\n";

    // 1. Get detailed shipping movement history
    $movements = $client->trackShipment($referenceNo);

    if (empty($movements)) {
        echo "Kargo hareket kaydı bulunamadı.\n";
    } else {
        echo "Kargo Hareket Geçmişi:\n";
        echo "=====================================\n";
        foreach ($movements as $movement) {
            $date = $movement['Tarih'] ?? $movement['TarihSaat'] ?? 'Bilinmeyen Tarih';
            $unit = $movement['Birim'] ?? $movement['Sube'] ?? 'Bilinmeyen Şube';
            $action = $movement['Islem'] ?? $movement['Durum'] ?? 'İşlem Bilgisi Yok';
            $description = $movement['Aciklama'] ?? '';

            echo "[$date] - Şube: $unit\n";
            echo "İşlem: $action" . ($description ? " ($description)" : "") . "\n";
            echo "-------------------------------------\n";
        }
    }

    // 2. Query web order general status
    $statusData = $client->queryByReference($referenceNo);
    
    echo "\nGenel Sipariş Bilgileri:\n";
    echo "=====================================\n";
    if (isset($statusData['Mesaj'])) {
        echo "Mesaj: " . $statusData['Mesaj'] . "\n";
    } else {
        $rawStatus = $statusData['Durum'] ?? $statusData['SonDurum'] ?? null;
        $resolvedStatus = $client->resolveStatus($rawStatus);

        echo "Alıcı: " . ($statusData['AliciCariUnvan'] ?? 'N/A') . "\n";
        echo "Takip No: " . ($statusData['KargoTakipNo'] ?? 'N/A') . "\n";
        echo "Ham Durum (Sürat Kargo): " . ($rawStatus ?? 'N/A') . "\n";
        echo "Çözümlenmiş Durum (E-Ticaret): {$resolvedStatus}\n";
    }

    // 3. Retrieve barcode and ZPL printer codes
    echo "\nBarkod ve Etiket Bilgileri Çekiliyor...\n";
    $barcodeData = $client->getBarcode($referenceNo);
    
    echo "Mevcut Barkod Sayısı: " . count($barcodeData['barcodes']) . "\n";
    if (!empty($barcodeData['pdf'])) {
        echo "PDF Barkod Etiketi: Mevcut (Base64 boyutu: " . strlen($barcodeData['pdf']) . " karakter)\n";
    }
    if (!empty($barcodeData['printer_codes'])) {
        echo "ZPL/Printer Kodları: Mevcut\n";
        echo "Örnek Yazıcı Kodu: " . substr($barcodeData['printer_codes'][0], 0, 100) . "...\n";
    }

} catch (SuratKargoException $e) {
    echo "Hata: " . $e->getMessage() . "\n";
}
