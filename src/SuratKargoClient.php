<?php

namespace SuratKargo;

use SimpleXMLElement;
use DateTimeInterface;

class SuratKargoClient
{
    private string $shippingUsername; // KullaniciAdi
    private string $shippingPassword; // Sifre
    private string $customerCode; // cariKodu / GonderenCariKodu
    private string $webPassword; // WebPassword / Sifre
    private string $endpoint;
    private int $timeout;
    private bool $verifySsl;
    private ?object $logger = null; // PSR-3 Logger (e.g. Monolog, Laravel Log)

    /**
     * SuratKargoClient constructor.
     *
     * @param array{
     *     shipping_username: string,
     *     shipping_password: string,
     *     customer_code: string,
     *     web_password: string,
     *     endpoint?: string,
     *     timeout?: int,
     *     verify_ssl?: bool,
     *     logger?: object
     * } $config
     * @throws SuratKargoException
     */
    public function __construct(array $config)
    {
        if (empty($config['shipping_username'])) {
            throw new SuratKargoException('shipping_username (KullaniciAdi) gereklidir.');
        }
        if (empty($config['shipping_password'])) {
            throw new SuratKargoException('shipping_password (Sifre) gereklidir.');
        }
        if (empty($config['customer_code'])) {
            throw new SuratKargoException('customer_code (cariKodu) gereklidir.');
        }
        if (empty($config['web_password'])) {
            throw new SuratKargoException('web_password (WebPassword) gereklidir.');
        }

        $this->shippingUsername = $config['shipping_username'];
        $this->shippingPassword = $config['shipping_password'];
        $this->customerCode = $config['customer_code'];
        $this->webPassword = $config['web_password'];
        
        $this->endpoint = $config['endpoint'] ?? 'https://webservices.suratkargo.com.tr/services.asmx';
        $this->timeout = $config['timeout'] ?? 30;
        $this->verifySsl = $config['verify_ssl'] ?? true;
        $this->logger = $config['logger'] ?? null;
    }

    /**
     * Main method to create a shipment.
     * Implements a resilient fallback cascade to ensure shipment creation succeeds regardless of the user's API tier permissions.
     *
     * @param ShipmentData $shipment
     * @return array{
     *     reference: string,
     *     tracking_number: ?string,
     *     barcode: ?string,
     *     barcodes: array<string>,
     *     message: string,
     *     method_used: string
     * }
     * @throws SuratKargoException
     */
    public function createShipment(ShipmentData $shipment): array
    {
        $gonderiXml = $shipment->toSoapXml();
        $reference = $shipment->referenceNo;

        try {
            // Cascade Step 1: Create Shipment & Generate Barcode
            $xml = $this->callRaw('GonderiyiKargoyaGonderYeniSiparisBarkodOlustur',
                '<KullaniciAdi>' . $this->escape($this->shippingUsername) . '</KullaniciAdi>'
                . '<Sifre>' . $this->escape($this->shippingPassword) . '</Sifre>'
                . '<Gonderi>' . $gonderiXml . '</Gonderi>');

            $isError = strtolower($this->value($xml, 'isError') ?? 'true') === 'true';
            $message = $this->value($xml, 'Message') ?: 'Sürat Kargo bilinmeyen yanıt verdi.';

            if ($isError) {
                // "050" code indicates that the barcode generation permission is not configured for individual barcodes.
                // Fall back to OrtakBarkodOlustur (Common Barcode Generator).
                if (trim($message) === '050') {
                    return $this->createShipmentWithCommonBarcode($gonderiXml, $reference);
                }
                throw new SuratKargoException($message);
            }

            $barcodes = $this->extractBarcodes($xml);

            return [
                'reference' => $reference,
                'tracking_number' => $this->value($xml, 'KargoTakipNo'),
                'barcode' => $barcodes[0] ?? null,
                'barcodes' => $barcodes,
                'message' => $message,
                'method_used' => 'GonderiyiKargoyaGonderYeniSiparisBarkodOlustur'
            ];

        } catch (SuratKargoException $e) {
            // If it failed due to credential issues or specific errors, cascade further to common barcode or raw creation
            if (str_contains($e->getMessage(), 'barkod') || str_contains($e->getMessage(), 'yetki')) {
                return $this->createShipmentWithCommonBarcode($gonderiXml, $reference);
            }
            throw $e;
        }
    }

