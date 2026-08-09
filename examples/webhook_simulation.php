<?php

require_once __DIR__ . '/../vendor/autoload.php';

use SuratKargo\SuratKargoClient;
use SuratKargo\SuratKargoException;

// Initialize SDK Client
$client = new SuratKargoClient([
    'shipping_username' => 'test_kullanici',
    'shipping_password' => 'test_sifre',
    'customer_code'     => '123456789',
    'web_password'      => 'web_sifre_123',
]);

/**
 * WEBHOOK SIMULATION ARCHITECTURE
 * 
 * Since Sürat Kargo WSDL API does not support native webhook dispatches,
 * you can simulate a real-time Webhook system by following this polling-and-trigger flow.
 * 
 * Flow:
 * 1. Read shipments with active statuses (e.g. 'approved', 'transfer', 'branch') from your database.
 * 2. Query these references in batches using SDK's `queryBatch()` (reduces network overhead dramatically).
 * 3. Loop through results and compare new statuses with old statuses stored in your DB.
 * 4. If status has changed, dispatch an HTTP POST (Webhook) to your internal/external services.
 */

// Simulated database record state
$myActiveShipmentsInDb = [
    'ORDER-1001' => ['status' => 'approved', 'tracking_number' => null],
    'ORDER-1002' => ['status' => 'transfer', 'tracking_number' => '123456789012'],
    'ORDER-1003' => ['status' => 'out_for_delivery', 'tracking_number' => '987654321098']
];

// References list to sync
$referencesToSync = array_keys($myActiveShipmentsInDb);

// Target Webhook URL to dispatch updates to
$webhookTargetUrl = 'https://api.mydomain.com/v1/webhooks/cargo';

try {
    echo "Fiyat/Limit aşımına takılmadan toplu sorgu başlatılıyor...\n";
    
    // Batch query up to 100+ references in a single request
    $results = $client->queryBatch($referencesToSync);

    echo "Sürat Kargo API'den " . count($results) . " adet kargo kaydı döndü. Analiz ediliyor...\n\n";

    foreach ($results as $row) {
        $reference = $row['WebSiparisKodu'] ?? null;
        if (!$reference || !isset($myActiveShipmentsInDb[$reference])) {
            continue;
        }

        $rawStatus = $row['Durum'] ?? $row['SonDurum'] ?? null;
        $trackingNo = $row['KargoTakipNo'] ?? null;
        
        // Resolve Turkish Sürat Kargo status string to standard status
        $resolvedStatus = $client->resolveStatus($rawStatus);
        
        $oldState = $myActiveShipmentsInDb[$reference];

        // Check if status has changed or a new tracking number is assigned
        $isStatusChanged = ($resolvedStatus !== $oldState['status']);
        $isTrackingNoAssigned = ($trackingNo && $trackingNo !== $oldState['tracking_number']);

        if ($isStatusChanged || $isTrackingNoAssigned) {
            echo "⚡ Durum Değişikliği Algılandı! Sipariş Ref: {$reference}\n";
            echo "   Eski Durum: {$oldState['status']} -> Yeni Durum: {$resolvedStatus}\n";
            echo "   Takip No: " . ($oldState['tracking_number'] ?? 'N/A') . " -> {$trackingNo}\n";

            // Prepare webhook payload
            $webhookPayload = [
                'event' => 'shipment.status_updated',
                'timestamp' => time(),
                'data' => [
                    'reference' => $reference,
                    'tracking_number' => $trackingNo,
                    'status' => $resolvedStatus,
                    'raw_status_description' => $rawStatus,
                    'original_payload' => $row
                ]
            ];

            echo "   [SIMULE] Webhook Gönderiliyor -> {$webhookTargetUrl}\n";
            echo "   Payload: " . json_encode($webhookPayload, JSON_UNESCAPED_UNICODE) . "\n";
            
            // In production, execute the HTTP POST request:
            /*
            $ch = curl_init($webhookTargetUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhookPayload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-Webhook-Signature: ' . hash_hmac('sha256', json_encode($webhookPayload), 'my-secret-key')
            ]);
            $response = curl_exec($ch);
            curl_close($ch);
            */
            echo "   -------------------------------------------------\n";
        } else {
            echo "ℹ️ Sipariş Ref: {$reference} için durum değişmedi. (Durum: {$resolvedStatus})\n";
        }
    }

} catch (SuratKargoException $e) {
    echo "Hata: " . $e->getMessage() . "\n";
}
