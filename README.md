# 🌐 Digital Rehber — Yerel İşletme & Esnaf Dijital Rehber Platformu

> **Herhangi bir şehir veya ülke için beyaz etiket (white-label) olarak kullanılabilen, PHP + MySQL tabanlı profesyonel yerel işletme rehber platformu.**

---

## 📋 Proje Özeti

**Digital Rehber**, yerel esnaf ve işletmelerin dijital ortamda görünür olmasını sağlayan, kapsamlı bir web platformudur. İşletme listeleme, dijital menü, QR kartvizit, influencer rehberi, etkinlik takvimi, kampanya yönetimi, blog sistemi ve nöbetçi eczane modüllerini tek çatı altında sunar.

Platform; restoran, kafe, otomotiv, sağlık, giyim, eğitim, turizm ve daha birçok sektördeki yerel işletmeleri kategori, ilçe ve anahtar kelime bazında listeleme, arama ve filtreleme imkanı sağlar.

**Hedef Kitle:**
- Yerel işletmeler ve esnaflar (dijital varlık oluşturmak isteyenler)
- Tüketiciler (bölgedeki hizmetleri arayan kullanıcılar)
- İçerik üreticileri / Influencerlar
- Etkinlik organizatörleri
- Belediyeler ve yerel yönetimler

---

## 🏗️ Teknoloji Altyapısı

| Bileşen         | Teknoloji                                      |
|------------------|-------------------------------------------------|
| **Backend**      | PHP 8.0+ (Saf PHP, framework kullanılmıyor)    |
| **Veritabanı**   | MySQL 5.7+ / MariaDB 10.3+                     |
| **Web Sunucu**   | Apache + mod_rewrite                            |
| **Frontend**     | HTML5, CSS3 (Bootstrap), Vanilla JavaScript     |
| **Fontlar**      | Oswald + Plus Jakarta Sans (Google Fonts)       |
| **İkonlar**      | Font Awesome 6                                  |
| **Bağımlılıklar**| Composer (PHPMailer, HTML Purifier)             |
| **Güvenlik**     | CSRF koruması, Rate Limiting, XSS filtreleme, PDO Prepared Statements |
| **Bildirim**     | Telegram Bot API (isteğe bağlı)                |
| **Önbellek**     | Dosya tabanlı cache sistemi                     |
| **Oturum**       | PHP Session (güvenli yapılandırma)              |
| **SEO**          | Dinamik sitemap.xml, robots.txt, llms.txt, meta tag yönetimi |
| **E-posta**      | PHPMailer (SMTP desteği)                        |

---

## 📁 Dosya & Klasör Yapısı

