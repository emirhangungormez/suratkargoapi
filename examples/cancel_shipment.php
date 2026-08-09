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

$referenceNo = 'DK260809200001';

/**
 * Sürat Kargo API allows cancelling shipping orders through two different methods:
 * 
 * 1. GonderiSil (cancelShipment):
 *    - Uses Customer Code & Web Password.
 *    - Deletes the shipment registration record using the special reference tracking number.
 *    - Highly recommended for standard cancel integrations.
 * 
 * 2. GonderiGeriCek (withdrawShipment):
 *    - Uses Shipping Username & Password.
 *    - Specifically triggers a "withdraw" command on the delivery order with a cancellation reason.
 *    - Best for customer-initiated returns before dispatch.
 */

try {
    echo "Metot 1: GonderiSil (cancelShipment) deneniyor...\n";
    $result = $client->cancelShipment($referenceNo);
    echo "İptal Sonucu: {$result}\n\n";

} catch (SuratKargoException $e) {
    echo "GonderiSil Hata verdi: " . $e->getMessage() . "\n";
    echo "Şimdi Metot 2: GonderiGeriCek (withdrawShipment) deneniyor...\n";

    try {
        $result = $client->withdrawShipment($referenceNo, 'Müşteri siparişi iptal etti.');
        echo "Geri Çekme Sonucu: {$result}\n";
    } catch (SuratKargoException $ex) {
        echo "GonderiGeriCek de hata verdi: " . $ex->getMessage() . "\n";
    }
}
