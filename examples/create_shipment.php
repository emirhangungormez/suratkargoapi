<?php

// Autoload dependencies (Make sure to run 'composer install' or include manually)
require_once __DIR__ . '/../vendor/autoload.php';

// If you want to include files manually:
// require_once __DIR__ . '/../src/ShipmentData.php';
// require_once __DIR__ . '/../src/SuratKargoClient.php';
// require_once __DIR__ . '/../src/SuratKargoException.php';

use SuratKargo\SuratKargoClient;
use SuratKargo\ShipmentData;
use SuratKargo\SuratKargoException;

// Initialize SDK Client Config
$client = new SuratKargoClient([
    'shipping_username' => 'test_kullanici', // Shipping Username (KullaniciAdi)
    'shipping_password' => 'test_sifre',    // Shipping Password (Sifre)
    'customer_code'     => '123456789',     // Customer Code (cariKodu)
    'web_password'      => 'web_sifre_123', // Web Service Password (WebPassword)
    'verify_ssl'        => false,           // Set to false for testing environment if needed
]);

try {
    // Generate a unique reference number (Must be unique for every request)
    $referenceNo = 'DK' . date('ymdHis') . rand(1000, 9999);

    // Create the Shipment payload
    $shipment = new ShipmentData(
        recipientName: 'Ahmet Yılmaz',
        address: 'Atatürk Mahallesi, Fatih Caddesi No:45 Daire:3',
        city: 'İstanbul',
        district: 'Ataşehir',
        phoneMobile: '5551234567', // Will be normalized to '5551234567'
        referenceNo: $referenceNo
    );

    // Optional attributes
    $shipment->email = 'ahmet.yilmaz@example.com';
    $shipment->packageCount = 2; // Number of packages
    $shipment->desi = 3.5;       // Desi value
    $shipment->weight = 2.0;     // Weight in KG
    $shipment->contentDescription = 'E-Ticaret Sipariş Ürünü';
    
    // For Cash on Delivery (COD) shipments (Kapıda Ödeme)
    // $shipment->codType = 1; // 1: Cash (Nakit), 2: Credit Card (Kredi Kartı)
    // $shipment->codAmount = 150.00; // Tutar

    echo "Kargo gönderisi hazırlanıyor...\n";
    echo "Referans No: {$shipment->referenceNo}\n";

    // Call SDK to create shipment (Cascade fallback is executed automatically)
    $result = $client->createShipment($shipment);

    echo "\nGönderi Başarıyla Oluşturuldu!\n";
    echo "-------------------------------------\n";
    echo "Referans No: " . $result['reference'] . "\n";
    echo "Kargo Takip No: " . ($result['tracking_number'] ?? 'Barkodsuz oluşturuldu (Takip No şubede oluşacak)') . "\n";
    echo "Barkod Kodu: " . ($result['barcode'] ?? 'N/A') . "\n";
    echo "Kullanılan API Metodu: " . $result['method_used'] . "\n";
    echo "Mesaj: " . $result['message'] . "\n";

} catch (SuratKargoException $e) {
    echo "\nGönderi oluşturulurken hata meydana geldi: " . $e->getMessage() . "\n";
}
