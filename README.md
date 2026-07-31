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

## 📄 Lisans

Bu proje beyaz etiket bir yerel rehber sistemidir. Kullanım hakları yazılımı size gönderen kişiye aittir.

---

## 🧑‍💻 Geliştirici

**Zahir Güzel**
- GitHub: [github.com/zahirguzel](https://github.com/zahirguzel)

---

