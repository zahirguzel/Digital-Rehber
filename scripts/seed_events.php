<?php
/**
 * Örnek Etkinlik Verileri Ekleme Scripti
 * Çalıştırıldığında örnek konser, festival, sergi, spor ve kültür etkinlikleri ekler.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/seo-meta.php';

try {
    $today = date('Y-m-d');
    $nextWeek = date('Y-m-d', strtotime('+7 days'));
    $nextMonth = date('Y-m-d', strtotime('+20 days'));
    $twoMonths = date('Y-m-d', strtotime('+45 days'));
    $pastDate = date('Y-m-d', strtotime('-15 days'));

    $sampleEvents = [
        [
            'title' => 'Girne Amfi Tiyatro Yaz Konserleri',
            'slug' => 'girne-amfi-tiyatro-yaz-konserleri',
            'district' => 'Girne',
            'venue_name' => 'Girne Amfi Tiyatro',
            'address' => 'Girne Amfi Tiyatro, Liman Arkası, Girne',
            'start_date' => $nextWeek,
            'end_date' => $nextWeek,
            'start_time' => '21:00:00',
            'end_time' => '23:30:00',
            'category' => 'konsert',
            'description' => '<p><strong>Girne Amfi Tiyatro</strong> eşsiz Akdeniz manzarası eşliğinde unutulmaz bir yaz konserine ev sahipliği yapıyor.</p><p>Sevilen sanatçıların sahne alacağı bu gecede akustik performanslar ve sürpriz konuklar müzikseverlerle buluşacak. Etkinlik alanında yiyecek ve içecek stantları hizmet verecektir.</p><ul><li>Kapı açılış saati: 19:30</li><li>Konser başlangıç saati: 21:00</li><li>Numarasız oturma düzeni geçerlidir.</li></ul>',
            'cover_image_path' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=800&q=80',
            'ticket_url' => 'https://biletinial.com',
            'ticket_price' => '450 TL',
            'organizer' => 'Akdeniz Müzik Organizasyon',
            'contact_phone' => '0533 880 11 22',
            'contact_email' => 'bilgi@akdenizorganizasyon.com',
            'is_featured' => 1,
            'is_published' => 1
        ],
        [
            'title' => 'Uluslararası Gazimağusa Kültür ve Sanat Festivali',
            'slug' => 'uluslararasi-gazimagusa-kultur-ve-sanat-festivali',
            'district' => 'Gazimağusa',
            'venue_name' => 'Salamis Antik Tiyatrosu',
            'address' => 'Salamis Antik Kenti Tiyatro Alanı, Gazimağusa',
            'start_date' => $nextMonth,
            'end_date' => date('Y-m-d', strtotime('+23 days')),
            'start_time' => '20:30:00',
            'end_time' => '23:00:00',
            'category' => 'festival',
            'description' => '<p>Bu yıl 26.\'sı düzenlenen <strong>Uluslararası Kültür ve Sanat Festivali</strong>, tarihi Salamis Antik Tiyatrosu\'nun büyüleyici atmosferinde gerçekleşiyor.</p><p>Dört gün sürecek festival boyunca klasik müzik dinletileri, modern dans gösterileri ve uluslararası tiyatro topluluklarının performansları sergilenecek.</p>',
            'cover_image_path' => 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=800&q=80',
            'ticket_url' => 'https://biletinial.com',
            'ticket_price' => '600 TL (Kombine)',
            'organizer' => 'Gazimağusa Belediyesi Kültür Müdürlüğü',
            'contact_phone' => '0392 366 55 44',
            'contact_email' => 'festival@magusa.org',
            'is_featured' => 1,
            'is_published' => 1
        ],
        [
            'title' => 'Lefkoşa Çağdaş Sanat ve Resim Sergisi',
            'slug' => 'lefkosa-cagdas-sanat-ve-resim-sergisi',
            'district' => 'Lefkoşa',
            'venue_name' => 'Atatürk Kültür ve Kongre Merkezi',
            'address' => 'Bedrettin Demirel Caddesi, Lefkoşa Merkez',
            'start_date' => $today,
            'end_date' => $nextWeek,
            'start_time' => '10:00:00',
            'end_time' => '19:00:00',
            'category' => 'sergi',
            'description' => '<p>Kıbrıslı ve uluslararası 20\'den fazla çağdaş sanatçının eserlerini bir araya getiren resim ve heykel sergisi kapılarını açıyor.</p><p>Sergi boyunca her gün saat 15:00\'te sanatçılar eşliğinde rehberli sergi turları düzenlenecektir. Girişler ücretsizdir.</p>',
            'cover_image_path' => 'https://images.unsplash.com/photo-1531058020387-3be344556be6?w=800&q=80',
            'ticket_url' => '',
            'ticket_price' => 'Ücretsiz',
            'organizer' => 'Kıbrıs Sanat Derneği',
            'contact_phone' => '0392 228 10 20',
            'contact_email' => 'info@kibrissanat.org',
            'is_featured' => 0,
            'is_published' => 1
        ],
        [
            'title' => 'Akdeniz Su Sporları ve Sörf Şampiyonası',
            'slug' => 'akdeniz-su-sporlari-ve-sorf-samipyonasi',
            'district' => 'İskele',
            'venue_name' => 'Long Beach Sahil Kordonu',
            'address' => 'Long Beach Sahil Alanı 3. Etap, İskele',
            'start_date' => $twoMonths,
            'end_date' => date('Y-m-d', strtotime('+47 days')),
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'category' => 'spor',
            'description' => '<p>Akdeniz\'in en gözde plajlarından İskele Long Beach\'te düzenlenecek su sporları şampiyonası büyük heyecana sahne olacak.</p><p>Kitesurf, windsurf ve kürek sörfü (SUP) kategorilerinde yarışmaların yapılacağı etkinlikte izleyiciler için de ücretsiz deneme seansları sunulacak.</p>',
            'cover_image_path' => 'https://images.unsplash.com/photo-1502680390469-be75c86b636f?w=800&q=80',
            'ticket_url' => '',
            'ticket_price' => 'Ücretsiz',
            'organizer' => 'Kuzey Kıbrıs Su Sporları Kulübü',
            'contact_phone' => '0533 870 40 50',
            'contact_email' => 'info@cypruswatersports.com',
            'is_featured' => 1,
            'is_published' => 1
        ],
        [
            'title' => 'Çocuklar İçin Kukla ve Masal Tiyatrosu',
            'slug' => 'cocuklar-icin-kukla-ve-masal-tiyatrosu',
            'district' => 'Güzelyurt',
            'venue_name' => 'Güzelyurt Kültür Evi',
            'address' => 'Kültür Evi Tiyatro Salonu, Güzelyurt',
            'start_date' => $nextWeek,
            'end_date' => $nextWeek,
            'start_time' => '14:00:00',
            'end_time' => '15:30:00',
            'category' => 'cocuk',
            'description' => '<p>Çocukların hayal gücünü geliştirmeyi hedefleyen interaktif masal ve kukla tiyatrosu Güzelyurt\'ta minik sanatseverlerle buluşuyor.</p><p>4-10 yaş grubu çocuklar için hazırlanan oyunda, doğa sevgisi ve arkadaşlık temalı eğlenceli hikâyeler anlatılıyor.</p>',
            'cover_image_path' => 'https://images.unsplash.com/photo-1511632765486-a01980e01a18?w=800&q=80',
            'ticket_url' => '',
            'ticket_price' => '100 TL',
            'organizer' => 'Minik Adımlar Tiyatro Topluluğu',
            'contact_phone' => '0533 844 12 13',
            'contact_email' => 'bilgi@minikadimlartiyatro.com',
            'is_featured' => 0,
            'is_published' => 1
        ],
        [
            'title' => 'Kıbrıs Tarih ve Gastronomi Söyleşisi',
            'slug' => 'kibris-tarih-ve-gastronomi-soylesisi',
            'district' => 'Lefkoşa',
            'venue_name' => 'Selimiye Kültür Merkezi',
            'address' => 'Selimiye Meydanı, Surlariçi, Lefkoşa',
            'start_date' => $pastDate,
            'end_date' => $pastDate,
            'start_time' => '18:00:00',
            'end_time' => '20:00:00',
            'category' => 'kultur',
            'description' => '<p>Ada mutfağının tarihsel gelişimi ve unutulmaya yüz tutmuş geleneksel tariflerin konuşulduğu gastronomi söyleşisi gerçekleştirildi.</p><p>Tarihçiler ve usta aşçıların katıldığı etkinlikte katılımcılara yöresel lezzetler ikram edildi.</p>',
            'cover_image_path' => 'https://images.unsplash.com/photo-1555244162-803834f70033?w=800&q=80',
            'ticket_url' => '',
            'ticket_price' => 'Ücretsiz',
            'organizer' => 'Kültür Mirası Koruma Derneği',
            'contact_phone' => '0392 227 00 11',
            'contact_email' => 'kultur@mirasdernegi.org',
            'is_featured' => 0,
            'is_published' => 1
        ]
    ];

    $sql = "INSERT INTO events (title, slug, district, venue_name, address, start_date, end_date, start_time, end_time, category, description, cover_image_path, ticket_url, ticket_price, organizer, contact_phone, contact_email, is_featured, is_published) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                title = VALUES(title),
                district = VALUES(district),
                venue_name = VALUES(venue_name),
                address = VALUES(address),
                start_date = VALUES(start_date),
                end_date = VALUES(end_date),
                start_time = VALUES(start_time),
                end_time = VALUES(end_time),
                category = VALUES(category),
                description = VALUES(description),
                cover_image_path = VALUES(cover_image_path),
                ticket_url = VALUES(ticket_url),
                ticket_price = VALUES(ticket_price),
                organizer = VALUES(organizer),
                contact_phone = VALUES(contact_phone),
                contact_email = VALUES(contact_email),
                is_featured = VALUES(is_featured),
                is_published = VALUES(is_published)";

    $stmt = $pdo->prepare($sql);

    $count = 0;
    foreach ($sampleEvents as $ev) {
        $stmt->execute([
            $ev['title'],
            $ev['slug'],
            $ev['district'],
            $ev['venue_name'],
            $ev['address'],
            $ev['start_date'],
            $ev['end_date'],
            $ev['start_time'],
            $ev['end_time'],
            $ev['category'],
            $ev['description'],
            $ev['cover_image_path'],
            $ev['ticket_url'],
            $ev['ticket_price'],
            $ev['organizer'],
            $ev['contact_phone'],
            $ev['contact_email'],
            $ev['is_featured'],
            $ev['is_published']
        ]);
        $count++;
    }

    echo "Başarıyla {$count} örnek etkinlik verisi eklendi veya güncellendi.\n";
} catch (Exception $e) {
    echo "Hata: " . $e->getMessage() . "\n";
}
