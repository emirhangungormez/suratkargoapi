# Sürat Kargo Web Service SOAP API SDK

[![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.0-8892BF.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Zero Dependencies](https://img.shields.io/badge/dependencies-zero-blue.svg)](#)

Türkiye'nin en popüler kargo şirketlerinden **Sürat Kargo**'nun resmî bir PHP SDK'sı veya düzgün bir entegrasyon kılavuzu internette bulunmamaktadır. 

Bu kütüphane, Sürat Kargo SOAP Web Servisi (`https://webservices.suratkargo.com.tr/services.asmx?WSDL`) üzerinde işlem yapmayı sağlayan, **sıfır bağımlılığa (zero-dependency)** sahip, PHP native `ext-soap` eklentisine dahi ihtiyaç duymadan **cURL XML SOAP** protokolünü kullanan son derece esnek bir PHP SDK'sıdır.

Hem **insan yazılımcılar** hem de **AI kodlama asistanları** (Gemini, Claude, Cursor, Copilot vb.) için en ideal entegrasyon yapısını sunar.

---

## 🚀 Önemli Özellikler

1. **Bağımsız SOAP Motoru:** `ext-soap` eklentisi yüklü olmayan sunucularda da cURL ve SimpleXML ile doğrudan XML mesajları oluşturarak çalışır.
2. **Dayanıklı Gönderi Cascade Akışı:** Sürat Kargo API yetkilerinizin türüne göre gönderiyi önce barkodlu oluşturmayı dener (`GonderiyiKargoyaGonderYeniSiparisBarkodOlustur`), yetki yoksa ortak barkodlu oluşturur (`OrtakBarkodOlustur`), o da olmazsa ham gönderi olarak kaydeder (`GonderiyiKargoyaGonderYeni`). Entegrasyonunuz asla yarıda kesilmez.
3. **Akıllı Telefon Normalizasyonu:** Telefon numaralarını otomatik olarak temizler ve Sürat Kargo API'sinin beklediği 10 haneli (`5XXXXXXXXX`) formata getirir.
4. **E-Ticaret Durum Çözümleyici:** Sürat Kargo'dan dönen Türkçe durum ibarelerini (`dağıtıma çıkarıldı`, `teslim edildi`, `aktarma şubesinde` vb.) standart e-ticaret durumlarına (`out_for_delivery`, `delivered`, `transfer` vb.) çevirir.

---

## ⚠️ En Büyük Entegrasyon Tuzağı: Kimlik Bilgileri (Credentials)

Sürat Kargo entegrasyonu yapan geliştiricilerin karşılaştığı **en büyük hata**, tüm API fonksiyonlarında tek bir kullanıcı adı ve şifre kullanmaya çalışmalarıdır. Sürat Kargo sisteminde **iki ayrı yetkilendirme katmanı** vardır:

| Parametre Adı (SDK) | SOAP Karşılığı | Kullanım Alanı | Açıklama |
| :--- | :--- | :--- | :--- |
| **`shipping_username`** | `KullaniciAdi` | **Gönderi Oluşturma & İptal** | Kargo göndermek, sipariş oluşturmak ve geri çekmek için kullanılan kargo şubesi tanımlı API kullanıcı adı. |
| **`shipping_password`** | `Sifre` | **Gönderi Oluşturma & İptal** | Gönderi API kullanıcısına ait şifre. |
| **`customer_code`** | `cariKodu` / `GonderenCariKodu` | **Sorgulama & Barkod & İptal** | Kargo hareketlerini takip etmek, geçmiş listelemek ve barkod etiketi indirmek için kullanılan Cari Kodu (Müşteri Numarası). |
| **`web_password`** | `WebPassword` / `Sifre` | **Sorgulama & Barkod & İptal** | Cari hesabınıza bağlı olan web entegrasyon paneli şifresi. |

> [!IMPORTANT]
> Gönderi oluştururken `shipping_username`/`shipping_password`; sorgulama, takip ve barkod çekerken ise `customer_code`/`web_password` kullanılmalıdır. Bu SDK, bu ayrımları arka planda otomatik olarak yönetir.

---

## ⚙️ Kurulum

Kütüphaneyi projenize Composer kullanarak dahil edebilirsiniz:

```bash
composer require emirhangungormez/suratkargoapi
```

---

## 📖 Temel Kullanım

### 1. Kargo Gönderisi Oluşturma (Shipment Creation)

Aşağıdaki örnekte en dayanıklı şekilde kargo siparişi oluşturma işlemi gösterilmektedir. SDK, kargo kaydını oluştururken API yetkilerinize bağlı olarak en uygun SOAP metodunu sırayla (cascade) dener.

```php
<?php

use SuratKargo\SuratKargoClient;
use SuratKargo\ShipmentData;
use SuratKargo\SuratKargoException;

require_once 'vendor/autoload.php';

// SDK İstemcisini Başlatma
$client = new SuratKargoClient([
    'shipping_username' => 'API_USER_NAME',
    'shipping_password' => 'API_PASSWORD',
    'customer_code'     => 'CUSTOMER_CARI_CODE',
    'web_password'      => 'WEB_PANEL_PASSWORD',
    'verify_ssl'        => true // Can be false in sandbox environment
]);

try {
    // E-Ticaret sisteminizdeki benzersiz sipariş veya gönderi referansı
    $orderReference = 'ORDER-' . time();

    // DTO Nesnesini Tanımlama (Zorunlu parametreler constructor'da yer alır)
    $shipment = new ShipmentData(
        recipientName: 'Ahmet Yılmaz',
        address: 'Atatürk Mahallesi, Fatih Caddesi No:45 Daire:3',
        city: 'İstanbul',
        district: 'Ataşehir',
        phoneMobile: '0 (555) 123 45 67', // SDK bunu "5551234567" olarak normalleştirir
        referenceNo: $orderReference
    );

    // İsteğe bağlı diğer parametreleri ayarlama
    $shipment->email = 'ahmet.yilmaz@example.com';
    $shipment->packageCount = 1;     // Paket sayısı
    $shipment->desi = 2.5;           // Desi değeri
    $shipment->weight = 1.8;         // Ağırlık KG
    $shipment->contentDescription = 'Kıyafet Siparişi';
    
    // Kapıda Ödemeli Sipariş ise:
    // $shipment->codType = 1;       // 1: Nakit, 2: Kredi Kartı
    // $shipment->codAmount = 350.00; // Tahsil edilecek tutar

    // API İstek Gönderme
    $result = $client->createShipment($shipment);

    echo "Kargo Başarıyla Gönderildi!\n";
    echo "Takip No: " . ($result['tracking_number'] ?? 'Şubede Oluşacak') . "\n";
    echo "Barkod Kodu: " . ($result['barcode'] ?? 'N/A') . "\n";
    echo "Kullanılan SOAP Metodu: " . $result['method_used'] . "\n";

} catch (SuratKargoException $e) {
    echo "Hata: " . $e->getMessage();
}
```

---

### 2. Kargo Takibi & Durum Çözümleme (Tracking & Status Resolution)

Sürat Kargo API'sinden dönen takip bilgileri Türkçe metin şeklindedir. SDK, bu metinleri e-ticaret sitenizin veritabanına uyacak şekilde standartlaştırır.

```php
<?php

use SuratKargo\SuratKargoClient;

$client = new SuratKargoClient([/* config */]);

try {
    $referenceNo = 'ORDER-123456';

    // 1. Detaylı kargo hareket geçmişini çekme
    $movements = $client->trackShipment($referenceNo);
    
    foreach ($movements as $step) {
        echo "Tarih: " . $step['Tarih'] . " | Konum: " . $step['Birim'] . " | İşlem: " . $step['Islem'] . "\n";
    }

    // 2. Genel durum bilgisi çekme ve durum çözümleme
    $general = $client->queryByReference($referenceNo);
    if (!isset($general['Mesaj'])) {
        $rawStatus = $general['Durum'] ?? $general['SonDurum'] ?? null;
        
        // Türkçe durumları standart durumlara çevirir:
        // 'delivered', 'out_for_delivery', 'branch', 'transfer', 'failed', 'returning', 'cancelled', 'approved'
        $resolvedStatus = $client->resolveStatus($rawStatus);
        
        echo "Takip Numarası: " . $general['KargoTakipNo'] . "\n";
        echo "E-Ticaret Durumu: " . $resolvedStatus . "\n"; // Örn: out_for_delivery
    }

} catch (Exception $e) {
    echo "Takip Hatası: " . $e->getMessage();
}
```

---

### 3. Barkod Etiketi & Yazıcı Kodları Çekme (ZPL & PDF Labels)

Gönderiyi oluşturduktan sonra kargo poşetinin üzerine yapıştırmak için PDF etiketini veya termal yazıcılar (Zebra vb.) için ZPL kodlarını alabilirsiniz.

```php
<?php
use SuratKargo\SuratKargoClient;

$client = new SuratKargoClient([/* config */]);
$barcodeData = $client->getBarcode('ORDER-123456');

// Base64 formatında PDF etiketi
$pdfBase64 = $barcodeData['pdf'];
if ($pdfBase64) {
    file_put_contents('kargo_etiket.pdf', base64_decode($pdfBase64));
    echo "PDF etiket kaydedildi.\n";
}

// Termal Yazıcı (ZPL) kodları array'i
$zplCodes = $barcodeData['printer_codes'];
foreach ($zplCodes as $zpl) {
    // Ham ZPL kodunu termal yazıcıya gönderebilirsiniz
    echo "Yazıcı Kodu: " . $zpl . "\n";
}
```

> [!TIP]
> Bilgisayarınızda termal yazıcı yoksa, ZPL kodunu görsele çevirip test etmek için `examples/zpl_preview.php` içerisindeki **Labelary API** entegrasyonu örneğini kullanabilirsiniz.

---

### 4. Kargo İptal Etme (Cancellation)

Gönderilmekten vazgeçilen veya müşterinin iptal ettiği kargo kayıtlarını iptal etmek için iki yöntem bulunur:

```php
<?php
use SuratKargo\SuratKargoClient;

$client = new SuratKargoClient([/* config */]);
$reference = 'ORDER-123456';

// YÖNTEM 1 (Tavsiye Edilen): Cari Yetkisi ile Kaydı Tamamen Silme
try {
    $result = $client->cancelShipment($reference);
    echo "İptal Mesajı: " . $result . "\n";
} catch (Exception $e) {
    // YÖNTEM 2: Şube Gönderim Yetkisi ile Gönderiyi Geri Çekme
    $result = $client->withdrawShipment($reference, 'Müşteri siparişi iptal etti.');
    echo "Geri Çekme Mesajı: " . $result . "\n";
}
```

---

### 5. Kapıda Ödeme ve İade Sorgulama (COD & Returns)

Kapıda ödemeli siparişlerinizin tahsilat durumunu sorgulamak, müşterilerden gelen iade paketlerini takip etmek ve alan dışı şubeleri tespit etmek için sunulan metotlar:

```php
<?php
use SuratKargo\SuratKargoClient;

$client = new SuratKargoClient([/* config */]);

// 1. Kapıda Ödeme Tahsilat Raporu (Son 7 Gün)
$from = new DateTime('-7 days');
$to = new DateTime();
$codPayments = $client->getCodPayments($from, $to);

foreach ($codPayments as $payment) {
    echo "Net Ödenen: " . $payment['OdenecekTutar'] . " TL | Ref: " . $payment['WebSiparisKodu'] . "\n";
}

// 2. Müşteri İade Kargolarını Sorgulama
$returns = $client->getReturns($from, $to);
foreach ($returns as $row) {
    echo "İade Siparişi: " . $row['WebSiparisKodu'] . " | Takip No: " . $row['KargoTakipNo'] . "\n";
}

// 3. Mobil Alan / Alan Dışı Mahalleleri Sorgulama
$outofAreas = $client->getOutofAreaNeighborhoods();
```

---

### 6. PSR-3 Loglama Desteği (Logging)

Sürat Kargo API isteklerini, dönen XML yanıtlarını ve cURL bağlantı hatalarını Monolog veya Laravel'in `Log` facade'i gibi herhangi bir PSR-3 uyumlu log motorunu kullanarak otomatik olarak kayıt altına alabilirsiniz.

```php
<?php
use SuratKargo\SuratKargoClient;

$client = new SuratKargoClient([
    'shipping_username' => 'test_kullanici',
    'shipping_password' => 'test_sifre',
    'customer_code'     => '123456789',
    'web_password'      => 'web_sifre_123',
    'logger'            => Log::getLogger() // Herhangi bir PSR-3 Logger nesnesi
]);
```

---

### 7. Toplu Sorgulama ve Webhook Simülasyonu (Batch Query & Webhooks)

Sürat Kargo API'si doğrudan Webhook (anlık HTTP bildirimi) desteklemez. Ancak Geliver, İkas gibi sistemlerin yaptığı gibi kendi sunucunuzda anlık durum değişikliklerini tetiklemek için SDK'nın toplu sorgulama gücünü kullanabilirsiniz.

`queryBatch()` metodu, tek bir SOAP istek paketinde 100 adete kadar kargoyu aynı anda sorgular. Bu, tek tek sorgu atmaktan (rate-limit / yavaşlık) kurtarır.

```php
<?php
use SuratKargo\SuratKargoClient;

$client = new SuratKargoClient([/* config */]);

// Veritabanınızda durumu 'taşıma' durumunda olan sipariş referansları
$activeOrders = ['ORDER-1001', 'ORDER-1002', 'ORDER-1003'];

// Tek istekte hepsini sorgula
$results = $client->queryBatch($activeOrders);

foreach ($results as $row) {
    $ref = $row['WebSiparisKodu'];
    $rawStatus = $row['Durum'] ?? $row['SonDurum'] ?? null;
    $resolvedStatus = $client->resolveStatus($rawStatus);

    // Eğer veritabanınızdaki eski durum ile $resolvedStatus farklıysa:
    // 1. Veritabanını güncelleyin
    // 2. Kendi sisteminize veya diğer mikroservislerinize POST (Webhook) atın
}
```

> [!TIP]
> Bu akışın tam çalışan, durum değişim kontrolü içeren ve webhook tetikleyen simülasyonunu incelemek için `examples/webhook_simulation.php` dosyasını kullanabilirsiniz.

---

## 🏷️ Ön Kabul / Özel Barkod Eşleştirme Sistemi (Pre-Acceptance Barcode System)

Sürat Kargo, bireysel veya standart e-ticaret entegrasyonlarına API üzerinden anlık barkod üretme yetkisini (`GonderiyiKargoyaGonderYeniSiparisBarkodOlustur`) **neredeyse hiçbir zaman doğrudan tanımlamaz**. İkas, Ticimax veya özel yazılmış büyük e-ticaret altyapıları bile Sürat Kargo entegrasyonlarını "Ön Kabul" yöntemiyle çözmektedir.

### Mantık Nasıl Çalışır?

1. **API İstek Aşaması:** `createShipment()` metodunu çağırdığınızda, SDK arka planda tüm gönderi bilgilerini ve sizin ürettiğiniz benzersiz **`OzelKargoTakipNo / ReferansNo`** (Örn: `ORDER-123456`) değerini Sürat Kargo sistemine iletir. Kargo kaydı başarılı şekilde oluşur ancak API size hiçbir kargo takip numarası veya barkod resmi **döndürmez**.
2. **Yerel Barkod Üretim Aşaması:** Sizin sisteminiz (veya e-ticaret altyapınız), ürettiğiniz bu benzersiz `OzelKargoTakipNo / ReferansNo` değerini barkoda dönüştürür.
3. **Etiket Basımı:** Bu referans kodunun barkodunu içeren kargo etiketini yazdırıp paketin üzerine yapıştırırsınız.
4. **Şube Okutma ve Eşleşme (Kritik Nokta):** Paket kargo şubesine gittiğinde, şube görevlisi paketin üzerindeki barkodu (yani sizin `OzelKargoTakipNo` değerinizi) kendi terminali ile okutur. Sürat Kargo sistemi bu kodu veritabanında arar, ön kabul kaydını bulur ve kargoyu kabul eder. Bu kabul anında, Sürat Kargo kendi sisteminde gerçek bir **`KargoTakipNo`** üretir ve sizin referans numaranızla otomatik olarak ilişkilendirir.
5. **Takip ve Senkronizasyon:** Siz daha sonra `queryByReference('ORDER-123456')` veya `trackShipment('ORDER-123456')` sorgusu yaptığınızda, Sürat Kargo API'si size o siparişe bağlı olarak oluşan gerçek takip numarasını ve tüm şube hareketlerini başarıyla döndürür.

### Örnek ZPL Termal Yazıcı Etiketi Üretimi

Eğer barkod yetkiniz yoksa, şubede taranacak özel barkodunuzu ZPL formatında şu şekilde üretebilirsiniz:

```php
<?php
// Şubede okutulduğunda sistemde kargonun bulunmasını sağlayacak referans numarası
$barcodeValue = preg_replace('/[^A-Za-z0-9\-]/', '', $shipment->referenceNo);

$zpl = "^XA\n"
    . "^PW800\n" // Genişlik
    . "^LL600\n" // Yükseklik
    . "^FO60,50^A0N,36,36^FDSURAT KARGO^FS\n" // Başlık
    . "^FO60,110^BCN,180,Y,N,N^FD{$barcodeValue}^FS\n" // Referans Numarası Barkodu
    . "^FO60,340^A0N,28,28^FDAlici: " . substr($shipment->recipientName, 0, 40) . "^FS\n"
    . "^FO60,390^A0N,24,24^FDSiparis Ref: " . substr($shipment->referenceNo, 0, 40) . "^FS\n"
    . "^XZ";

// Bu ZPL kodunu doğrudan termal yazıcıya gönderebilirsiniz
header('Content-Type: application/zpl');
header('Content-Disposition: attachment; filename="' . $shipment->referenceNo . '.zpl"');
echo $zpl;
```

---

## 🤖 Yapay Zekâ (AI) Entegrasyon Kılavuzu

Eğer bu kütüphaneyi projenize entegrasyon yapmak için bir **AI Kod Asistanı** (Cursor, Gemini, Claude, ChatGPT vb.) kullanıyorsanız, AI asistanınıza aşağıdaki promptu vererek hatasız entegrasyonlar yazdırabilirsiniz:

> **AI Prompt Örneği:**
> *"Sürat Kargo entegrasyonu yapmak istiyorum. Projemde `emirhangungormez/suratkargoapi` PHP SDK'sı kurulu durumda. Benim `Order` ve `Shipping` modellerim var. Sipariş onaylandığında kargo kaydını oluşturacak, veritabanına tracking_number ve reference_no kaydedecek, kargo durumunu saatlik senkronize edip durum 'teslim edildi' olduğunda siparişi tamamlayacak bir Laravel Service sınıfı ve Console Command yaz. SDK'nın `SuratKargoClient` sınıfını kullan. API kimlik bilgileri için `.env` dosyasındaki değerleri oku ve `SuratKargoClient::resolveStatus` fonksiyonuyla durumları benim `delivered`, `out_for_delivery` durumlarımla eşleştir."*

---

## 📑 SOAP WSDL Fonksiyon Listesi & Parametreleri

Kütüphane içinde kullanılan ve WSDL dosyasında (`https://webservices.suratkargo.com.tr/services.asmx?WSDL`) yer alan başlıca operasyonların listesi:

* **`GonderiyiKargoyaGonderYeniSiparisBarkodOlustur`**: En güncel sipariş ve barkod oluşturma metodu. DTO içindeki tüm alıcı bilgilerini ve kargo detaylarını alarak doğrudan takip numarası ve barkod nesnesi döner.
* **`OrtakBarkodOlustur`**: Kargo şubesi tarafından ortak havuz barkodu kullanılması zorunlu kılınan hesaplar için ortak barkod üretilmesini sağlayan yedek metod.
* **`GonderiyiKargoyaGonderYeni`**: Barkod oluşturma yetkisi bulunmayan standart cari hesaplar için sadece veri kaydı oluşturan metot.
* **`KargoBarkodu`**: Oluşturulmuş bir kargonun PDF etiketini ve ZPL/yazıcı çıktılarını çekmek için kullanılır.
* **`GonderiSil`**: Cari kodu ve web entegrasyon şifresiyle kargo gönderisini sistemden siler.
* **`GonderiGeriCek`**: API kullanıcı bilgileri ve iptal nedeni belirtilerek kargoyu geri çeker.
* **`WebSiparisKodu`**: Gönderilen bir referans koduna ait kargonun durumunu tekil olarak sorgular.
* **`WebSiparisKoduToplu`**: Birden fazla sipariş referansını tek bir SOAP istek paketiyle sorgular. Toplu senkronizasyon ve webhook tetikleme süreçlerinde kullanılır.
* **`KOdemeIslemleri`**: Cari kod ve web şifresiyle belirli tarih aralığındaki kapıda ödeme (COD) tahsilat ve mutabakat dökümlerini döndürür.
* **`ATDisiMahalleListesi`**: Kargo şirketinin normal dağıtım alanı dışındaki (mobil teslimat yapılan) kırsal/özel bölgeleri sorgulamak için kullanılır.
* **`KargoTakipHareketDetayliV2`**: Kargonun hangi şubede, hangi işlemden geçtiğini (tarih, saat ve şube bilgisiyle) detaylı XML dizisi halinde döndürür.
* **`CariKoduveSifre`**: İki tarih aralığındaki (maks. 7 gün) tüm gönderilerin listesini ve genel durumlarını döndürür.

---

## 🛡️ Güvenlik ve Defansif Entegrasyon (Security & Defense)

Kargo entegrasyonları, XML tabanlı SOAP yapıları nedeniyle çeşitli siber saldırılara hedef olabilir. Bu SDK, güvenliğinizi sağlamak için varsayılan olarak defansif önlemler içerir.

### 1. XXE (XML External Entity) Koruması
*   **Tehdit:** Saldırganlar, SOAP yanıtları veya takip verileri arasına dış varlık (external entity) tanımları yerleştirerek sunucu üzerindeki yerel dosyaları (`file:///etc/passwd` vb.) okumaya çalışabilir.
*   **SDK Önlemi:** SDK, gelen tüm ham XML yanıtlarını ayrıştırmadan (parse etmeden) önce tarar. Eğer XML içerisinde `<!DOCTYPE` veya `<!ENTITY` ifadeleri algılanırsa işlemi durdurur ve `SuratKargoException` fırlatır.

### 2. XML Bomb (Billion Laughs / DoS) Koruması
*   **Tehdit:** Derin iç içe geçmiş XML entity tanımları (XML bomb), parser'ın belleği tüketerek sunucunun kilitlenmesine (Denial of Service) neden olabilir.
*   **SDK Önlemi:** DTD ve Dış Varlık çözümlemeleri XML yükleme aşamasında tamamen engellenmiştir. Ayrıca ham veri taranarak recursive entity tanımları doğrudan reddedilir.

### 3. SOAPAction Header Spoofing Koruması
*   **Tehdit:** HTTP başlığındaki (Header) `SOAPAction` değeri ile XML gövdesindeki (Body) metot adının farklı olması durumunda sunucuların yetkisiz metotları çalıştırması sağlanabilir.
*   **SDK Önlemi:** SDK, istek oluştururken `SOAPAction` başlığını ve XML gövdesini birebir eşleştirerek gönderir.

### 4. WSDL ve Bilgi İfşası (Reconnaissance)
*   **Tehdit:** Sürat Kargo'nun veya sizin entegrasyon katmanınızın WSDL dosyalarının public olması, saldırganların tüm metotları ve parametre tiplerini keşfetmesini sağlar.
*   **Güvenlik Tavsiyesi:** Kendi uygulamanızda kargo entegrasyon detaylarını dış dünyaya açan yönlendirmeleri (routing) ve logları korumaya alın. Canlı ortam kimlik bilgilerini asla Git depolarına eklemeyin; `.env` dosyası kullanarak şifreleyin.

### 5. SSL Doğrulaması (MitM Koruması)
*   **Tehdit:** Ortadaki Adam (Man-in-the-Middle) saldırılarıyla verilerin araya girilerek okunması veya manipüle edilmesi.
*   **SDK Önlemi:** `verify_ssl => true` varsayılan olarak etkindir. Local test ortamları dışında bu değeri asla `false` yapmayın. Eğer `false` yapılırsa SDK sistem loglarına güvenlik uyarısı (warning) yazar.

---

## 📜 Lisans

Bu proje **MIT Lisansı** ile lisanslanmıştır. Dilediğiniz gibi kişisel veya ticari projelerinizde kullanabilir, değiştirebilir ve dağıtabilirsiniz.