    /**
     * Cascade Step 2: Create Shipment using Common Barcode method.
     */
    private function createShipmentWithCommonBarcode(string $gonderiXml, string $reference): array
    {
        try {
            $xml = $this->callRaw('OrtakBarkodOlustur',
                '<KullaniciAdi>' . $this->escape($this->shippingUsername) . '</KullaniciAdi>'
                . '<Sifre>' . $this->escape($this->shippingPassword) . '</Sifre>'
                . '<Gonderi>' . $gonderiXml . '</Gonderi>');

            $isError = strtolower($this->value($xml, 'isError') ?? 'true') === 'true';
            $message = $this->value($xml, 'Message') ?: 'Sürat Kargo bilinmeyen yanıt verdi.';

            if ($isError) {
                return $this->createShipmentWithoutBarcode($gonderiXml, $reference);
            }

            $barcodes = $this->extractBarcodes($xml);

            return [
                'reference' => $reference,
                'tracking_number' => $this->value($xml, 'KargoTakipNo'),
                'barcode' => $barcodes[0] ?? null,
                'barcodes' => $barcodes,
                'message' => $message,
                'method_used' => 'OrtakBarkodOlustur'
            ];
        } catch (SuratKargoException $e) {
            return $this->createShipmentWithoutBarcode($gonderiXml, $reference);
        }
    }

    /**
     * Cascade Step 3: Create Shipment without Barcode (fallback for account without barcode authority).
     */
    private function createShipmentWithoutBarcode(string $gonderiXml, string $reference): array
    {
        $xml = $this->callRaw('GonderiyiKargoyaGonderYeni',
            '<KullaniciAdi>' . $this->escape($this->shippingUsername) . '</KullaniciAdi>'
            . '<Sifre>' . $this->escape($this->shippingPassword) . '</Sifre>'
            . '<Gonderi>' . $gonderiXml . '</Gonderi>');

        $message = trim((string) ($this->value($xml, 'GonderiyiKargoyaGonderYeniResult') ?: ''));
        $normalized = mb_strtolower($message, 'UTF-8');

        if ($message === '' || str_contains($normalized, 'hata') || str_contains($normalized, 'başarısız') || str_contains($normalized, 'basarisiz')) {
            throw new SuratKargoException($message ?: 'Sürat Kargo gönderi oluşturma yanıtı alınamadı.');
        }

        return [
            'reference' => $reference,
            'tracking_number' => null,
            'barcode' => null,
            'barcodes' => [],
            'message' => $message . ' Barkod yetkisi olmadığı için gönderi barkodsuz oluşturuldu.',
            'method_used' => 'GonderiyiKargoyaGonderYeni'
        ];
    }

    /**
     * Cancels / deletes a shipment using the special tracking reference number.
     * Uses Customer Code & Web Password.
     */
    public function cancelShipment(string $reference): string
    {
        $xml = $this->call('GonderiSil', [
            'cariKodu' => $this->customerCode,
            'WebPassword' => $this->webPassword,
            'ozelKargoTakipNo' => $reference,
        ]);
        
        $message = $this->value($xml, 'GonderiSilResult') ?: 'Sürat Kargo iptal yanıtı alınamadı.';
        $normalized = mb_strtolower($message, 'UTF-8');
        
        if (!str_contains($normalized, 'başarı') && !str_contains($normalized, 'basari')) {
            throw new SuratKargoException($message);
        }
        
        return $message;
    }

    /**
     * Withdraws a shipment (e.g. customer returned request) before the package is handled.
     * Uses Shipping Username & Password.
     */
    public function withdrawShipment(string $reference, string $reason = 'Müşteri iade talebi'): string
    {
        $xml = $this->call('GonderiGeriCek', [
            'KullaniciAdi' => $this->shippingUsername,
            'Sifre' => $this->shippingPassword,
            'OzelKargoTakipNo' => $reference,
            'IptalNeden' => $reason,
        ]);
        
        $message = $this->value($xml, 'GonderiGeriCekResult') ?: 'Sürat Kargo geri çekme yanıtı alınamadı.';
        $normalized = mb_strtolower($message, 'UTF-8');
        
        if (!str_contains($normalized, 'başar') && !str_contains($normalized, 'basar')) {
            throw new SuratKargoException($message);
        }

        return $message;
    }

    /**
     * Queries a shipment status by its reference code.
     * Uses Customer Code & Web Password.
     *
     * @return array<string, string> Key-value pairs of the shipment details
     */
    public function queryByReference(string $reference): array
    {
        $xml = $this->call('WebSiparisKodu', [
            'GonderenCariKodu' => $this->customerCode,
            'Sifre' => $this->webPassword,
            'WebSiparisKodu' => $reference,
        ]);

        $rows = $this->datasetRows($xml);
        return $rows[0] ?? ['Mesaj' => $this->value($xml, 'Mesaj') ?: 'Kayıt bulunamadı.'];
    }