```
digitalrehber/
│
├── config/                    # Yapılandırma dosyaları
│   ├── db.php                 #   Veritabanı bağlantı ayarları (PDO)
│   └── environment.php        #   .env dosya yükleyici sınıfı
│
├── core/                      # Çekirdek sistem sınıfları
│   ├── Cache.php              #   Dosya tabanlı önbellek yönetimi
│   ├── Database.php           #   PDO Singleton veritabanı bağlantısı
│   ├── Logger.php             #   Loglama sistemi
│   ├── Session.php            #   Güvenli oturum yönetimi
│   └── Validator.php          #   Form doğrulama sınıfı
│
├── models/                    # Veritabanı model sınıfları (ORM benzeri)
│   ├── BaseModel.php          #   Temel model sınıfı (CRUD)
│   ├── Business.php           #   İşletme modeli
│   ├── Favorite.php           #   Favori modeli
│   ├── Review.php             #   Değerlendirme/yorum modeli
│   └── User.php               #   Kullanıcı modeli
│
├── middleware/                # Ara katman yazılımları
│   ├── CSRFMiddleware.php     #   Cross-Site Request Forgery koruması
│   └── RateLimitMiddleware.php#   İstek hızı sınırlama
│
├── helpers/                   # Yardımcı sınıflar
│   └── SecurityHelper.php     #   XSS temizleme, escape fonksiyonları
│
├── includes/                  # Ortak bileşenler ve yardımcı fonksiyonlar
│   ├── header.php             #   Site üst bilgi, navigasyon menüsü
│   ├── footer.php             #   Site alt bilgi alanı
│   ├── seo-meta.php           #   Dinamik SEO meta tag yönetimi
│   ├── blog-helpers.php       #   Blog yardımcı fonksiyonları
│   ├── campaign-helpers.php   #   Kampanya yardımcı fonksiyonları
│   ├── district-helpers.php   #   İlçe sayfası yardımcı fonksiyonları
│   ├── duty-pharmacy-helpers.php # Nöbetçi eczane fonksiyonları
│   ├── event-helpers.php      #   Etkinlik yardımcı fonksiyonları
│   ├── influencer-helpers.php #   Influencer yardımcı fonksiyonları
│   ├── menu-helpers.php       #   Dijital menü fonksiyonları
│   ├── telegram-notify.php    #   Telegram bildirim sistemi
│   └── portal-legal-shell.php #   Yasal sayfa şablonu
│
├── admin/                     # 🔐 YÖNETİM PANELİ (30 dosya)
│   ├── login.php              #   Admin giriş sayfası
│   ├── index.php              #   Dashboard (istatistikler)
│   ├── businesses.php         #   İşletme yönetimi (CRUD)
│   ├── categories.php         #   Kategori yönetimi
│   ├── blogs.php              #   Blog yazısı yönetimi
│   ├── campaigns.php          #   Kampanya yönetimi
│   ├── events.php             #   Etkinlik yönetimi
│   ├── influencers.php        #   Influencer yönetimi
│   ├── influencer-talepler.php#   Influencer başvuru talepleri
│   ├── ads.php                #   Reklam banner yönetimi
│   ├── admins.php             #   Yönetici hesapları
│   ├── settings.php           #   Site genel ayarları
│   ├── seo.php                #   SEO meta ayarları
│   ├── messages.php           #   İletişim mesajları
│   ├── menu.php               #   İşletme menü yönetimi
│   ├── qrcodes.php            #   QR kod oluşturma
│   ├── hero-slides.php        #   Ana sayfa slider yönetimi
│   ├── ilceler.php            #   İlçe sayfa yönetimi
│   ├── regions.php            #   Bölge/şehir yönetimi
│   ├── reviews.php            #   Kullanıcı yorumları
│   ├── pages.php              #   Statik sayfa yönetimi (CMS)
│   ├── services.php           #   Hizmet sayfası yönetimi
│   ├── nobetci-eczane.php     #   Nöbetçi eczane API yönetimi
│   ├── logs.php               #   Admin işlem logları
│   ├── pending-changes.php    #   Onay bekleyen değişiklikler
│   ├── business-applications.php # İşletme başvuruları
│   ├── event-talepler.php     #   Etkinlik başvuru talepleri
│   ├── 2fa-setup.php          #   İki faktörlü doğrulama kurulumu
│   ├── 2fa-verify.php         #   İki faktörlü doğrulama onayı
│   └── logout.php             #   Çıkış
│
├── isletme/                   # 🏪 İŞLETME PANELİ (13 dosya)
│   ├── login.php              #   İşletme girişi
│   ├── index.php              #   İşletme dashboard
│   ├── profile.php            #   İşletme profil düzenleme
│   ├── gallery.php            #   Fotoğraf galerisi yönetimi
│   ├── menu.php               #   Menü ana sayfası
│   ├── menu-kategoriler.php   #   Menü kategori yönetimi
│   ├── menu-urunler.php       #   Menü ürün yönetimi
│   ├── campaigns.php          #   İşletme kampanyaları
│   ├── reviews.php            #   Müşteri yorumları
│   ├── qr.php                 #   QR kod oluşturma
│   ├── settings.php           #   İşletme ayarları
│   ├── force-password.php     #   Zorunlu şifre değiştirme
│   └── logout.php             #   Çıkış
│
├── public/                    # Statik dosyalar
│   ├── css/style.css          #   Ana stil dosyası
│   ├── js/main.js             #   Ana JavaScript dosyası
│   ├── images/                #   Yüklenen görseller (logo, kapak, galeri, menü)
│   ├── uploads/               #   Kullanıcı yüklemeleri
│   └── health-check.php       #   Sunucu sağlık kontrolü
│
├── scripts/                   # Yardımcı betikler
│   ├── seed_blogs.php         #   Örnek blog verileri
│   ├── seed_cyprus.php        #   Kıbrıs bölge verileri
│   ├── seed_turkey.php        #   Türkiye bölge verileri
│   ├── seed_demo_data.php     #   Demo işletme verileri
│   ├── seed_events.php        #   Örnek etkinlik verileri
│   ├── seed_duty_pharmacies.php # Eczane API test
│   └── verify-menu-images.py  #   Menü görselleri doğrulama (Python)
│
├── database/                  # Veritabanı migration dosyaları
├── backups/                   # Veritabanı yedekleri
├── cache/                     # Dosya tabanlı önbellek
├── logs/                      # Uygulama logları
│
├── .env.example               # Ortam değişkenleri şablonu
├── .gitignore                 # Git hariç tutma kuralları
├── .htaccess                  # Apache URL yönlendirme kuralları
├── autoload.php               # PSR-4 otomatik yükleyici
├── composer.json              # PHP bağımlılık yönetimi
├── database.sql               # Temiz veritabanı şeması (kurulum)
├── kurulum.txt                # Detaylı kurulum rehberi
│
├── index.php                  # 🏠 ANA SAYFA
├── esnaflar.php               # 📋 İşletme listeleme (arama, filtre)
├── esnaf.php                  # 📄 İşletme detay profili
├── menu.php                   # 🍽️ Dijital menü sayfası
├── qr.php                     # 📱 QR dijital kartvizit
├── blog.php                   # 📝 Blog listesi
├── blog-detay.php             # 📝 Blog detay
├── etkinlikler.php            # 🎭 Etkinlik listesi
├── etkinlik.php               # 🎭 Etkinlik detay
├── kampanyalar.php            # 🏷️ Kampanya listesi
├── kampanya.php               # 🏷️ Kampanya detay
├── influencerlar.php          # ⭐ Influencer listesi
├── influencer.php             # ⭐ Influencer profil
├── influencer-qr.php          # ⭐ Influencer QR kartvizit
├── influencer-basvuru.php     # ⭐ Influencer başvuru formu
├── influencer-kaldirma-talebi.php # ⭐ KVKK kaldırma talebi
├── nobetci-eczane.php         # 💊 Nöbetçi eczane sayfası
├── bolgeler.php               # 📍 Bölge/şehir sayfası
├── ilce.php                   # 📍 İlçe detay sayfası
├── isletme-basvuru.php        # 📝 İşletme başvuru formu
├── hizmetlerimiz.php          # 🛠️ Sunulan hizmetler
├── hakkimizda.php             # ℹ️ Hakkımızda
├── iletisim.php               # 📞 İletişim formu
├── giris.php                  # 🔑 Kullanıcı girişi
├── kayit.php                  # 🔑 Kullanıcı kaydı
├── profil.php                 # 👤 Kullanıcı profili
├── cikis.php                  # 🚪 Çıkış
├── gizlilik-politikasi.php    # 📜 Gizlilik politikası
├── kullanim-kosullari.php     # 📜 Kullanım koşulları
├── vizyon-misyon.php          # 📜 Vizyon & Misyon
├── sikca-sorulan-sorular.php  # ❓ SSS
├── sitemap.php                # 🗺️ Dinamik XML sitemap
├── robots.php                 # 🤖 Dinamik robots.txt
├── llms.php                   # 🤖 LLM bilgi dosyası (llms.txt)
├── 404.php                    # ⚠️ Hata sayfası
└── page_template.php          # 📄 Genel sayfa şablonu
```

