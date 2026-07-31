<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';
require_once __DIR__ . '/includes/seo-meta.php';
require_once __DIR__ . '/includes/portal-legal-shell.php';

$pageTitle = 'Gizlilik Politikası';
$_siteTitle = seoGetSiteTitle();
$metaDescription = $_siteTitle . ' gizlilik politikası: kişisel verilerin toplanması, kullanımı, saklanması ve KVKK kapsamındaki haklarınız.';
$metaKeywords = 'gizlilik politikası, kvkk, kişisel veriler, ' . strtolower($_siteTitle);
$canonicalUrl = seoGetBaseUrl() . '/gizlilik-politikasi';
require_once 'includes/header.php';

$siteName = SecurityHelper::escape($siteSettings['site_title'] ?? seoGetSiteTitle());
$contactEmail = SecurityHelper::escape($siteSettings['contact_email'] ?? '');

$sections = [
    [
        'id' => 'veri-sorumlusu',
        'num' => '01',
        'title' => 'Veri Sorumlusu',
        'icon' => 'fa-building-shield',
        'content' => '<p>6698 sayılı Kişisel Verilerin Korunması Kanunu (KVKK) kapsamında veri sorumlusu <strong>' . $siteName . '</strong> platformudur.</p><p>İletişim: <a href="mailto:' . $contactEmail . '">' . $contactEmail . '</a></p>',
    ],
    [
        'id' => 'toplanan-veriler',
        'num' => '02',
        'title' => 'Toplanan Veriler',
        'icon' => 'fa-database',
        'content' => '<p>Platformumuz aracılığıyla aşağıdaki veriler toplanabilir:</p>
            <ul class="legal-check-list">
                <li><strong>İletişim formu:</strong> ad soyad, e-posta, telefon, mesaj içeriği</li>
                <li><strong>İşletme kayıt başvurusu:</strong> işletme adı, adres, iletişim ve profil bilgileri</li>
                <li><strong>Influencer başvurusu:</strong> ad, e-posta, ilçe, sosyal medya linkleri, biyografi ve profil yayın onayı</li>
                <li><strong>Influencer iş birliği talebi:</strong> marka/işletme adı, yetkili bilgileri, kampanya mesajı</li>
                <li><strong>Etkinlik yayınlama başvurusu:</strong> başvuran adı, e-posta, telefon, etkinlik adı, tarih/saat, mekân, ilçe, açıklama, bilet bilgisi, görsel linki ve KVKK onayı</li>
                <li><strong>Teknik veriler:</strong> IP adresi, tarayıcı türü, oturum logları (güvenlik amaçlı)</li>
                <li><strong>Analitik veriler:</strong> Google Analytics gibi araçlarla anonim kullanım istatistikleri</li>
            </ul>',
    ],
    [
        'id' => 'kullanim-amaclari',
        'num' => '03',
        'title' => 'Verilerin Kullanım Amaçları',
        'icon' => 'fa-bullseye',
        'content' => '<ul class="legal-check-list">
                <li>Rehber hizmetinin sunulması ve işletme profillerinin yönetilmesi</li>
                <li>Etkinlik takviminde duyuru yayınlanması ve başvuru değerlendirmesi</li>
                <li>İletişim taleplerinin yanıtlanması</li>
                <li>Platform güvenliğinin sağlanması</li>
                <li>Yasal yükümlülüklerin yerine getirilmesi</li>
                <li>Site performansının ve kullanıcı deneyiminin iyileştirilmesi</li>
            </ul>',
    ],
    [
        'id' => 'veri-paylasimi',
        'num' => '04',
        'title' => 'Verilerin Paylaşımı',
        'icon' => 'fa-share-nodes',
        'content' => '<p>Kişisel verileriniz, yasal zorunluluklar dışında üçüncü kişilerle ticari amaçla paylaşılmaz. Hizmet altyapısı (hosting, e-posta) sağlayıcıları yalnızca teknik gereklilik ölçüsünde veriye erişebilir.</p>',
    ],
    [
        'id' => 'cerezler',
        'num' => '05',
        'title' => 'Çerezler (Cookies)',
        'icon' => 'fa-cookie-bite',
        'content' => '<p>Sitemiz, oturum yönetimi ve analitik amaçlarla çerez kullanabilir. Tarayıcı ayarlarınızdan çerezleri yönetebilir veya devre dışı bırakabilirsiniz; bu durumda bazı özellikler kısıtlanabilir.</p>',
    ],
    [
        'id' => 'saklama-suresi',
        'num' => '06',
        'title' => 'Veri Saklama Süresi',
        'icon' => 'fa-clock',
        'content' => '<p>Veriler, işleme amacının gerektirdiği süre boyunca ve yasal saklama yükümlülükleri çerçevesinde muhafaza edilir; süre sonunda silinir, yok edilir veya anonim hale getirilir.</p>',
    ],
    [
        'id' => 'haklariniz',
        'num' => '07',
        'title' => 'Haklarınız',
        'icon' => 'fa-user-shield',
        'content' => '<p>KVKK madde 11 kapsamında kişisel verilerinizle ilgili bilgi talep etme, düzeltme, silme, itiraz ve şikâyet haklarına sahipsiniz.</p><p>Taleplerinizi <a href="mailto:' . $contactEmail . '">' . $contactEmail . '</a> adresine iletebilirsiniz.</p>',
    ],
    [
        'id' => 'guvenlik',
        'num' => '08',
        'title' => 'Güvenlik',
        'icon' => 'fa-lock',
        'content' => '<p>Verilerinizin yetkisiz erişime karşı korunması için makul teknik ve idari önlemler alınmaktadır.</p>',
    ],
    [
        'id' => 'influencer-kvkk',
        'num' => '09',
        'title' => 'Influencer Rehberi ve KVKK',
        'icon' => 'fa-star',
        'content' => '<p><strong>Influencer / içerik üretici rehberi</strong> kapsamında aşağıdaki ilkelere uyulur:</p>
            <ul class="legal-check-list">
                <li>Profil yalnızca içerik üreticisinin <strong>yazılı onayı</strong> ile yayınlanır (isim, fotoğraf, biyografi, sosyal medya linkleri).</li>
                <li>Takipçi sayıları <strong>manuel doğrulama</strong> ile güncellenir; otomatik veya doğrulanmamış rakamlar gösterilmez.</li>
                <li>Doğrulanmış profiller <strong>' . $siteName . '</strong> rozeti ile işaretlenir.</li>
                <li>Profil sahipleri <a href="influencer-kaldirma-talebi">kaldırma veya düzeltme talebi</a> formu ile KVKK madde 11 haklarını kullanabilir.</li>
                <li>Kaldırma talepleri en geç <strong>30 gün</strong> içinde değerlendirilir; onaylanan profiller yayından kaldırılır veya düzeltilir.</li>
            </ul>
            <p>Başvuru formu: <a href="influencer-basvuru">Influencer profil başvurusu</a></p>',
    ],
    [
        'id' => 'etkinlik-kvkk',
        'num' => '10',
        'title' => 'Etkinlik Takvimi ve KVKK',
        'icon' => 'fa-calendar-days',
        'content' => '<p><strong>' . $siteName . ' etkinlik takvimi</strong> kapsamında başvuru yapan kişi ve kurumların verileri aşağıdaki ilkelere göre işlenir:</p>
            <ul class="legal-check-list">
                <li>Başvuru formunda paylaştığınız iletişim bilgileri yalnızca <strong>etkinlik duyurusu değerlendirmesi</strong>, gerekirse sizinle iletişim kurulması ve yayın sürecinin yürütülmesi amacıyla kullanılır.</li>
                <li>Etkinlik bilgileri (ad, tarih, mekân, açıklama, bilet/ücret vb.) editör onayı sonrası sitede <strong>herkese açık</strong> etkinlik sayfasında yayımlanabilir; yayın kararı tamamen platforma aittir.</li>
                <li>Onaylanmayan veya reddedilen başvurularda kişisel veriler, saklama süresi ve yasal yükümlülükler dışında gereksiz yere tutulmaz.</li>
                <li>Yayınlanan etkinliklerde başvuranın adı zorunlu olarak gösterilmez; yalnızca etkinliğe ait duyuru bilgileri (organizasyon, mekân, program vb.) paylaşılır.</li>
                <li>Düzeltme, silme veya itiraz taleplerinizi <a href="mailto:' . $contactEmail . '">' . $contactEmail . '</a> adresine veya <a href="iletisim">İletişim</a> formu üzerinden iletebilirsiniz; KVKK madde 11 kapsamındaki haklarınız saklıdır.</li>
            </ul>
            <p>Başvuru formu: <a href="etkinlik-basvuru">Etkinlik yayınlama başvurusu</a></p>',
    ],
    [
        'id' => 'degisiklikler',
        'num' => '11',
        'title' => 'Değişiklikler',
        'icon' => 'fa-file-pen',
        'content' => '<p>Bu politika güncellenebilir. Güncel metin her zaman bu sayfada yayımlanır.</p>',
    ],
];

renderPortalLegalPage([
    'eyebrow' => 'Gizlilik & KVKK',
    'title' => 'Gizlilik Politikası',
    'lead' => 'Kişisel verilerinizin nasıl toplandığı, kullanıldığı ve korunduğu hakkında şeffaf bilgilendirme metni.',
    'intro_icon' => 'fa-shield-halved',
    'intro_title' => 'Kişisel Verileriniz Bizim İçin Önemli',
    'intro_text' => 'Bu Gizlilik Politikası, ' . $siteName . ' web sitesini ziyaret eden kullanıcılar ile iletişim formu aracılığıyla bilgi paylaşan kişiler için geçerlidir.',
    'sections' => $sections,
    'footer_icon' => 'fa-envelope-open-text',
    'footer_title' => 'Sorularınız mı var?',
    'footer_text' => 'Gizlilik veya KVKK ile ilgili talepleriniz için <a href="mailto:' . $contactEmail . '">' . $contactEmail . '</a> adresine yazabilir veya <a href="/iletisim">İletişim</a> sayfamızı kullanabilirsiniz.',
]);

require_once 'includes/footer.php';