    /**
     * Queries multiple shipment statuses in a single SOAP API call.
     * Useful for batch synchronization and webhook dispatching.
     * Max recommended batch size is 100.
     *
     * @param array<string> $references Array of shipment reference codes
     * @return array<array<string, string>> Array of mapped shipment details
     * @throws SuratKargoException
     */
    public function queryBatch(array $references): array
    {
        if (empty($references)) {
            return [];
        }

        $cariNodes = '';
        foreach ($references as $_) {
            $cariNodes .= '<string>' . $this->escape($this->customerCode) . '</string>';
        }

        $refNodes = '';
        foreach ($references as $ref) {
            $refNodes .= '<string>' . $this->escape($ref) . '</string>';
        }

        $xml = $this->callRaw('WebSiparisKoduToplu',
            '<GonderenCariKodlari>' . $cariNodes . '</GonderenCariKodlari>'
            . '<WebSiparisKodlari>' . $refNodes . '</WebSiparisKodlari>'
            . '<Sifre>' . $this->escape($this->webPassword) . '</Sifre>');

        return $this->datasetRows($xml);
    }


    /**
     * Queries detailed barcode metadata, PDF content and ZPL printer codes.
     * Uses Customer Code & Web Password.
     */
    public function getBarcode(string $reference): array
    {
        $xml = $this->call('KargoBarkodu', [
            'cariKodu' => $this->customerCode,
            'WebPassword' => $this->webPassword,
            'ozelKargoTakipNo' => $reference,
        ]);

        $extractValues = fn (string $name) => array_values(array_filter(array_map(
            fn ($node) => trim((string) $node),
            $xml->xpath('.//*[local-name()="' . $name . '"]/*') ?: []
        )));

        return [
            'reference' => $this->value($xml, 'OzelKargoTakipNo'),
            'tracking_number' => $this->value($xml, 'KargoTakipNo'),
            'message' => $this->value($xml, 'Aciklama'),
            'barcodes' => $extractValues('BarkodNo'),
            'printer_codes' => $extractValues('PpdBarkod'),
            'pdf' => $this->value($xml, 'PdfBarkod'), // Base64 encoded string
        ];
    }

    /**
     * Tracks the real-time shipping movements and status history.
     * Uses Customer Code & Web Password.
     *
     * @return array<array<string, string>> Array of shipping movement steps
     * @throws SuratKargoException
     */
    public function trackShipment(string $reference): array
    {
        $xml = $this->call('KargoTakipHareketDetayliV2', [
            'CariKodu' => $this->customerCode,
            'Sifre' => $this->webPassword,
            'WebSiparisKodu' => $reference,
        ]);

        $resultString = $this->value($xml, 'KargoTakipHareketDetayliV2Result');
        if (empty($resultString)) {
            return [];
        }

        // The result is returned as a nested XML string inside the SOAP element. Parse it.
        $cleanXmlString = html_entity_decode($resultString, ENT_XML1, 'UTF-8');
        
        // Remove potential duplicate XML declarations
        $cleanXmlString = preg_replace('/<\?xml[^>]*\?>/i', '', $cleanXmlString);
        $cleanXmlString = '<root>' . $cleanXmlString . '</root>';

        $historyXml = @simplexml_load_string($cleanXmlString);
        if ($historyXml === false) {
            throw new SuratKargoException('Kargo takip hareket detayları XML formatı çözülemedi.');
        }

        $movements = [];
        // Typically returns elements like <NewDataSet><Hareket>...
        $rows = $historyXml->xpath('.//*[local-name()="Hareket"]') ?: $historyXml->xpath('.//*[local-name()="Table"]') ?: [];
        
        foreach ($rows as $row) {
            $movement = [];
            foreach ($row->children() as $child) {
                $movement[$child->getName()] = trim((string) $child);
            }
            if (!empty($movement)) {
                $movements[] = $movement;
            }
        }

        return $movements;
    }

