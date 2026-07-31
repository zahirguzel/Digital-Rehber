<?php
require_once '../autoload.php';
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

$db = Database::getInstance();

require_once '../includes/influencer-helpers.php';

$tab = $_GET['tab'] ?? 'applications';
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_collab_read']) && (int) $_POST['mark_collab_read'] > 0) {
        $db->getPDO()->prepare('UPDATE influencer_collaboration_requests SET is_read = 1 WHERE id = ?')->execute([(int) $_POST['mark_collab_read']]);
        $successMsg = 'İş birliği talebi okundu olarak işaretlendi.';
    }
    if (isset($_POST['app_status']) && (int) $_POST['app_id'] > 0) {
        $status = $_POST['app_status'];
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $notes = trim($_POST['admin_notes'] ?? '');
            $db->getPDO()->prepare('UPDATE influencer_applications SET status = ?, admin_notes = ? WHERE id = ?')->execute([$status, $notes, (int) $_POST['app_id']]);
            $successMsg = 'Başvuru durumu güncellendi.';
        }
    }
    if (isset($_POST['removal_processed']) && (int) $_POST['removal_id'] > 0) {
        $db->getPDO()->prepare("UPDATE influencer_removal_requests SET status = 'processed' WHERE id = ?")->execute([(int) $_POST['removal_id']]);
        $rid = (int) $_POST['removal_id'];
        if (!empty($_POST['unpublish_profile'])) {
            $req = $db->query('SELECT influencer_id FROM influencer_removal_requests WHERE id = ?', [$rid]);
            $infId = $req->fetchColumn();
            if ($infId) {
                $db->getPDO()->prepare('UPDATE influencers SET is_published = 0 WHERE id = ?')->execute([(int) $infId]);
            }
        }
        $successMsg = 'Kaldırma talebi işlendi.';
    }
}

$applications = $db->query("SELECT * FROM influencer_applications ORDER BY FIELD(status,'pending','approved','rejected'), created_at DESC")->fetchAll();
$collabs = $db->query('SELECT c.*, i.name AS influencer_name, i.slug FROM influencer_collaboration_requests c JOIN influencers i ON i.id = c.influencer_id ORDER BY c.is_read ASC, c.created_at DESC')->fetchAll();
$removals = $db->query('SELECT r.*, i.slug FROM influencer_removal_requests r LEFT JOIN influencers i ON i.id = r.influencer_id ORDER BY FIELD(r.status,\'pending\',\'processed\'), r.created_at DESC')->fetchAll();

$pendingApps = count(array_filter($applications, function ($a) { return $a['status'] === 'pending'; }));
$unreadCollabs = count(array_filter($collabs, function ($c) { return !$c['is_read']; }));
$pendingRemovals = count(array_filter($removals, function ($r) { return $r['status'] === 'pending'; }));

$pageTitle = 'Influencer Talepleri';
include 'includes/header.php';
?>

<style>
.inf-req-bio {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 10px 12px;
    margin-top: 8px;
}
.inf-req-bio__label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    margin-bottom: 6px;
}
.inf-req-bio__label i {
    color: var(--primary);
    margin-right: 4px;
}
.inf-req-bio__text {
    font-size: 13px;
    color: #334155;
    line-height: 1.55;
    margin: 0;
    white-space: pre-wrap;
}
.inf-req-bio--empty {
    color: #94a3b8;
    font-style: italic;
}
.inf-req-detail dt {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    margin-bottom: 4px;
}
.inf-req-detail dd {
    margin-bottom: 14px;
    color: #1e293b;
}
.inf-req-detail dd:last-child {
    margin-bottom: 0;
}
.inf-req-social a {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    text-decoration: none;
    margin-right: 12px;
}
</style>