---

## 🧩 Modüller ve Özellikler

### 1. 🏪 İşletme Rehberi (Ana Modül)
- İşletme listeleme, arama ve filtreleme (ilçe, kategori, anahtar kelime)
- Detaylı işletme profil sayfası (adres, telefon, WhatsApp, Instagram, TikTok, Facebook, web sitesi, Yemeksepeti)
- Google Haritalar entegrasyonu (iframe)
- İşletme fotoğraf galerisi
- Premium işletme vitrini (öne çıkarma)
- Kullanıcı yorumları ve puanlama
- İşletme başvuru formu

### 2. 🍽️ Dijital Menü Sistemi
- Restoran ve kafe menülerini online sergileme
- Menü kategorileri (Başlangıçlar, Ana Yemekler, İçecekler vb.)
- Ürün görseli, açıklama ve fiyat
- Sıralama ve aktif/pasif yönetimi
- İşletme panelinden self-servis düzenleme

### 3. 📱 QR Dijital Kartvizit
- Her işletme için benzersiz QR kodlu dijital kartvizit
- Telefon, WhatsApp, konum, menü, sosyal medya linkleri tek sayfada
- Özelleştirilebilir tema rengi
- `/{slug}` formatında temiz URL'ler
- Influencerlar için ayrı QR kartvizit sistemi (`/i/{slug}`)