    /**
     * Queries shipments made within a specific date range.
     * Date range cannot exceed 7 days.
     * Uses Customer Code & Web Password.
     *
     * @return array<array<string, string>>
     * @throws SuratKargoException
     */
    public function getShipments(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $diff = $from->diff($to);
        if ($diff->days > 7) {
            throw new SuratKargoException('Sürat Kargo sorguları en fazla 7 günlük aralıkla yapılabilir.');
        }

        $xml = $this->call('CariKoduveSifre', [
            'GonderenCariKodu' => $this->customerCode,
            'WebPassword' => $this->webPassword,
            'BasTar' => $from->format('Y-m-d'),
            'BitTar' => $to->format('Y-m-d'),
            'IsWebSiparisKoduOlsun' => 'true',
        ]);

        return $this->datasetRows($xml);
    }

    /**
     * Resolves raw status string from Sürat Kargo response to standard status keyword.
     *
     * @param string|null $status
     * @return string One of: 'delivered', 'out_for_delivery', 'branch', 'transfer', 'failed', 'returning', 'cancelled', 'approved'
     */
    public function resolveStatus(?string $status): string
    {
        $value = mb_strtolower(trim((string) $status), 'UTF-8');
        return match (true) {
            str_contains($value, 'teslim edildi') || str_contains($value, 'teslimat yapildi') => 'delivered',
            str_contains($value, 'dağıtıma') || str_contains($value, 'dagitima') || str_contains($value, 'kuryede') => 'out_for_delivery',
            str_contains($value, 'dağıtım şube') || str_contains($value, 'dagitim sube') || str_contains($value, 'şubede') || str_contains($value, 'subede') => 'branch',
            str_contains($value, 'transfer') || str_contains($value, 'aktarma') || str_contains($value, 'yolda') => 'transfer',
            str_contains($value, 'teslim edilemedi') || str_contains($value, 'teslim edilmedi') => 'failed',
            str_contains($value, 'iade') => 'returning',
            str_contains($value, 'iptal') || str_contains($value, 'pasif') => 'cancelled',
            str_contains($value, 'kabul') || str_contains($value, 'sevk') || str_contains($value, 'fatura') || str_contains($value, 'sipariş alındı') => 'approved',
            default => 'approved',
        };
    }

    /**
     * Queries COD (Kapıda Ödeme) payments made within a date range.
     *
     * @param DateTimeInterface $from
     * @param DateTimeInterface $to
     * @return array<array<string, string>>
     * @throws SuratKargoException
     */
    public function getCodPayments(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $xml = $this->call('KOdemeIslemleri', [
            'cariKodu' => $this->customerCode,
            'WebPassword' => $this->webPassword,
            'baslangicTarihi' => $from->format('Y-m-d\TH:i:s'),
            'bitisTarihi' => $to->format('Y-m-d\TH:i:s'),
        ]);

        $payments = [];
        $odemeNodes = $xml->xpath('.//*[local-name()="OdemeIslemleri"]') ?: [];
        foreach ($odemeNodes as $node) {
            $evrakNo = trim((string) ($node->xpath('.//*[local-name()="OdemeEvrakNo"]')[0] ?? ''));
            $aciklama = trim((string) ($node->xpath('.//*[local-name()="Aciklama"]')[0] ?? ''));
            
            $detayNodes = $node->xpath('.//*[local-name()="OdemeIslemleriDetay"]') ?: [];
            foreach ($detayNodes as $detay) {
                $row = [
                    'evrak_no' => $evrakNo,
                    'aciklama' => $aciklama,
                ];
                foreach ($detay->children() as $child) {
                    $row[$child->getName()] = trim((string) $child);
                }
                $payments[] = $row;
            }
        }

        return $payments;
    }

    /**
     * Queries packages returned by customers.
     *
     * @param DateTimeInterface $from
     * @param DateTimeInterface $to
     * @param string|null $reference Special web reference/order code
     * @return array<array<string, string>>
     * @throws SuratKargoException
     */
    public function getReturns(DateTimeInterface $from, DateTimeInterface $to, ?string $reference = null): array
    {
        $xml = $this->call('IadeKargolar', [
            'GondericiCariKodu' => $this->customerCode,
            'WebPassword' => $this->webPassword,
            'BasTar' => $from->format('Y-m-d'),
            'BitTar' => $to->format('Y-m-d'),
            'WebSiparisKodu' => $reference ?? '',
        ]);

        return $this->datasetRows($xml);
    }

    /**
     * Queries the list of out-of-area (Alan Dışı / Mobil Alan) neighborhood zones.
     *
     * @param string $neighborhoodId Optional neighborhood ID to filter
     * @return array<array<string, string>>
     * @throws SuratKargoException
     */
    public function getOutofAreaNeighborhoods(string $neighborhoodId = ''): array
    {
        $xml = $this->call('ATDisiMahalleListesi', [
            'GonderenCariKodu' => $this->customerCode,
            'Sifre' => $this->webPassword,
            'MahalleId' => $neighborhoodId,
        ]);

        return $this->datasetRows($xml);
    }

