<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';
require_once __DIR__ . '/includes/seo-meta.php';
require_once __DIR__ . '/includes/portal-legal-shell.php';

$pageTitle = 'Kullanım Koşulları';
$_siteTitle = seoGetSiteTitle();
$metaDescription = $_siteTitle . ' kullanım koşulları: site kullanım kuralları, sorumluluk sınırları, fikri mülkiyet ve işletme listeleme şartları.';
$metaKeywords = 'kullanım koşulları, site şartları, ' . strtolower($_siteTitle) . ', yasal';
$canonicalUrl = seoGetBaseUrl() . '/kullanim-kosullari';
require_once 'includes/header.php';

$siteName = SecurityHelper::escape($siteSettings['site_title'] ?? seoGetSiteTitle());
$contactEmail = SecurityHelper::escape($siteSettings['contact_email'] ?? '');

$sections = [
    [
        'id' => 'hizmet-tanimi',
        'num' => '01',
        'title' => 'Hizmet Tanımı',
        'icon' => 'fa-compass',
        'content' => '<p>Platform; ' . SecurityHelper::escape(seoGetRegionName()) . '\'daki işletmeleri listeleyen, kullanıcıların arama yapmasını sağlayan ve işletmelere dijital profil, QR menü ve vitrin hizmetleri sunan bir yerel rehber sitesidir.</p>',
    ],
    [
        'id' => 'kullanici-yukumlulukleri',
        'num' => '02',
        'title' => 'Kullanıcı Yükümlülükleri',
        'icon' => 'fa-user-check',
        'content' => '<ul class="legal-check-list">
                <li>Siteyi yalnızca yasal amaçlarla kullanmak</li>
                <li>İletişim formunda doğru ve güncel bilgi vermek</li>
                <li>Platforma zarar verecek, yanıltıcı veya hukuka aykırı içerik göndermemek</li>
                <li>Diğer kullanıcıların haklarına saygı göstermek</li>
            </ul>',
    ],
    [
        'id' => 'isletme-listeleme',
        'num' => '03',
        'title' => 'İşletme Listeleme',
        'icon' => 'fa-store',
        'content' => '<p>Rehbere eklenen işletme bilgileri (ad, adres, telefon, açıklama, görseller) işletme sahibi veya yetkili kişi tarafından sağlanır. Yanlış veya güncelliğini yitirmiş bilgilerin düzeltilmesi için bizimle iletişime geçilmesi gerekir.</p>
            <p>Platform, üçüncü taraf işletmelerin sunduğu hizmetlerin kalitesi, fiyatı veya yasal uygunluğu konusunda garanti vermez; kullanıcı ile işletme arasındaki ilişki doğrudan taraflar arasındadır.</p>',
    ],
    [
        'id' => 'fikri-mulkiyet',
        'num' => '04',
        'title' => 'Fikri Mülkiyet',
        'icon' => 'fa-copyright',
        'content' => '<p>Sitenin tasarımı, yazılımı, metinleri ve markası <strong>' . $siteName . '</strong>\'ya aittir. İzinsiz kopyalama, çoğaltma veya ticari kullanım yasaktır. İşletme logoları ve içerikleri ilgili hak sahiplerine aittir.</p>',
    ],
    [
        'id' => 'dis-baglantilar',
        'num' => '05',
        'title' => 'Dış Bağlantılar',
        'icon' => 'fa-arrow-up-right-from-square',
        'content' => '<p>İşletme profillerinde Instagram, WhatsApp, Google Haritalar ve benzeri üçüncü taraf sitelere bağlantılar bulunabilir. Bu sitelerin içerik ve politikalarından platform sorumlu değildir.</p>',
    ],
    [
        'id' => 'hizmet-degisiklikleri',
        'num' => '06',
        'title' => 'Hizmet Değişiklikleri',
        'icon' => 'fa-arrows-rotate',
        'content' => '<p>Platform özellikleri, fiyatlandırma ve listeleme koşulları önceden bildirim yapılmaksızın güncellenebilir. Önemli değişiklikler sitede duyurulur.</p>',
    ],
    [
        'id' => 'sorumluluk-siniri',
        'num' => '07',
        'title' => 'Sorumluluk Sınırı',
        'icon' => 'fa-scale-balanced',
        'content' => '<p>Site "olduğu gibi" sunulmaktadır. Kesintisiz veya hatasız çalışma taahhüdü verilmez. Mücbir sebep hallerinde hizmet geçici olarak durabilir.</p>',
    ],
    [
        'id' => 'uyusmazlik',
        'num' => '08',
        'title' => 'Uyuşmazlık',
        'icon' => 'fa-gavel',
        'content' => '<p>Bu koşullardan doğan uyuşmazlıklarda Türkiye Cumhuriyeti kanunları uygulanır. Yetkili mahkeme ve icra daireleri ' . SecurityHelper::escape(seoGetRegionName()) . ' mahkemeleridir.</p>',
    ],
    [
        'id' => 'iletisim',
        'num' => '09',
        'title' => 'İletişim',
        'icon' => 'fa-envelope',
        'content' => '<p>Sorularınız için: <a href="mailto:' . $contactEmail . '">' . $contactEmail . '</a> veya <a href="/iletisim">İletişim</a> sayfamız.</p>',
    ],
];

renderPortalLegalPage([
    'eyebrow' => 'Yasal',
    'title' => 'Kullanım Koşulları',
    'lead' => 'Platformu kullanırken geçerli olan kurallar, sorumluluk sınırları ve işletme listeleme şartları.',
    'intro_icon' => 'fa-file-contract',
    'intro_title' => 'Site Kullanım Kuralları',
    'intro_text' => $siteName . ' web sitesini kullanarak aşağıdaki koşulları kabul etmiş sayılırsınız. Koşulları kabul etmiyorsanız siteyi kullanmayınız.',
    'sections' => $sections,
    'footer_icon' => 'fa-circle-question',
    'footer_title' => 'Koşullar hakkında soru mu var?',
    'footer_text' => 'Kullanım koşulları veya işletme listeleme şartlarıyla ilgili talepleriniz için <a href="mailto:' . $contactEmail . '">' . $contactEmail . '</a> adresine yazabilir veya <a href="/iletisim">İletişim</a> sayfamızı kullanabilirsiniz.',
]);

require_once 'includes/footer.php';