### 4. ⭐ Influencer / İçerik Üretici Rehberi
- Influencer profil sayfaları (bio, sosyal medya, takipçi sayıları)
- Niş kategorileri (yemek, moda, seyahat, teknoloji vb.)
- Doğrulanmış profil ve premium vitrin
- Takipçi sayısı onaylama sistemi
- İşletme-influencer eşleştirme
- İşbirliği talep formu
- Başvuru formu (yeni influencer kaydı)
- KVKK uyumlu kaldırma talep sistemi

### 5. 🎭 Etkinlik Takvimi
- Konser, festival, spor, kültür etkinlikleri listeleme
- Etkinlik detay (tarih, saat, mekan, bilet, organizatör)
- İlçe bazlı filtreleme
- Öne çıkan etkinlikler
- Kullanıcı etkinlik başvuru formu

### 6. 🏷️ Kampanya Sistemi
- İndirim, fırsat ve kampanya listeleme
- İşletmeye bağlı kampanyalar
- Başlangıç-bitiş tarihi yönetimi
- Orijinal fiyat / indirimli fiyat gösterimi

### 7. 📝 Blog / İçerik Yönetimi (CMS)
- Blog yazısı oluşturma ve yönetimi
- SEO dostu URL'ler (`/blog/{slug}.html`)
- Özet, içerik, kapak görseli, meta açıklama
- Ana sayfada son yazılar bölümü

### 8. 📍 İlçe / Bölge Sayfaları
- Her ilçe için ayrı landing sayfası
- İlçeye ait işletme listesi, istatistikler
- Gezi rehberi bağlantıları
- SEO optimizeli meta etiketler

### 9. 💊 Nöbetçi Eczane (Türkiye — isteğe bağlı)
- EczaneAPI entegrasyonu
- Günlük otomatik senkronizasyon
- İlçe bazlı filtreleme
- Harita koordinatları (lat/lon)

### 10. 📢 Reklam Yönetimi
- Banner reklam sistemi
- Farklı pozisyonlarda gösterim (ana sayfa, sidebar vb.)
- Aktif/pasif yönetimi

### 11. 🔔 Telegram Bildirimleri
- İletişim formu bildirimleri
- Bot token ve chat ID ile yapılandırma
- İki ayrı bildirim kanalı desteği

---

## 🔐 Güvenlik Özellikleri

| Özellik                       | Açıklama                                           |
|-------------------------------|-----------------------------------------------------|
| **CSRF Koruması**             | Tüm formlarda token doğrulama                       |
| **Rate Limiting**             | Brute-force saldırı engelleme                       |
| **XSS Filtreleme**            | SecurityHelper ile HTML çıktı temizleme             |
| **SQL Injection Koruması**    | PDO Prepared Statements kullanımı                   |
| **HTML Purifier**             | Kullanıcı HTML içeriklerini temizleme               |
| **İki Faktörlü Doğrulama**   | Admin hesapları için 2FA (TOTP)                     |
| **Şifre Güvenliği**          | bcrypt hash ile şifre saklama                       |
| **Admin İşlem Logları**      | Tüm admin aktivitelerinin kaydı                     |
| **Güvenli Oturum Yönetimi**  | Session hijacking koruması                          |