    /**
     * Logs message if logger is set.
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger && method_exists($this->logger, $level)) {
            $this->logger->{$level}($message, $context);
        }
    }

    /**
     * Executes a SOAP call with key-value parameters.
     */
    private function call(string $method, array $parameters): SimpleXMLElement
    {
        $nodes = '';
        foreach ($parameters as $name => $value) {
            $nodes .= sprintf('<%1$s>%2$s</%1$s>', $name, $this->escape((string) $value));
        }
        return $this->callRaw($method, $nodes);
    }

    /**
     * Executes a SOAP call with a raw XML string body.
     *
     * @throws SuratKargoException
     */
    private function callRaw(string $method, string $nodes): SimpleXMLElement
    {
        $soapEnvelope = '<?xml version="1.0" encoding="utf-8"?>'
            . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soap:Body>'
            . '<' . $method . ' xmlns="http://tempuri.org/">' . $nodes . '</' . $method . '>'
            . '</soap:Body>'
            . '</soap:Envelope>';

        $this->log('info', "Sürat Kargo SOAP Request: {$method}", ['body' => $soapEnvelope]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $soapEnvelope);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySsl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySsl ? 2 : 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: http://tempuri.org/' . $method,
            'Content-Length: ' . strlen($soapEnvelope)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $this->log('error', "Sürat Kargo SOAP cURL Error: {$error}", ['method' => $method]);
            throw new SuratKargoException("Sürat Kargo API bağlantı hatası (cURL): {$error}");
        }

        $this->log('info', "Sürat Kargo SOAP Response: {$method}", ['code' => $httpCode, 'body' => $response]);

        if ($httpCode >= 400 && empty($response)) {
            throw new SuratKargoException("Sürat Kargo API geçersiz HTTP yanıtı verdi: Kod {$httpCode}");
        }

        $xml = simplexml_load_string($response);
        if ($xml === false) {
            // Handle charset issues (Sürat Kargo sometimes returns Windows-1254 encoded characters in errors)
            $responseUtf8 = iconv('Windows-1254', 'UTF-8//IGNORE', $response) ?: $response;
            $responseUtf8 = preg_replace('/encoding="[^"]+"/i', 'encoding="utf-8"', $responseUtf8, 1) ?: $responseUtf8;
            $xml = simplexml_load_string($responseUtf8);
        }

        if ($xml === false) {
            throw new SuratKargoException('Sürat Kargo servisinden geçerli bir XML yanıtı alınamadı.');
        }

        // Check for SOAP Faults
        $fault = $xml->xpath('.//*[local-name()="Fault"]/*[local-name()="faultstring"]');
        if ($fault) {
            throw new SuratKargoException('SOAP Fault: ' . (string) $fault[0]);
        }

        return $xml;
    }

    /**
     * Parses standard ADO.NET DataSet rows from XML output.
     */
    private function datasetRows(SimpleXMLElement $xml): array
    {
        $rows = [];
        $dataSet = $xml->xpath('.//*[local-name()="NewDataSet"]/*') ?: [];
        
        foreach ($dataSet as $row) {
            $values = [];
            foreach ($row->children() as $child) {
                $values[$child->getName()] = trim((string) $child);
            }
            if (!empty($values)) {
                $rows[] = $values;
            }
        }

        return $rows;
    }

    /**
     * Extracts barcode values from shipment response XML.
     */
    private function extractBarcodes(SimpleXMLElement $xml): array
    {
        $barcodeRoots = $xml->xpath('.//*[local-name()="Barcode"]') ?: [];
        $barcodeNodes = $xml->xpath('.//*[local-name()="Barcode"]//*[text()]') ?: [];
        
        $list = array_merge(
            array_map(fn ($node) => trim((string) $node), $barcodeRoots),
            array_map(fn ($node) => trim((string) $node), $barcodeNodes)
        );

        return array_values(array_unique(array_filter($list)));
    }

    /**
     * Escapes XML special characters.
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Safely reads a nested node value from SimpleXMLElement.
     */
    private function value(SimpleXMLElement $xml, string $name): ?string
    {
        $nodes = $xml->xpath('.//*[local-name()="' . $name . '"]');
        return $nodes && trim((string) $nodes[0]) !== '' ? trim((string) $nodes[0]) : null;
    }
}
