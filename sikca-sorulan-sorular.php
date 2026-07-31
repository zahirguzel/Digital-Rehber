<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';
require_once __DIR__ . '/includes/seo-meta.php';

$pageTitle = 'Sıkça Sorulan Sorular';
$metaDescription = 'Şehir Rehberi hakkında sık sorulan sorular: işletme kaydı, premium vitrin, QR dijital profil, dijital menü ve iletişim bilgileri.';
$metaKeywords = strtolower(seoGetSiteTitle()) . ' sss, işletme kaydı, qr menü, premium vitrin, dijital profil, ' . strtolower(seoGetRegionName()) . ' rehber';

$faqs = [
    [
        'q' => 'Şehir Rehberi nedir?',
        'a' => 'şehirdeki esnaf ve işletmeleri tek platformda listeleyen dijital şehir rehberidir. Kullanıcılar ilçe, kategori veya anahtar kelime ile arama yapabilir; işletmeler ise dijital profil ve vitrin hizmetlerinden yararlanabilir.',
    ],
    [
        'q' => 'İşletmemi rehbere nasıl ekleyebilirim?',
        'a' => 'İletişim sayfamızdaki formu doldurarak veya bizi arayarak başvuru yapabilirsiniz. Ekibimiz işletme bilgilerinizi alır, profilinizi oluşturur ve onay sonrası yayına alır.',
    ],
    [
        'q' => 'Premium vitrin nedir, ne sağlar?',
        'a' => 'Premium vitrin, işletmenizin ana sayfa ve arama sonuçlarında öne çıkarılmasını sağlar. Logo, açıklama, iletişim kanalları ve QR dijital profil entegrasyonu ile daha görünür bir temsil sunar.',
    ],
    [
        'q' => 'QR dijital profil nasıl çalışır?',
        'a' => 'Her işletmeye özel bir QR kod ve kısa link tanımlanır. Müşteriler kodu okuttuğunda menü, telefon, WhatsApp, konum ve sosyal medya bağlantılarına tek ekrandan ulaşır.',
    ],
    [
        'q' => 'Dijital menü hizmetiniz var mı?',
        'a' => 'Evet. Restoran ve kafe işletmeleri için kategorili dijital menü oluşturulabilir. Menü QR profil üzerinden veya ayrı menü linki ile erişilebilir.',
    ],
    [
        'q' => 'Listeleme ücretsiz mi?',
        'a' => 'Temel rehber kaydı ve standart profil seçenekleri hakkında güncel koşullar için bizimle iletişime geçmeniz yeterlidir. Premium vitrin ve dijital hizmetler için ayrı paketler sunulmaktadır.',
    ],
    [
        'q' => 'Bilgilerimi nasıl güncellerim?',
        'a' => 'Telefon, adres, menü veya logo değişikliklerini iletişim kanallarımız üzerinden bildirmeniz yeterlidir. Dijital menü paneli olan işletmeler kendi menülerini panelden güncelleyebilir.',
    ],
    [
        'q' => 'Kişisel verilerim nasıl korunuyor?',
        'a' => 'KVKK kapsamında kişisel verileriniz yalnızca hizmet sunumu ve iletişim amacıyla işlenir. Detaylar için Gizlilik Politikası sayfamızı inceleyebilirsiniz.',
    ],
];

$canonicalUrl = seoGetBaseUrl() . '/sikca-sorulan-sorular';
$schemaFAQ = seoBuildFAQPageSchema($faqs, $canonicalUrl);
require_once 'includes/header.php';
?>

<script type="application/ld+json"><?= json_encode($schemaFAQ, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<header class="directory-portal-hero directory-portal-hero--faq">
    <div class="directory-portal-hero__backdrop" aria-hidden="true">
        <div class="directory-portal-hero__panel directory-portal-hero__panel--guide"></div>
        <div class="directory-portal-hero__panel directory-portal-hero__panel--media"></div>
    </div>
    <div class="container directory-portal-hero__inner">
        <div class="directory-portal-hero__head reveal-on-scroll">
            <div>
                <span class="portal-eyebrow">Yardım Merkezi</span>
                <h1 class="directory-portal-hero__title">Sıkça Sorulan Sorular</h1>
                <p class="directory-portal-hero__lead">Rehber, kayıt, QR profil ve dijital hizmetler hakkında en çok merak edilen soruların yanıtları.</p>
            </div>
            <div class="directory-portal-hero__stat">
                <strong><?= count($faqs) ?></strong>
                <span>Soru</span>
            </div>
        </div>
    </div>
</header>

<section class="portal-section portal-section--muted faq-portal-main">
    <div class="container">
        <div class="faq-portal-layout reveal-on-scroll">
            <div class="faq-portal-list" id="faqAccordion">
                <?php foreach ($faqs as $i => $faq):
                    $num = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
                ?>
                <article class="faq-portal-item">
                    <h2 class="faq-portal-item__head">
                        <button class="faq-portal-item__toggle<?= $i === 0 ? ' is-open' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse<?= $i ?>" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="faqCollapse<?= $i ?>">
                            <span class="faq-portal-item__num"><?= $num ?></span>
                            <span class="faq-portal-item__question"><?= SecurityHelper::escape($faq['q']) ?></span>
                            <i class="fa-solid fa-chevron-down faq-portal-item__icon" aria-hidden="true"></i>
                        </button>
                    </h2>
                    <div id="faqCollapse<?= $i ?>" class="collapse<?= $i === 0 ? ' show' : '' ?>" data-bs-parent="#faqAccordion">
                        <div class="faq-portal-item__body">
                            <?= SecurityHelper::escape($faq['a']) ?>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>

            <aside class="faq-portal-cta">
                <span class="portal-section__eyebrow">Destek</span>
                <h2>Aradığınız yanıtı bulamadınız mı?</h2>
                <p>Ekibimiz işletme kaydı, dijital hizmetler ve rehber kullanımı hakkında size yardımcı olmaya hazır.</p>
                <div class="faq-portal-cta__actions">
                    <a href="/iletisim" class="btn btn-primary fw-semibold"><i class="fa-solid fa-paper-plane me-2"></i> Bize Ulaşın</a>
                    <a href="/gizlilik-politikasi" class="btn btn-outline-primary fw-semibold">Gizlilik & KVKK</a>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