---

## 🎨 Tasarım ve Arayüz

- **Responsive tasarım**: Mobil, tablet ve masaüstü uyumlu
- **Modern UI**: Bootstrap tabanlı, glassmorphism efektleri
- **Dinamik hero slider**: Admin panelinden yönetilebilir
- **Scroll animasyonları**: `reveal-on-scroll` efektleri
- **Özelleştirilebilir renkler**: Admin panelinden birincil renk değiştirme
- **Varsayılan renk paleti**:
  - Birincil: `#D62828`
  - İkincil: `#1F242B`
  - Vurgu: `#C9622B`

---

## 🛠️ SEO & Teknik Özellikler

- **Dinamik Sitemap** (`/sitemap.xml`): Tüm sayfalar, işletmeler, bloglar, etkinlikler otomatik
- **Robots.txt** (`/robots.txt`): Dinamik oluşturma
- **LLMs.txt** (`/llms.txt`): AI botları için site bilgi dosyası
- **Temiz URL'ler**: `.htaccess` ile SEO dostu URL yönlendirmeleri
  - `/esnaf/{slug}` — İşletme profili
  - `/menu/{slug}` — Dijital menü
  - `/{slug}` — QR kartvizit
  - `/blog/{slug}.html` — Blog yazısı
  - `/ilce/{slug}` — İlçe sayfası
  - `/influencer/{slug}` — Influencer profili
  - `/i/{slug}` — Influencer QR kartvizit
  - `/etkinlik/{slug}` — Etkinlik detay
  - `/kampanya/{slug}` — Kampanya detay
- **Meta tag yönetimi**: Her sayfa için dinamik title, description, keywords
- **UTF-8 karakter desteği**: Türkçe karakter tam uyumlu

---

## 👥 Kullanıcı Rolleri

| Rol             | Panel          | Yetkiler                                                |
|-----------------|----------------|----------------------------------------------------------|
| **Süper Admin** | `/admin`       | Tam yetki: tüm modüller, ayarlar, yönetici ekleme       |
| **Admin**       | `/admin`       | İşletme, blog, etkinlik, kampanya yönetimi               |
| **Editör**      | `/admin`       | Sınırlı düzenleme yetkileri                              |
| **İşletme**     | `/isletme`     | Kendi profilini, menüsünü, galeri ve kampanyalarını yönetme |
| **Kullanıcı**   | Ön yüz         | Kayıt, giriş, favori, yorum yapma                       |

---

## ⚙️ Kurulum (Kısa Özet)

### Gereksinimler
- PHP 8.0+ (PDO, curl, mbstring, json, session)
- MySQL 5.7+ / MariaDB 10.3+
- Apache + mod_rewrite
- Composer

### Adımlar
1. Proje dosyalarını web köküne kopyalayın
2. `database.sql` dosyasını phpMyAdmin ile import edin
3. `config/db.php` dosyasında veritabanı bilgilerini düzenleyin
4. `.env.example` dosyasını `.env` olarak kopyalayıp yapılandırın
5. `composer install` komutunu çalıştırın
6. `public/images/` klasörüne yazma izni verin
7. Tarayıcıdan siteyi açın

### Varsayılan Admin Girişi
```
URL     : /admin/login.php
Kullanıcı: admin
Şifre    : admin123
```
> ⚠️ İlk girişten sonra şifreyi mutlaka değiştirin!

---

## 🗄️ Veritabanı Tabloları (19 tablo)

