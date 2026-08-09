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

try {
    // 1. COD Payment Reconciliation Query (Kapıda Ödeme Tahsilat Sorgulama)
    // Query last 7 days of COD transfers
    $from = new DateTime('-7 days');
    $to = new DateTime();

    echo "Kapıda Ödeme Tahsilat Raporu Çekiliyor ({$from->format('Y-m-d')} - {$to->format('Y-m-d')})...\n";
    $codPayments = $client->getCodPayments($from, $to);

    if (empty($codPayments)) {
        echo "Belirtilen tarihler arasında hesaba aktarılan kapıda ödeme tahsilatı bulunamadı.\n";
    } else {
        echo "Bulunan Tahsilat İşlemleri:\n";
        echo "=====================================\n";
        foreach ($codPayments as $payment) {
            echo "Evrak No: " . ($payment['evrak_no'] ?? 'N/A') . "\n";
            echo "Sipariş Kodu / Ref: " . ($payment['WebSiparisKodu'] ?? 'N/A') . "\n";
            echo "Alıcı Unvanı: " . ($payment['AliciCariUnvan'] ?? 'N/A') . "\n";
            echo "Tahsilat Tutarı: " . ($payment['TahsilatTutari'] ?? '0.00') . " TL\n";
            echo "Kargo Kesinti Bedeli: " . ($payment['KargoKesintiBedeli'] ?? '0.00') . " TL\n";
            echo "Hesaba Ödenen Net Tutar: " . ($payment['OdenecekTutar'] ?? '0.00') . " TL\n";
            echo "Ödeme Tarihi: " . ($payment['OdemeTarihi'] ?? 'N/A') . "\n";
            echo "-------------------------------------\n";
        }
    }

    // 2. Customer Returns Query (Müşteri İade Kargo Sorgulama)
    echo "\nMüşteri İade Kargoları Sorgulanıyor...\n";
    $returns = $client->getReturns($from, $to);

    if (empty($returns)) {
        echo "Belirtilen tarih aralığında size gelen iade kargo kaydı bulunamadı.\n";
    } else {
        echo "Size Gelen İade Kargolar:\n";
        echo "=====================================\n";
        foreach ($returns as $row) {
            echo "Gönderen: " . ($row['GondericiUnvan'] ?? 'N/A') . "\n";
            echo "Takip No: " . ($row['KargoTakipNo'] ?? 'N/A') . "\n";
            echo "İade Sipariş Ref: " . ($row['WebSiparisKodu'] ?? 'N/A') . "\n";
            echo "Çıkış Şubesi: " . ($row['GonderenSube'] ?? 'N/A') . "\n";
            echo "-------------------------------------\n";
        }
    }

    // 3. Out of Area Delivery Check (Alan Dışı / Mobil Alan Sorgulama)
    echo "\nSürat Kargo Alan Dışı (Mobil Teslimat) Bölge Sorgusu...\n";
    // Check remote zones (Pass empty string to list all, or pass a specific neighborhood ID)
    $remoteZones = $client->getOutofAreaNeighborhoods();

    echo "Toplam Alan Dışı Mahalle Sayısı: " . count($remoteZones) . "\n";
    if (!empty($remoteZones)) {
        echo "Örnek Alan Dışı Bölgeler:\n";
        echo "=====================================\n";
        $counter = 0;
        foreach ($remoteZones as $zone) {
            echo "İl/İlçe: " . ($zone['IlAd'] ?? 'N/A') . " / " . ($zone['IlceAd'] ?? 'N/A') . "\n";
            echo "Mahalle: " . ($zone['MahalleAd'] ?? 'N/A') . "\n";
            echo "Açıklama: " . ($zone['Aciklama'] ?? 'N/A') . "\n";
            echo "-------------------------------------\n";
            if (++$counter >= 3) {
                break; // Show only 3 examples
            }
        }
    }

} catch (SuratKargoException $e) {
    echo "\nHata: " . $e->getMessage() . "\n";
}
