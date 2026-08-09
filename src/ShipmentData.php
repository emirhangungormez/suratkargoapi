<?php

namespace SuratKargo;

use InvalidArgumentException;

class ShipmentData
{
    public string $recipientName; // KisiKurum & SahisBirim
    public string $address; // AliciAdresi
    public string $city; // Il
    public string $district; // Ilce
    public ?string $phoneMobile = null; // TelefonCep
    public ?string $phoneHome = null; // TelefonEv
    public ?string $phoneWork = null; // TelefonIs
    public ?string $email = null; // Email
    public ?string $recipientCode = null; // AliciKodu (e.g., Customer ID or Order ID)
    
    public int $cargoType = 2; // KargoTuru (1: Dosya/Evrak, 2: Paket/Koli)
    public int $paymentType = 1; // OdemeTipi (1: Gonderici Odemeli / Sender Pays, 2: Alici Odemeli / Receiver Pays)
    public string $waybillSeries = 'DKC'; // IrsaliyeSeriNo
    public ?string $waybillNumber = null; // IrsaliyeSiraNo
    public string $referenceNo; // ReferansNo / OzelKargoTakipNo (Must be unique!)
    
    public int $packageCount = 1; // Adet
    public float $desi = 1.0; // BirimDesi
    public float $weight = 1.0; // BirimKg
    public ?string $contentDescription = null; // KargoIcerigi
    
    public int $codType = 0; // KapidanOdemeTahsilatTipi (0: Yok / None, 1: Nakit / Cash, 2: Kredi Karti / Credit Card)
    public float $codAmount = 0.0; // KapidanOdemeTutari
    
    public ?string $extraServices = null; // EkHizmetler
    public int $transportType = 1; // TasimaSekli (1: Karayolu / Road, 2: Havayolu / Air)
    public int $deliveryType = 1; // TeslimSekli (1: Adrese Teslim / Address Delivery, 2: Subeden Teslim / Branch Pickup)
    public ?string $alternativeDeliveryAddress = null; // SevkAdresi
    public int $shipmentMode = 1; // GonderiSekli (Usually 1)
    public ?string $deliveryBranchCode = null; // TeslimSubeKodu
    public int $isMarketplace = 0; // Pazaryerimi (0: Hayir, 1: Evet)
    public string $integrationCompany = 'API'; // EntegrasyonFirmasi
    public bool $isReturn = false; // Iademi
    public ?string $pickupTime = null; // AlimSaati

    /**
     * ShipmentData constructor.
     * 
     * @param string $recipientName Recipient or company name (KisiKurum / SahisBirim)
     * @param string $address Recipient full address (AliciAdresi)
     * @param string $city Recipient city (Il)
     * @param string $district Recipient district (Ilce)
     * @param string $phoneMobile Recipient mobile phone (TelefonCep)
     * @param string $referenceNo Unique tracking/reference number (ReferansNo)
     */
    public function __construct(
        string $recipientName,
        string $address,
        string $city,
        string $district,
        string $phoneMobile,
        string $referenceNo
    ) {
        $this->recipientName = $recipientName;
        $this->address = $address;
        $this->city = $city;
        $this->district = $district;
        $this->phoneMobile = $this->normalizePhone($phoneMobile);
        $this->referenceNo = $referenceNo;
    }

    /**
     * Normalizes Turkish phone numbers to 10 digits (e.g. 5xxxxxxxxx)
     */
    public function normalizePhone(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }
        
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        
        if (str_starts_with($digits, '90') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = substr($digits, 1);
        }
        
        return $digits;
    }

    /**
     * Validates shipment fields before XML construction.
     * 
     * @throws InvalidArgumentException
     */
    public function validate(): void
    {
        if (empty(trim($this->recipientName))) {
            throw new InvalidArgumentException("Alıcı adı (recipientName) boş bırakılamaz.");
        }
        if (empty(trim($this->address))) {
            throw new InvalidArgumentException("Alıcı adresi (address) boş bırakılamaz.");
        }
        if (empty(trim($this->city))) {
            throw new InvalidArgumentException("Alıcı ili (city) boş bırakılamaz.");
        }
        if (empty(trim($this->district))) {
            throw new InvalidArgumentException("Alıcı ilçesi (district) boş bırakılamaz.");
        }
        if (empty($this->phoneMobile) || strlen($this->phoneMobile) !== 10) {
            throw new InvalidArgumentException("Geçersiz alıcı cep telefonu. 10 haneli (örn: 5xxxxxxxxx) olmalıdır.");
        }
        if (empty(trim($this->referenceNo))) {
            throw new InvalidArgumentException("Referans numarası (referenceNo) boş bırakılamaz.");
        }
        if ($this->packageCount < 1) {
            throw new InvalidArgumentException("Paket adedi (packageCount) en az 1 olmalıdır.");
        }
        if ($this->desi <= 0) {
            throw new InvalidArgumentException("Desi (desi) 0'dan büyük olmalıdır.");
        }
        if ($this->weight <= 0) {
            throw new InvalidArgumentException("Ağırlık (weight) 0'dan büyük olmalıdır.");
        }
        if ($this->codType > 0 && $this->codAmount <= 0) {
            throw new InvalidArgumentException("Kapıda ödemeli gönderilerde tutar (codAmount) 0'dan büyük olmalıdır.");
        }
    }

    /**
     * Converts properties into Sürat Kargo SOAP XML parameters structure.
     */
    public function toSoapXml(): string
    {
        $this->validate();

        $fields = [
            'KisiKurum' => $this->recipientName,
            'SahisBirim' => $this->recipientName,
            'AliciAdresi' => $this->address,
            'Il' => $this->city,
            'Ilce' => $this->district,
            'TelefonEv' => $this->phoneHome ?? '',
            'TelefonIs' => $this->phoneWork ?? '',
            'TelefonCep' => $this->phoneMobile,
            'Email' => $this->email ?? '',
            'AliciKodu' => $this->recipientCode ?? $this->referenceNo,
            'KargoTuru' => $this->cargoType,
            'OdemeTipi' => $this->paymentType,
            'IrsaliyeSeriNo' => $this->waybillSeries,
            'IrsaliyeSiraNo' => $this->waybillNumber ?? $this->referenceNo,
            'ReferansNo' => $this->referenceNo,
            'OzelKargoTakipNo' => $this->referenceNo,
            'Adet' => $this->packageCount,
            'BirimDesi' => number_format($this->desi, 2, '.', ''),
            'BirimKg' => number_format($this->weight, 2, '.', ''),
            'KargoIcerigi' => $this->contentDescription ?? '',
            'KapidanOdemeTahsilatTipi' => $this->codType,
            'KapidanOdemeTutari' => number_format($this->codAmount, 2, '.', ''),
            'EkHizmetler' => $this->extraServices ?? '',
            'TasimaSekli' => $this->transportType,
            'TeslimSekli' => $this->deliveryType,
            'SevkAdresi' => $this->alternativeDeliveryAddress ?? '',
            'GonderiSekli' => $this->shipmentMode,
            'TeslimSubeKodu' => $this->deliveryBranchCode ?? '',
            'Pazaryerimi' => $this->isMarketplace,
            'EntegrasyonFirmasi' => $this->integrationCompany,
            'Iademi' => $this->isReturn ? 'true' : 'false',
            'AlimSaati' => $this->pickupTime ?? '',
        ];

        $xml = '';
        foreach ($fields as $key => $value) {
            $xml .= sprintf('<%1$s>%2$s</%1$s>', $key, htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        }

        return $xml;
    }
}