| Tablo                              | Açıklama                          |
|------------------------------------|-------------------------------------|
| `categories`                       | İşletme kategorileri                |
| `businesses`                       | İşletme kayıtları                   |
| `business_images`                  | İşletme görselleri                  |
| `business_users`                   | İşletme panel kullanıcıları         |
| `menu_categories`                  | Menü kategorileri                   |
| `menu_items`                       | Menü ürünleri                       |
| `advertisements`                   | Reklam bannerları                   |
| `admins`                           | Yönetici hesapları                  |
| `admin_logs`                       | Admin işlem logları                 |
| `settings`                         | Site genel ayarları                 |
| `contact_messages`                 | İletişim mesajları                  |
| `blogs`                            | Blog yazıları                       |
| `services`                         | Hizmet sayfaları                    |
| `influencers`                      | Influencer profilleri               |
| `influencer_business_links`        | Influencer-işletme eşleştirme       |
| `influencer_collaboration_requests`| İşbirliği talepleri                 |
| `influencer_applications`          | Influencer başvuruları              |
| `influencer_removal_requests`      | KVKK kaldırma talepleri             |
| `events`                           | Etkinlikler                         |
| `event_business_links`             | Etkinlik-işletme eşleştirme         |
| `event_submissions`                | Etkinlik başvuruları                |
| `campaigns`                        | Kampanyalar                         |
| `district_pages`                   | İlçe sayfaları                      |
| `duty_pharmacies`                  | Nöbetçi eczane cache                |
| `duty_pharmacy_sync_logs`          | Eczane senkronizasyon logları       |

---

## 📦 Bağımlılıklar (Composer)

```json
{
  "phpmailer/phpmailer": "^6.8",    // E-posta gönderimi (SMTP)
  "ezyang/htmlpurifier": "^4.16"    // HTML içerik temizleme (XSS koruması)
}
```

---

## 🌍 Beyaz Etiket (White-Label) Yapısı

Bu platform herhangi bir şehir veya ülke için özelleştirilebilir:
- **Admin → Ayarlar** üzerinden site adı, logo, renk, açıklama değiştirilebilir
- **Admin → İlçeler/Bölgeler** üzerinden kendi bölge yapınız oluşturulabilir
- `.htaccess` dosyasından domain yönlendirmeleri ayarlanabilir
- Tüm metinler veritabanı üzerinden dinamik olarak yönetilir
- İçerik eklenmeden önce site tamamen boş ve temizdir — kurulum sonrası admin panelinden tüm içerik girilir

---

## 📬 İletişim ve Bildirim Kanalları

- **İletişim Formu**: CAPTCHA destekli, veritabanına kayıt
- **Telegram Bildirimleri**: İletişim formu gönderimlerinde anında bildirim
- **E-posta**: PHPMailer ile SMTP üzerinden mail gönderimi
- **WhatsApp**: İşletme profillerinde doğrudan WhatsApp bağlantısı

---

## 🚀 Canlı Ortam (Production) Kurulumu & E-posta Onay Sistemi

Platformu yerel sunucudan (localhost / XAMPP) canlı sunucuya taşıdığınızda e-posta (SMTP) sisteminin ve şifre sıfırlama özelliklerinin hatasız (500 hatası vermeden) çalışması için aşağıdaki kritik adımları izleyin:

### 0. Kritik Ön Koşul: Vendor Autoload Entegrasyonu (500 Hatası Çözümü)
Canlı sunucuda formu doldurduğunuzda veya mail atmaya çalıştığınızda `HTTP 500 Error` alıyorsanız bunun sebebi PHPMailer eklentisinin yüklenememesidir.
Bu sorunu çözmek için ana dizindeki **`autoload.php`** dosyasını açıp **28. Satırda** yer alan `// Load environment` kısmının hemen üstüne şu kodu eklemelisiniz:

```php
// Load composer dependencies if exists
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Load environment
require_once __DIR__ . '/config/environment.php';
```

### 1. Geliştirici Modunu (`dev_otp`) Kapatmak

Yerel geliştirme ortamlarında kolay test edilebilmesi için 6 haneli OTP kodu ekranda sarı bir uyarı kutusu içerisinde gösterilmektedir (`dev_otp`).

- **Otomatik Devre Dışı Kalma:**  
  Canlı sunucuya geçtiğinizde, sistem alan adınızı (`$_SERVER['SERVER_NAME']`) algılar; alan adınız `localhost` veya `127.0.0.1` dışındaki gerçek bir domain olduğunda **Geliştirici Modu otomatik olarak devre dışı kalır** ve ekrandaki sarı uyarı kutusu gizlenir (`dev_otp` değeri `null` döner).
