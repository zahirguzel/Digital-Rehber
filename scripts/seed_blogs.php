<?php
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/seo-meta.php';

mb_internal_encoding('UTF-8');

$pdo = Database::getInstance()->getPDO();
$region = function_exists('seoGetRegionName') ? seoGetRegionName() : 'Şehir';
$siteTitle = function_exists('seoGetSiteTitle') ? seoGetSiteTitle() : 'Şehir Rehberi';

echo "=== Dinamik Blog Yazıları Seed İşlemi Başlıyor ===\n";
echo "Aktif Bölge: {$region} | Site Başlığı: {$siteTitle}\n\n";

try {
    $pdo->exec("DELETE FROM blogs");
    try {
        $pdo->exec("ALTER TABLE blogs AUTO_INCREMENT = 1");
    } catch (Exception $e) {}
    echo "Eski blog kayıtları temizlendi.\n";
} catch (Exception $e) {
    echo "Tablo temizlenirken uyarı: " . $e->getMessage() . "\n";
}

$blogPosts = [
    [
        'title' => "{$region} Gastronomi ve Lezzet Rehberi: En Sevilen Yöresel Tatlar",
        'slug' => 'gastronomi-ve-lezzet-rehberi-en-sevilen-yoresel-tatlar',
        'summary' => "{$region} mutfağının vazgeçilmez lezzetleri, meşhur tatlıları, esnaf lokantaları ve yerel restoran önerileri hakkında detaylı yeme-içme rehberi.",
        'content' => "{$region}, zengin mutfak kültürü ve kendine has yöresel lezzetleriyle hem yerel halkın hem de ziyaretçilerin vazgeçilmez lezzet duraklarından biridir. Geleneksel tariflerden modern restoran sunumlarına kadar geniş bir yelpaze sunan mutfağımız, her damak zevkine hitap ediyor.\n\n" .
                     "Öne Çıkan Yöresel Lezzetler\n\n" .
                     "Şehrimizin gastronomik kimliğini oluşturan tatları deneyimlerken özellikle geleneksel esnaf lokantalarını ve usta ellerden çıkan lezzetleri tercih etmenizi öneririz.\n\n" .
                     "Geleneksel Ana Yemekler: Yöresel baharatlar ve yerli üretim malzemelerle hazırlanan fırın yemekleri ve tencere lezzetleri.\n" .
                     "Meşhur Tatlılar: Yemeklerin ardından damakları tatlandıran, nesillerdir yaşatılan geleneksel tatlı çeşitleri.\n" .
                     "Kahvaltı Kültürü: Yerel peynirler, yöresel reçeller, sıcak pide ve çöreklerle donatılan hafta sonu kahvaltı sofraları.\n\n" .
                     "Bölgedeki en iyi restoranları, kafeleri ve tatlıcıları bulmak için {$region} Yeme & İçme Rehberi sayfamızı inceleyebilir, menü, konum ve iletişim bilgilerine anında ulaşabilirsiniz.",
        'image_path' => 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=1200&q=80',
        'meta_description' => "{$region} mutfağının meşhur yöresel yemekleri, tatlıları ve en iyi restoran tavsiyeleri.",
        'meta_keywords' => mb_strtolower($region, 'UTF-8') . " mutfağı, " . mb_strtolower($region, 'UTF-8') . " yemekleri, " . mb_strtolower($region, 'UTF-8') . " restoranlar, yöresel lezzetler"
    ],
    [
        'title' => "{$region} Gezilecek Yerler ve Hafta Sonu Rotaları",
        'slug' => 'gezilecek-en-guzel-yerler-ve-hafta-sonu-rotalari',
        'summary' => "{$region} sınırları içindeki tarihi çarşılar, doğal güzellikler, sahil rotaları ve hafta sonu gezileri için ideal tur planı.",
        'content' => "{$region}, tarihi zenginlikleri, canlı caddeleri, doğal parkları ve sahil yürüyüş rotalarıyla hafta sonu gezileri için eşsiz imkanlar sunar. İster kültür turu ister sakin bir hafta sonu dinlencesi planlayın, bu rehber işinizi kolaylaştıracak.\n\n" .
                     "Önerilen Gezi Rotaları\n\n" .
                     "Tarihi Çarşı ve Merkez Turu: Yerel el sanatları, geleneksel dükkanlar ve tarihi dokuyu hissedebileceğiniz merkez yürüyüşü.\n" .
                     "Doğa ve Sahil Yürüyüşleri: Ailece vakit geçirebileceğiniz yeşil alanlar ve manzara noktaları.\n" .
                     "Kültür ve Tarih Mirası: Bölgenin geçmişine ışık tutan anıt yapılar ve müze alanları.\n\n" .
                     "İlçelerin kendine has güzelliklerini ve o bölgedeki öne çıkan işletmeleri keşfetmek için {$region} İlçe Rehberleri sayfamıza göz atabilirsiniz.",
        'image_path' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1200&q=80',
        'meta_description' => "{$region} gezilecek yerler, hafta sonu rotaları, tarihi çarşılar ve gezi rehberi.",
        'meta_keywords' => mb_strtolower($region, 'UTF-8') . " gezilecek yerler, " . mb_strtolower($region, 'UTF-8') . " turizm, gezi rehberi, hafta sonu rotası"
    ],
    [
        'title' => 'Yerel Esnaftan Alışverişin Ekonomiye ve Şehir Kültürüne Faydaları',
        'slug' => 'yerel-esnaftan-alisverisin-ekonomiye-ve-kulture-faydalari',
        'summary' => 'Mahalle esnafını desteklemenin yerel ekonomiye sağladığı katkılar, güvenilir alışveriş ve dayanışma kültürü.',
        'content' => "Bir şehrin en önemli canlılık kaynağı yerel esnaftır. Geleneksel ticaretin temsilcisi olan esnaflarımız, yalnızca ürün veya hizmet sunmakla kalmaz; mahalle kültürünü ve toplumsal güveni ayakta tutar.\n\n" .
                     "Neden Yerel Esnaftan Alışveriş Yapmalıyız?\n\n" .
                     "Kişiselleştirilmiş Hizmet: Müşteriyle doğrudan iletişim kuran, ihtiyaca uygun çözüm sunan esnaf yaklaşımı.\n" .
                     "Yerel Ekonomiye Destek: Harcanan her kuruşun bölge ekonomisinde kalarak istihdama dönüşmesi.\n" .
                     "Kalite ve Güven: Yıllardır aynı adreste hizmet veren işletmelerin sunduğu güvenilir alışveriş deneyimi.\n\n" .
                     "Bölgenizdeki tüm güvenilir ve doğrulanmış esnaflara {$siteTitle} Esnaf Vitrini üzerinden anında ulaşabilir, WhatsApp veya telefon ile doğrudan iletişime geçebilirsiniz.",
        'image_path' => 'https://images.unsplash.com/photo-1556740758-90de374c12ad?w=1200&q=80',
        'meta_description' => "Yerel esnaftan alışveriş yapmanın önemi, güvenilir hizmet ve mahalle kültürü.",
        'meta_keywords' => "yerel esnaf, esnaf alışverişi, " . mb_strtolower($region, 'UTF-8') . " esnafları, güvenilir firmalar"
    ],
    [
        'title' => 'İşletmeler İçin Dijital Vitrin, QR Menü ve Harita Kaydı Avantajları',
        'slug' => 'isletmeler-icin-dijital-vitrin-ve-qr-menu-avantajlari',
        'summary' => 'Yerel işletmenizi dijital rehbere taşıyarak yeni müşterilere ulaşmanın ve modern QR kartvizit/menü çözümleriyle fark yaratmanın yolları.',
        'content' => "Günümüzde tüketicilerin büyük çoğunluğu bir hizmet almadan önce internette arama yapıyor. İşletmenizin dijital dünyada doğru, güncel ve kurumsal bir şekilde yer alması, müşteri kitlenizi genişletmenin en etkili yoludur.\n\n" .
                     "Dijital Rehber Kaydının Sağladığı Avantajlar\n\n" .
                     "Görünürlük Artışı: Google aramalarında ve şehir rehberinde üst sıralarda yer alarak yeni müşterilere ulaşma imkanı.\n" .
                     "QR Menü ve Dijital Kartvizit: Kağıt baskı maliyeti olmadan menü ve hizmetlerinizi anlık olarak müşterilerinize sunma kolaylığı.\n" .
                     "Güven Veren Profil: Açık adres, çalışma saatleri, sosyal medya bağlantıları ve harita konumu ile şeffaf işletme imajı.\n\n" .
                     "Siz de firmanızı platformumuza eklemek veya hizmetlerimiz hakkında bilgi almak için İşletme Ekle sayfamızı kullanabilirsiniz.",
        'image_path' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=1200&q=80',
        'meta_description' => "İşletmeler için dijital rehber, QR menü ve harita kaydı imkanları.",
        'meta_keywords' => "dijital vitrin, qr menü, google harita kaydı, " . mb_strtolower($region, 'UTF-8') . " işletme ekle"
    ],
    [
        'title' => "{$region} Kültür, Sanat ve Sosyal Yaşam: Etkinlik Rehberi",
        'slug' => 'kultur-sanat-ve-sosyal-yasam-etkinlik-rehberi',
        'summary' => "{$region} genelinde düzenlenen konserler, tiyatro gösterileri, festivaller ve sosyal yaşama dair en güncel etkinlikler.",
        'content' => "{$region}, sosyal hayatı ve yıl boyunca düzenlenen çeşitli kültür-sanat etkinlikleriyle dinamik bir yaşam tarzı sunar. Hem gençler hem de aileler için düzenlenen etkinlikler, şehrin kültürel zenginliğini artırıyor.\n\n" .
                     "Öne Çıkan Etkinlik Kategorileri\n\n" .
                     "Müzik Konserleri ve Açık Hava Etkinlikleri: Sevilen sanatçıların buluştuğu konser serileri.\n" .
                     "Tiyatro ve Sahne Sanatları: Kültür merkezlerinde ve sahnelerde sergilenen oyunlar.\n" .
                     "Yerel Festivaller ve Çarşı Etkinlikleri: Şehrin geleneklerini ve kültürünü yansıtan özel buluşmalar.\n\n" .
                     "Bölgedeki yaklaşan tüm organizasyonları takip etmek ve etkinlik takvimine ulaşmak için {$region} Etkinlikler sayfamızı inceleyebilirsiniz.",
        'image_path' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=1200&q=80',
        'meta_description' => "{$region} etkinlik rehberi, konserler, festivaller ve sosyal yaşama dair güncel duyurular.",
        'meta_keywords' => mb_strtolower($region, 'UTF-8') . " etkinlikler, " . mb_strtolower($region, 'UTF-8') . " konserler, sosyal yaşam, festival rehberi"
    ],
    [
        'title' => 'Doğrulanmış İçerik Üreticileri ve Yerel Marka İş Birlikleri',
        'slug' => 'dogrulanmis-icerik-ureticileri-ve-yerel-marka-is-birlikleri',
        'summary' => 'Bölgenin önde gelen içerik üreticileri ile yerel işletmelerin bir araya gelerek gerçekleştirdiği etkili dijital tanıtım projeleri.',
        'content' => "Sosyal medya, yerel işletmelerin potansiyel müşterilerine samimi ve etkili bir şekilde ulaşmasını sağlayan en önemli kanallardan biridir. Doğru içerik üreticileri (influencerlar) ile yapılan iş birlikleri hem marka bilinirliğini hem de güveni artırır.\n\n" .
                     "Yerel İş Birliklerinin Gücü\n\n" .
                     "Doğal Tanıtım: Bölge halkı tarafından bilinen ve takip edilen isimlerin gerçek deneyim paylaşımları.\n" .
                     "Hedef Kitleye Erişim: Doğrudan şehirde yaşayan takipçi kitlesine nokta atışı ulaşım.\n" .
                     "QR Profil İle Ölçülebilirlik: İçerik üreticisine özel tanımlanan QR profiller ile görünürlüğün takibi.\n\n" .
                     "Bölgenizdeki doğrulanmış içerik üreticilerini incelemek için Influencer Rehberi sayfamızı ziyaret edebilirsiniz.",
        'image_path' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=1200&q=80',
        'meta_description' => "Yerel içerik üreticileri ile marka tanıtım projeleri ve influencer iş birlikleri.",
        'meta_keywords' => mb_strtolower($region, 'UTF-8') . " influencer, içerik üreticileri, sosyal medya tanıtımı, yerel iş birlikleri"
    ]
];

$stmt = $pdo->prepare("INSERT INTO blogs (title, slug, summary, content, image_path, meta_description, meta_keywords, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");

$count = 0;
foreach ($blogPosts as $post) {
    $stmt->execute([
        $post['title'],
        $post['slug'],
        $post['summary'],
        $post['content'],
        $post['image_path'],
        $post['meta_description'],
        $post['meta_keywords']
    ]);
    $count++;
    echo "[+] Eklendi: {$post['title']} ({$post['slug']})\n";
}

echo "\n=== Toplam {$count} adet dinamik blog yazısı veritabanına başarıyla eklendi! ===\n";