<?php
if ($successMsg): ?><div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($successMsg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php
endif; ?>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link <?= $tab === 'applications' ? 'active' : '' ?>" href="?tab=applications">Başvurular <?php
if ($pendingApps): ?><span class="badge bg-danger"><?= $pendingApps ?></span><?php
endif; ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'collabs' ? 'active' : '' ?>" href="?tab=collabs">İş Birlikleri <?php
if ($unreadCollabs): ?><span class="badge bg-danger"><?= $unreadCollabs ?></span><?php
endif; ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'removals' ? 'active' : '' ?>" href="?tab=removals">KVKK Talepleri <?php
if ($pendingRemovals): ?><span class="badge bg-danger"><?= $pendingRemovals ?></span><?php
endif; ?></a></li>
</ul>

<?php
if ($tab === 'applications'): ?>
<div class="card border-0 shadow-sm">
    <div class="card-header py-3 bg-white border-0">
        <h5 class="mb-0 fw-bold"><i class="fa-solid fa-user-plus me-2 text-primary"></i> Influencer Başvuruları</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4">Ad</th>
                    <th>İlçe / Niş</th>
                    <th>Sosyal</th>
                    <th>Onaylar</th>
                    <th>Durum</th>
                    <th class="text-end pe-4">İşlem</th>
                </tr>
            </thead>
            <tbody>
            <?php
if (empty($applications)): ?>
                <tr><td colspan="6" class="text-center py-5 text-muted">Henüz başvuru yok.</td></tr>
            <?php
else: foreach ($applications as $app):
                $bioPreview = trim($app['bio'] ?? '');
                $bioShort = $bioPreview !== '' ? (mb_strlen($bioPreview) > 100 ? mb_substr($bioPreview, 0, 100) . '…' : $bioPreview) : '';
            ?>
                <tr class="<?= $app['status'] === 'pending' ? 'table-warning' : '' ?>">
                    <td class="ps-4">
                        <strong><?= htmlspecialchars($app['name']) ?></strong><br>
                        <small class="text-muted"><?= htmlspecialchars($app['email']) ?></small>
                        <?php
if (!empty($app['phone'])): ?><br><small class="text-muted"><i class="fa-solid fa-phone fa-xs me-1"></i><?= htmlspecialchars($app['phone']) ?></small><?php
endif; ?>
                    </td>
                    <td><?= htmlspecialchars($app['district']) ?><br><small><?= htmlspecialchars(getInfluencerNicheLabel($app['niche'])) ?></small></td>
                    <td class="small text-nowrap">
                        <?php
if ($app['instagram']): ?><span class="badge bg-light text-dark border me-1">IG</span><?php
endif; ?>
                        <?php
if ($app['tiktok']): ?><span class="badge bg-light text-dark border me-1">TT</span><?php
endif; ?>
                        <?php
if ($app['youtube']): ?><span class="badge bg-light text-dark border">YT</span><?php
endif; ?>
                        <?php
if (!$app['instagram'] && !$app['tiktok'] && !$app['youtube']): ?><span class="text-muted">—</span><?php
endif; ?>
                    </td>
                    <td>
                        <?= $app['consent_profile'] ? '<span class="text-success"><i class="fa-solid fa-check me-1"></i>Profil</span>' : '<span class="text-danger"><i class="fa-solid fa-xmark me-1"></i>Profil</span>' ?>
                        ·
                        <?= $app['consent_kvkk'] ? '<span class="text-success">KVKK</span>' : '<span class="text-danger">KVKK</span>' ?>
                    </td>
                    <td><span class="badge bg-<?= getInfluencerApplicationStatusBadgeClass($app['status']) ?>"><?= htmlspecialchars(getInfluencerApplicationStatusLabel($app['status'])) ?></span></td>
                    <td class="text-end pe-4">
                        <div class="d-flex justify-content-end align-items-start gap-2 flex-wrap">
                            <button type="button"
                                class="btn btn-outline-primary btn-sm btn-view-app"
                                title="Görüntüle"
                                data-name="<?= htmlspecialchars($app['name'], ENT_QUOTES) ?>"
                                data-email="<?= htmlspecialchars($app['email'], ENT_QUOTES) ?>"
                                data-phone="<?= htmlspecialchars($app['phone'] ?? '', ENT_QUOTES) ?>"
                                data-district="<?= htmlspecialchars($app['district'], ENT_QUOTES) ?>"
                                data-niche="<?= htmlspecialchars(getInfluencerNicheLabel($app['niche']), ENT_QUOTES) ?>"
                                data-instagram="<?= htmlspecialchars($app['instagram'] ?? '', ENT_QUOTES) ?>"
                                data-tiktok="<?= htmlspecialchars($app['tiktok'] ?? '', ENT_QUOTES) ?>"
                                data-youtube="<?= htmlspecialchars($app['youtube'] ?? '', ENT_QUOTES) ?>"
                                data-bio="<?= htmlspecialchars($bioPreview, ENT_QUOTES) ?>"
                                data-consent-profile="<?= $app['consent_profile'] ? '1' : '0' ?>"
                                data-consent-kvkk="<?= $app['consent_kvkk'] ? '1' : '0' ?>"
                                data-status="<?= htmlspecialchars(getInfluencerApplicationStatusLabel($app['status']), ENT_QUOTES) ?>"
                                data-date="<?= htmlspecialchars(date('d.m.Y H:i', strtotime($app['created_at'])), ENT_QUOTES) ?>"
                                data-notes="<?= htmlspecialchars($app['admin_notes'] ?? '', ENT_QUOTES) ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#appDetailModal">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            <form method="POST" class="d-flex gap-1 flex-wrap justify-content-end">
    <?= CSRFMiddleware::field() ?>
                                <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                                <select name="app_status" class="form-select form-select-sm" style="width:auto; min-width:120px;">
                                    <option value="pending" <?= $app['status'] === 'pending' ? 'selected' : '' ?>>Beklemede</option>
                                    <option value="approved" <?= $app['status'] === 'approved' ? 'selected' : '' ?>>Onaylandı</option>
                                    <option value="rejected" <?= $app['status'] === 'rejected' ? 'selected' : '' ?>>Reddedildi</option>
                                </select>
                                <button type="submit" class="btn btn-sm btn-primary">Kaydet</button>
                            </form>
                        </div>
                        <?php
if ($bioPreview !== ''): ?>
                        <div class="inf-req-bio text-start">
                            <div class="inf-req-bio__label"><i class="fa-solid fa-align-left"></i> Bio</div>
                            <p class="inf-req-bio__text"><?= nl2br(htmlspecialchars($bioShort)) ?></p>
                        </div>
                        <?php
endif; ?>
                    </td>
                </tr>
            <?php
endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
elseif ($tab === 'collabs'): ?>
<div class="card border-0 shadow-sm">
    <div class="card-header py-3 bg-white border-0">
        <h5 class="mb-0 fw-bold"><i class="fa-solid fa-handshake me-2 text-primary"></i> İş Birliği Talepleri</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4">Influencer</th>
                    <th>İşletme</th>
                    <th>İletişim</th>
                    <th>Durum</th>
                    <th class="text-end pe-4">İşlem</th>
                </tr>
            </thead>
            <tbody>
            <?php
if (empty($collabs)): ?>
                <tr><td colspan="5" class="text-center py-5 text-muted">Henüz iş birliği talebi yok.</td></tr>
            <?php
else: foreach ($collabs as $c):
                $collabTypeLabel = !empty($c['collab_type']) && isset(influencerCollabTypes()[$c['collab_type']])
                    ? influencerCollabTypes()[$c['collab_type']]
                    : ($c['collab_type'] ?? '—');
            ?>
                <tr class="<?= !$c['is_read'] ? 'table-warning' : '' ?>">
                    <td class="ps-4">
                        <a href="../influencer/<?= htmlspecialchars($c['slug']) ?>" target="_blank" class="fw-bold text-decoration-none"><?= htmlspecialchars($c['influencer_name']) ?></a>
                        <br><small class="text-muted"><?= htmlspecialchars(date('d.m.Y H:i', strtotime($c['created_at']))) ?></small>
                    </td>
                    <td><?= htmlspecialchars($c['business_name']) ?></td>
                    <td class="small">
                        <?= htmlspecialchars($c['contact_name']) ?><br>
                        <?= htmlspecialchars($c['email']) ?>
                        <?php
if (!empty($c['phone'])): ?><br><?= htmlspecialchars($c['phone']) ?><?php
endif; ?>
                    </td>
                    <td>
                        <?php
if (!$c['is_read']): ?>
                            <span class="badge bg-warning text-dark">Okunmadı</span>
                        <?php
else: ?>
                            <span class="badge bg-secondary">Okundu</span>
                        <?php
endif; ?>
                    </td>
                    <td class="text-end pe-4">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button"
                                class="btn btn-outline-primary btn-sm btn-view-collab"
                                title="Görüntüle"
                                data-influencer="<?= htmlspecialchars($c['influencer_name'], ENT_QUOTES) ?>"
                                data-slug="<?= htmlspecialchars($c['slug'], ENT_QUOTES) ?>"
                                data-business="<?= htmlspecialchars($c['business_name'], ENT_QUOTES) ?>"
                                data-contact="<?= htmlspecialchars($c['contact_name'], ENT_QUOTES) ?>"
                                data-email="<?= htmlspecialchars($c['email'], ENT_QUOTES) ?>"
                                data-phone="<?= htmlspecialchars($c['phone'] ?? '', ENT_QUOTES) ?>"
                                data-type="<?= htmlspecialchars($collabTypeLabel, ENT_QUOTES) ?>"
                                data-message="<?= htmlspecialchars($c['message'], ENT_QUOTES) ?>"
                                data-date="<?= htmlspecialchars(date('d.m.Y H:i', strtotime($c['created_at'])), ENT_QUOTES) ?>"
                                data-read="<?= $c['is_read'] ? '1' : '0' ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#collabDetailModal">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            <?php
if (!$c['is_read']): ?>
                            <form method="POST">
    <?= CSRFMiddleware::field() ?>
                                <input type="hidden" name="mark_collab_read" value="<?= $c['id'] ?>">
                                <button class="btn btn-sm btn-outline-success">Okundu</button>
                            </form>
                            <?php
endif; ?>
                        </div>
                    </td>
                </tr>
            <?php
endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
else: ?>
<div class="card border-0 shadow-sm">
    <div class="card-header py-3 bg-white border-0">
        <h5 class="mb-0 fw-bold"><i class="fa-solid fa-user-shield me-2 text-primary"></i> KVKK Talepleri</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4">Profil</th>
                    <th>E-posta</th>
                    <th>Tür</th>
                    <th>Durum</th>
                    <th class="text-end pe-4">İşlem</th>
                </tr>
            </thead>
            <tbody>
            <?php
if (empty($removals)): ?>
                <tr><td colspan="5" class="text-center py-5 text-muted">Henüz KVKK talebi yok.</td></tr>
            <?php
else: foreach ($removals as $r): ?>
                <tr class="<?= $r['status'] === 'pending' ? 'table-warning' : '' ?>">
                    <td class="ps-4">
                        <?= htmlspecialchars($r['profile_name']) ?>
                        <?php
if ($r['slug']): ?><br><small class="text-muted font-monospace"><?= htmlspecialchars($r['slug']) ?></small><?php
endif; ?>
                        <br><small class="text-muted"><?= htmlspecialchars(date('d.m.Y H:i', strtotime($r['created_at']))) ?></small>
                    </td>
                    <td><?= htmlspecialchars($r['email']) ?></td>
                    <td><?= htmlspecialchars(getInfluencerRemovalRequestTypeLabel($r['request_type'])) ?></td>
                    <td><span class="badge bg-<?= getInfluencerRemovalStatusBadgeClass($r['status']) ?>"><?= htmlspecialchars(getInfluencerRemovalStatusLabel($r['status'])) ?></span></td>
                    <td class="text-end pe-4">
                        <div class="d-flex justify-content-end gap-2 flex-wrap">
                            <button type="button"
                                class="btn btn-outline-primary btn-sm btn-view-removal"
                                title="Görüntüle"
                                data-profile="<?= htmlspecialchars($r['profile_name'], ENT_QUOTES) ?>"
                                data-slug="<?= htmlspecialchars($r['slug'] ?? '', ENT_QUOTES) ?>"
                                data-email="<?= htmlspecialchars($r['email'], ENT_QUOTES) ?>"
                                data-type="<?= htmlspecialchars(getInfluencerRemovalRequestTypeLabel($r['request_type']), ENT_QUOTES) ?>"
                                data-reason="<?= htmlspecialchars($r['reason'], ENT_QUOTES) ?>"
                                data-status="<?= htmlspecialchars(getInfluencerRemovalStatusLabel($r['status']), ENT_QUOTES) ?>"
                                data-date="<?= htmlspecialchars(date('d.m.Y H:i', strtotime($r['created_at'])), ENT_QUOTES) ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#removalDetailModal">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            <?php
if ($r['status'] === 'pending'): ?>
                            <form method="POST" class="text-start">
    <?= CSRFMiddleware::field() ?>
                                <input type="hidden" name="removal_id" value="<?= $r['id'] ?>">
                                <?php
if ($r['request_type'] === 'removal' && $r['influencer_id']): ?>
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" name="unpublish_profile" value="1" id="unpub<?= $r['id'] ?>">
                                    <label class="form-check-label small" for="unpub<?= $r['id'] ?>">Profili yayından kaldır</label>
                                </div>
                                <?php
endif; ?>
                                <button type="submit" name="removal_processed" value="1" class="btn btn-sm btn-primary">İşlendi</button>
                            </form>
                            <?php
endif; ?>
                        </div>
                    </td>
                </tr>
            <?php
endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
endif; ?>

<!-- Başvuru detay modal -->
<div class="modal fade" id="appDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-plus text-primary me-2"></i> Başvuru Detayı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <dl class="inf-req-detail row mb-0">
                    <div class="col-md-6"><dt>Ad Soyad</dt><dd id="app-modal-name" class="fw-bold"></dd></div>
                    <div class="col-md-6"><dt>Durum</dt><dd id="app-modal-status"></dd></div>
                    <div class="col-md-6"><dt>E-posta</dt><dd id="app-modal-email"></dd></div>
                    <div class="col-md-6"><dt>Telefon</dt><dd id="app-modal-phone"></dd></div>
                    <div class="col-md-6"><dt>İlçe</dt><dd id="app-modal-district"></dd></div>
                    <div class="col-md-6"><dt>Niş</dt><dd id="app-modal-niche"></dd></div>
                    <div class="col-md-6"><dt>Başvuru Tarihi</dt><dd id="app-modal-date"></dd></div>
                    <div class="col-md-6"><dt>Onaylar</dt><dd id="app-modal-consent"></dd></div>
                    <div class="col-12"><dt>Sosyal Medya</dt><dd id="app-modal-social" class="inf-req-social"></dd></div>
                    <div class="col-12"><dt>Bio</dt><dd><div class="inf-req-bio"><p id="app-modal-bio" class="inf-req-bio__text mb-0"></p></div></dd></div>
                    <div class="col-12" id="app-modal-notes-wrap" style="display:none;"><dt>Yönetici Notu</dt><dd><div class="inf-req-bio"><p id="app-modal-notes" class="inf-req-bio__text mb-0"></p></div></dd></div>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<!-- İş birliği detay modal -->
<div class="modal fade" id="collabDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-handshake text-primary me-2"></i> İş Birliği Detayı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <dl class="inf-req-detail row mb-0">
                    <div class="col-md-6"><dt>Influencer</dt><dd id="collab-modal-influencer" class="fw-bold"></dd></div>
                    <div class="col-md-6"><dt>Durum</dt><dd id="collab-modal-read"></dd></div>
                    <div class="col-md-6"><dt>İşletme</dt><dd id="collab-modal-business"></dd></div>
                    <div class="col-md-6"><dt>İş Birliği Türü</dt><dd id="collab-modal-type"></dd></div>
                    <div class="col-md-6"><dt>Yetkili</dt><dd id="collab-modal-contact"></dd></div>
                    <div class="col-md-6"><dt>Tarih</dt><dd id="collab-modal-date"></dd></div>
                    <div class="col-md-6"><dt>E-posta</dt><dd id="collab-modal-email"></dd></div>
                    <div class="col-md-6"><dt>Telefon</dt><dd id="collab-modal-phone"></dd></div>
                    <div class="col-12"><dt>Mesaj</dt><dd><div class="inf-req-bio"><p id="collab-modal-message" class="inf-req-bio__text mb-0"></p></div></dd></div>
                </dl>
            </div>
            <div class="modal-footer">
                <a href="#" id="collab-modal-profile-link" target="_blank" class="btn btn-outline-primary btn-sm">Profili Aç</a>
                <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<!-- KVKK detay modal -->
<div class="modal fade" id="removalDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-shield text-primary me-2"></i> KVKK Talebi Detayı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <dl class="inf-req-detail row mb-0">
                    <div class="col-md-6"><dt>Profil Adı</dt><dd id="removal-modal-profile" class="fw-bold"></dd></div>
                    <div class="col-md-6"><dt>Durum</dt><dd id="removal-modal-status"></dd></div>
                    <div class="col-md-6"><dt>E-posta</dt><dd id="removal-modal-email"></dd></div>
                    <div class="col-md-6"><dt>Talep Türü</dt><dd id="removal-modal-type"></dd></div>
                    <div class="col-md-6"><dt>Tarih</dt><dd id="removal-modal-date"></dd></div>
                    <div class="col-md-6"><dt>Slug</dt><dd id="removal-modal-slug"></dd></div>
                    <div class="col-12"><dt>Açıklama</dt><dd><div class="inf-req-bio"><p id="removal-modal-reason" class="inf-req-bio__text mb-0"></p></div></dd></div>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-view-app').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('app-modal-name').textContent = btn.dataset.name || '—';
            document.getElementById('app-modal-status').textContent = btn.dataset.status || '—';
            document.getElementById('app-modal-email').textContent = btn.dataset.email || '—';
            document.getElementById('app-modal-phone').textContent = btn.dataset.phone || '—';
            document.getElementById('app-modal-district').textContent = btn.dataset.district || '—';
            document.getElementById('app-modal-niche').textContent = btn.dataset.niche || '—';
            document.getElementById('app-modal-date').textContent = btn.dataset.date || '—';

            var consent = [];
            if (btn.dataset.consentProfile === '1') consent.push('Profil onayı verildi');
            else consent.push('Profil onayı yok');
            if (btn.dataset.consentKvkk === '1') consent.push('KVKK onayı verildi');
            else consent.push('KVKK onayı yok');
            document.getElementById('app-modal-consent').textContent = consent.join(' · ');

            var social = document.getElementById('app-modal-social');
            social.innerHTML = '';
            [['instagram', 'Instagram', 'fa-brands fa-instagram'], ['tiktok', 'TikTok', 'fa-brands fa-tiktok'], ['youtube', 'YouTube', 'fa-brands fa-youtube']].forEach(function (item) {
                var url = btn.dataset[item[0]];
                if (url) {
                    var a = document.createElement('a');
                    a.href = url;
                    a.target = '_blank';
                    a.innerHTML = '<i class="' + item[2] + '"></i> ' + item[1];
                    social.appendChild(a);
                }
            });
            if (!social.innerHTML) social.textContent = '—';

            var bioEl = document.getElementById('app-modal-bio');
            bioEl.textContent = btn.dataset.bio || '';
            bioEl.classList.toggle('inf-req-bio--empty', !btn.dataset.bio);
            if (!btn.dataset.bio) bioEl.textContent = 'Bio girilmemiş.';

            var notesWrap = document.getElementById('app-modal-notes-wrap');
            if (btn.dataset.notes) {
                notesWrap.style.display = '';
                document.getElementById('app-modal-notes').textContent = btn.dataset.notes;
            } else {
                notesWrap.style.display = 'none';
            }
        });
    });

    document.querySelectorAll('.btn-view-collab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('collab-modal-influencer').textContent = btn.dataset.influencer || '—';
            document.getElementById('collab-modal-read').textContent = btn.dataset.read === '1' ? 'Okundu' : 'Okunmadı';
            document.getElementById('collab-modal-business').textContent = btn.dataset.business || '—';
            document.getElementById('collab-modal-type').textContent = btn.dataset.type || '—';
            document.getElementById('collab-modal-contact').textContent = btn.dataset.contact || '—';
            document.getElementById('collab-modal-date').textContent = btn.dataset.date || '—';
            document.getElementById('collab-modal-email').textContent = btn.dataset.email || '—';
            document.getElementById('collab-modal-phone').textContent = btn.dataset.phone || '—';
            document.getElementById('collab-modal-message').textContent = btn.dataset.message || '—';
            document.getElementById('collab-modal-profile-link').href = '/influencer/' + (btn.dataset.slug || '');
        });
    });

    document.querySelectorAll('.btn-view-removal').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('removal-modal-profile').textContent = btn.dataset.profile || '—';
            document.getElementById('removal-modal-status').textContent = btn.dataset.status || '—';
            document.getElementById('removal-modal-email').textContent = btn.dataset.email || '—';
            document.getElementById('removal-modal-type').textContent = btn.dataset.type || '—';
            document.getElementById('removal-modal-date').textContent = btn.dataset.date || '—';
            document.getElementById('removal-modal-slug').textContent = btn.dataset.slug || '—';
            document.getElementById('removal-modal-reason').textContent = btn.dataset.reason || '—';
        });
    });
});
</script>

<?php
include 'includes/footer.php'; ?>