- **Manuel Olarak Tamamen Kapatmak (Opsiyonel):**  
  Yerel ortamda veya test sunucusunda da ekranda şifre göstermeyi tamamen kapatmak isterseniz, [`services/PasswordResetService.php`](file:///c:/xampp/htdocs/digitalrehber/services/PasswordResetService.php#L83) dosyasını açıp satır 85 civarındaki kontrolü değiştirebilirsiniz:
  ```php
  // Mevcut kod (sadece localhost'ta açılır):
  $isLocalhost = in_array($_SERVER['SERVER_NAME'] ?? 'localhost', ['localhost', '127.0.0.1', '::1']);

  // Her zaman kapalı olmasını isterseniz:
  $isLocalhost = false;
  ```

### 2. Canlı Sunucuda Gerçek E-posta Onayı (PHPMailer / SMTP) Kurulumu

Sistemde Composer ile yüklü **PHPMailer** kütüphanesi hazır durumdadır. OTP kodlarının canlı sunucuda kullanıcıların ve işletmelerin e-posta adreslerine kurumsal şablonla gönderilmesi için [`services/PasswordResetService.php`](file:///c:/xampp/htdocs/digitalrehber/services/PasswordResetService.php#L82) dosyasındaki `requestOtp($email)` metodu içerisinde bulunan **`// 8. E-posta Gönderim Simülasyonu / Kancası`** bölümüne aşağıdaki kod bloğunu ekleyin:

```php
// 8. PHPMailer ile Gerçek SMTP E-posta Gönderimi (Canlı Sunucu İçin)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
try {
    // SMTP Sunucu Ayarları
    $mail->isSMTP();
    $mail->Host       = 'smtp.domainadi.com';        // SMTP Sunucu Adresiniz (örn: mail.siteniz.com)
    $mail->SMTPAuth   = true;
    $mail->Username   = 'noreply@domainadi.com';     // SMTP E-posta hesabınız
    $mail->Password   = 'SMTP_SIFRENIZ';             // SMTP E-posta şifreniz
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL için ENCRYPTION_SMTPS (465), TLS için ENCRYPTION_STARTTLS (587)
    $mail->Port       = 465;                         // SSL Portu: 465 | TLS Portu: 587
    $mail->CharSet    = 'UTF-8';

    // Gönderen & Alıcı Bilgileri
    $mail->setFrom('noreply@domainadi.com', 'Dijital Rehber');
    $mail->addAddress($email);

    // HTML E-posta İçeriği (Kurumsal Kırmızı Temalı Şablon)
    $mail->isHTML(true);
    $mail->Subject = 'Şifre Sıfırlama Doğrulama Kodu';
    $mail->Body    = "
        <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 25px; border: 1px solid #e2e8f0; border-radius: 12px; background-color: #ffffff;'>
            <h2 style='color: #D62828; text-align: center; margin-bottom: 15px;'>Şifre Sıfırlama Kodunuz</h2>
            <p style='color: #1a1a1a; font-size: 14px;'>Merhaba,</p>
            <p style='color: #4a5568; font-size: 14px; line-height: 1.5;'>
                Hesabınızın şifresini yenilemek için aşağıdaki 6 haneli doğrulama kodunu kullanabilirsiniz. Kodunuzun geçerlilik süresi <strong>5 dakikadır</strong>.
            </p>
            <div style='background: #fce8e8; color: #D62828; font-size: 32px; font-weight: bold; text-align: center; padding: 18px; border-radius: 10px; letter-spacing: 8px; margin: 25px 0;'>
                {$otpCode}
            </div>
            <p style='font-size: 12px; color: #718096; text-align: center; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 15px;'>
                Bu talebi siz gerçekleştirmediysanız, lütfen bu e-postayı dikkate almayın ve hesabınızın güvenliğini sağlayın.
            </p>
        </div>
    ";

    $mail->send();
} catch (Exception $e) {
    error_log('OTP E-posta Gönderim Hatası: ' . $mail->ErrorInfo);
}
```

> **İpucu:** Bu entegrasyonu yaptığınızda hem normal kullanıcılar (`sifremi-unuttum.php`) hem de işletme sahipleri (`isletme/sifremi-unuttum.php`) için e-posta onaylı şifre yenileme akışı canlı ortamda sorunsuz olarak çalışacaktır.

---

---

## 🚀 Gelişmiş SEO ve Reklam Altyapısı (Kıbrıs & Yerel Rehber Odaklı)

Sistem, Google ve sosyal medya platformlarında (Facebook, Instagram, X vb.) maksimum görünürlük elde etmek için "Büyük Portal" (Yemeksepeti, Sahibinden, Yelp) standartlarında dinamik bir SEO mimarisine sahiptir. Bu altyapı sayesinde yüzlerce işletme ve sayfa manuel müdahaleye gerek kalmadan otomatik optimize edilir.

### 1. JSON-LD Schema (Yapısal Veri)
Sistem Google'ın en çok tercih ettiği yapısal veri kodlaması olan **Schema.org** altyapısını kullanır:
- **Organization (Kurum):** Sitenizin iletişim bilgileri, logosu ve bölge konumu otomatik bildirilir.
- **WebSite & WebPage:** Hangi sayfada bulunulduğunu hiyerarşik olarak belirtir.
- **SearchAction:** Google arama sonuçlarında (SERP) sitenizin altında "Site İçi Arama Kutusu" çıkmasını tetikleyen özel yapılandırmadır.

### 2. Open Graph & Twitter Cards Entegrasyonu
Linkleriniz sosyal medyada ve WhatsApp'ta paylaşıldığında boş veya anlamsız görünmez:
- Her sayfa ve işletme için dinamik **og:title**, **og:description** ve **og:image** etiketleri üretilir.
- Sistem paylaşılan resmin türünü (MIME Tipi) otomatik tespit edip arama motorlarına iletir.
- Twitter (X) paylaşımları için "summary_large_image" formatı varsayılan olarak uygulanır.

### 3. Dinamik Meta Etiketler (Her Sayfaya Özel)
- **İşletme Detay:** İşletme adı, kategorisi ve bulunduğu ilçeye göre otomatik Title ve Description oluşturulur (Örn: *Ahmet Usta - Girne Otomotiv | Dijital Rehber*).
- **Filtre ve Kategori Sayfaları:** Seçilen ilçe ve kategoriye göre sayfa başlığı otomatik değişir.
- **Dinamik Bölge Sayfaları:** Bölgeler için panelden girilen özel tanıtım yazıları doğrudan SEO açıklamalarına aktarılır.
- **Blog Stratejisi:** Eklenen her blog yazısının kendi özel OpenGraph görseli ve meta başlığı bulunur. Google Reklamları açılış sayfaları için mükemmeldir.

### 4. Sayfalandırma & Kopya İçerik Kontrolü (Canonical & NoIndex)
- Google'ın sevmediği "kopya içerik" sorununu çözmek adına, filtreleme uygulanan arama sayfalarına anında **noindex** etiketi eklenir.
- Her sayfanın orijinal kaynağını belirten **canonical** linki otomatik olarak kodun en üstüne yerleştirilir.

### 5. Footer Anahtar Kelime / Hashtag Sistemi
- Admin panelindeki *SEO ve Arama Motoru Yönetimi* sekmesinden eklenen virgülle ayrılmış site anahtar kelimeleri, Footer (Alt Bilgi) alanında şık hashtagler (örn: `#kibrisesnaf`) formatında tüm sayfalara dağıtılarak iç SEO (Internal Linking & Keyword Density) skoru güçlendirilir.

---

## 📄 Lisans

Bu proje beyaz etiket bir yerel rehber sistemidir. Kullanım hakları yazılımı size gönderen kişiye aittir.

---

## 🧑‍💻 Geliştirici

**Zahir Güzel**
- GitHub: [github.com/zahirguzel](https://github.com/zahirguzel)

---

