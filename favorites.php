<?php
require_once 'db.php';
require_once 'auth.php';

// ─── Fetch all favorites with user name ──────────────────────────────
$selectedUser = $_GET['nav_user'] ?? ($_SESSION['nav_user'] ?? '');

try {
    if ($selectedUser !== '') {
        $stmt = $pdo->prepare("
            SELECT f.id, f.label, f.path, u.name AS user_name, u.id AS user_id
            FROM favorites f
            JOIN users u ON u.id = f.user_id
            WHERE u.name = ?
            ORDER BY f.label ASC
        ");
        $stmt->execute([$selectedUser]);
    } else {
        $stmt = $pdo->query("
            SELECT f.id, f.label, f.path, u.name AS user_name, u.id AS user_id
            FROM favorites f
            JOIN users u ON u.id = f.user_id
            ORDER BY f.label ASC
        ");
    }
    $favList = $stmt->fetchAll();
} catch (Exception $e) {
    $favList = [];
}

// ─── Fetch all users for the edit modal dropdown ─────────────────────
try {
    $userStmt   = $pdo->query("SELECT id, name FROM users ORDER BY name");
    $modalUsers = $userStmt->fetchAll();
} catch (Exception $e) {
    $modalUsers = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Favorites – Croven Events</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .fav-wrap {
      max-width: 1100px;
      margin: 0 auto;
      padding: 0 0 60px;
    }

    /* ── Page header row ─────────────────────────────────────────── */
    .fav-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
      flex-wrap: wrap;
      gap: 10px;
    }
    .fav-count {
      font-size: 0.8rem;
      opacity: 0.45;
      letter-spacing: 0.03em;
    }

    /* ── Grid ────────────────────────────────────────────────────── */
    .fav-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 14px;
    }

    /* ── Card ────────────────────────────────────────────────────── */
    .fav-card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 16px 18px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      transition: transform 0.15s, box-shadow 0.15s;
    }
    .fav-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 16px rgba(0,0,0,0.1);
    }
    .fav-card-top {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 10px;
    }
    .fav-card-title {
      font-size: 1rem;
      font-weight: 700;
      line-height: 1.3;
      word-break: break-word;
    }
    .fav-edit-btn {
      flex-shrink: 0;
      background: var(--input-bg);
      border: 1px solid var(--border-strong);
      border-radius: 8px;
      padding: 5px 12px;
      font-size: 0.78rem;
      font-weight: 600;
      color: inherit;
      cursor: pointer;
      opacity: 0.7;
      transition: opacity 0.15s, background 0.15s;
      white-space: nowrap;
    }
    .fav-edit-btn:hover { opacity: 1; background: var(--border); }

    .fav-card-link {
      font-size: 0.78rem;
      color: var(--accent);
      opacity: 0.7;
      word-break: break-all;
      text-decoration: none;
      line-height: 1.4;
      transition: opacity 0.15s;
    }
    .fav-card-link:hover { opacity: 1; text-decoration: underline; }

    .fav-card-user {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 0.78rem;
      opacity: 0.5;
    }
    .fav-user-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: var(--accent);
      flex-shrink: 0;
    }

    /* ── Empty state ─────────────────────────────────────────────── */
    .fav-empty {
      text-align: center;
      padding: 60px 20px;
      opacity: 0.4;
      font-size: 0.95rem;
    }
    .fav-empty-icon { font-size: 2.5rem; margin-bottom: 10px; }

    /* ── Modal backdrop ──────────────────────────────────────────── */
    .fav-modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.55);
      z-index: 1000;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .fav-modal-overlay.open { display: flex; }

    /* ── Modal box ───────────────────────────────────────────────── */
    .fav-modal {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 16px;
      width: 100%;
      max-width: 460px;
      box-shadow: 0 12px 40px rgba(0,0,0,0.35);
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    .fav-modal-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px 20px;
      border-bottom: 1px solid var(--border);
    }
    .fav-modal-title {
      font-size: 1rem;
      font-weight: 700;
    }
    .fav-modal-close {
      background: none;
      border: none;
      font-size: 1.1rem;
      color: inherit;
      opacity: 0.45;
      cursor: pointer;
      padding: 2px 6px;
      border-radius: 6px;
      transition: opacity 0.15s;
    }
    .fav-modal-close:hover { opacity: 1; }

    .fav-modal-body {
      padding: 20px;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    /* ── Form fields ─────────────────────────────────────────────── */
    .fav-field {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .fav-field label {
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.07em;
      text-transform: uppercase;
      opacity: 0.45;
    }
    .fav-field input,
    .fav-field select {
      background: var(--input-bg);
      border: 1px solid var(--border-strong);
      border-radius: 10px;
      padding: 10px 14px;
      font-size: 0.9rem;
      color: inherit;
      outline: none;
      transition: border-color 0.15s;
      width: 100%;
    }
    .fav-field input:focus,
    .fav-field select:focus { border-color: var(--accent); }
    .fav-field select option { background: var(--card-bg); color: var(--text); }

    /* ── Modal footer ────────────────────────────────────────────── */
    .fav-modal-footer {
      padding: 14px 20px;
      border-top: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }
    .fav-btn-save {
      padding: 9px 22px;
      border-radius: 10px;
      border: none;
      background: var(--accent);
      color: #fff;
      font-size: 0.88rem;
      font-weight: 600;
      cursor: pointer;
      transition: opacity 0.15s;
    }
    .fav-btn-save:hover { opacity: 0.85; }
    .fav-btn-cancel {
      padding: 9px 16px;
      border-radius: 10px;
      border: 1px solid var(--border-strong);
      background: transparent;
      color: inherit;
      font-size: 0.88rem;
      cursor: pointer;
      opacity: 0.6;
      transition: opacity 0.15s;
    }
    .fav-btn-cancel:hover { opacity: 1; }
    .fav-btn-delete {
      padding: 9px 16px;
      border-radius: 10px;
      border: 1px solid rgba(220,50,50,0.35);
      background: transparent;
      color: #e05555;
      font-size: 0.88rem;
      cursor: pointer;
      opacity: 0.75;
      transition: opacity 0.15s, background 0.15s;
      margin-right: auto;
    }
    .fav-btn-delete:hover { opacity: 1; background: rgba(220,50,50,0.1); }

    /* ── Status message ──────────────────────────────────────────── */
    .fav-status {
      font-size: 0.82rem;
      padding: 8px 12px;
      border-radius: 8px;
      display: none;
    }
    .fav-status.success { display: block; background: rgba(76,175,80,0.12); color: #4caf50; }
    .fav-status.error   { display: block; background: rgba(220,50,50,0.12); color: #e05555; }

    /* ── Confirm delete panel (inside modal) ─────────────────────── */
    .fav-confirm-panel {
      display: none;
      flex-direction: column;
      gap: 14px;
      padding: 20px;
      border-top: 1px solid var(--border);
      background: rgba(220,50,50,0.05);
    }
    .fav-confirm-panel.open { display: flex; }
    .fav-confirm-text {
      font-size: 0.9rem;
      font-weight: 600;
      color: #e05555;
      text-align: center;
    }
    .fav-confirm-text span {
      display: block;
      font-size: 0.78rem;
      font-weight: 400;
      opacity: 0.7;
      margin-top: 4px;
    }
    .fav-confirm-btns {
      display: flex;
      gap: 10px;
      justify-content: center;
    }
    .fav-btn-confirm-yes {
      padding: 9px 24px;
      border-radius: 10px;
      border: none;
      background: #e05555;
      color: #fff;
      font-size: 0.88rem;
      font-weight: 600;
      cursor: pointer;
      transition: opacity 0.15s;
    }
    .fav-btn-confirm-yes:hover { opacity: 0.85; }
    .fav-btn-confirm-no {
      padding: 9px 18px;
      border-radius: 10px;
      border: 1px solid var(--border-strong);
      background: transparent;
      color: inherit;
      font-size: 0.88rem;
      cursor: pointer;
      opacity: 0.6;
      transition: opacity 0.15s;
    }
    .fav-btn-confirm-no:hover { opacity: 1; }
  </style>
</head>
<body>

<?php
  $currentPage = 'favorites';
  $pageTitle   = 'Favorites';
  require 'nav.php';
?>

<div class="fav-wrap">

  <div class="fav-header">
    <div class="fav-count" id="favCount">
      <?= count($favList) ?> favorite<?= count($favList) !== 1 ? 's' : '' ?>
      <?= $selectedUser !== '' ? ' for ' . htmlspecialchars($selectedUser) : '' ?>
    </div>
  </div>

  <?php if (empty($favList)): ?>
    <div class="fav-empty">
      <div class="fav-empty-icon">⭐</div>
      No favorites found<?= $selectedUser !== '' ? ' for ' . htmlspecialchars($selectedUser) : '' ?>.
    </div>
  <?php else: ?>
  <div class="fav-grid" id="favGrid">
    <?php foreach ($favList as $fav): ?>
    <div class="fav-card" data-id="<?= $fav['id'] ?>">
      <div class="fav-card-top">
        <div class="fav-card-title"><?= htmlspecialchars($fav['label']) ?></div>
        <button class="fav-edit-btn"
          data-id="<?= $fav['id'] ?>"
          data-label="<?= htmlspecialchars($fav['label'], ENT_QUOTES) ?>"
          data-path="<?= htmlspecialchars($fav['path'], ENT_QUOTES) ?>"
          data-userid="<?= $fav['user_id'] ?? '' ?>"
          onclick="openEditModal(this)">
          Edit
        </button>
      </div>
      <a class="fav-card-link" href="<?= htmlspecialchars($fav['path']) ?>" target="_blank">
        <?= htmlspecialchars($fav['path']) ?>
      </a>
      <div class="fav-card-user">
        <span class="fav-user-dot"></span>
        <?= htmlspecialchars($fav['user_name'] ?? 'Unknown') ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div><!-- /fav-wrap -->

<!-- ══ Edit Modal ══════════════════════════════════════════════════ -->
<div class="fav-modal-overlay" id="favModalOverlay">
  <div class="fav-modal" id="favModal">

    <div class="fav-modal-header">
      <div class="fav-modal-title">Edit Favorite</div>
      <button class="fav-modal-close" id="favModalClose" aria-label="Close">&#10005;</button>
    </div>

    <div class="fav-modal-body">
      <div class="fav-status" id="favStatus"></div>

      <div class="fav-field">
        <label for="editLabel">Title</label>
        <input type="text" id="editLabel" maxlength="100" placeholder="e.g. Summer 2024">
      </div>

      <div class="fav-field">
        <label for="editPath">Link / URL</label>
        <input type="text" id="editPath" maxlength="250" placeholder="e.g. schedule.php?month=2024-06">
      </div>

      <div class="fav-field">
        <label for="editUser">User</label>
        <select id="editUser">
          <?php foreach ($modalUsers as $u): ?>
            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="fav-modal-footer">
      <button class="fav-btn-delete" id="favBtnDelete">🗑 Delete</button>
      <button class="fav-btn-cancel" id="favBtnCancel">Cancel</button>
      <button class="fav-btn-save"   id="favBtnSave">Save Changes</button>
    </div>

    <!-- Delete confirmation panel -->
    <div class="fav-confirm-panel" id="favConfirmPanel">
      <div class="fav-confirm-text">
        Delete this favorite?
        <span>This cannot be undone.</span>
      </div>
      <div class="fav-confirm-btns">
        <button class="fav-btn-confirm-no"  id="favConfirmNo">No, keep it</button>
        <button class="fav-btn-confirm-yes" id="favConfirmYes">Yes, delete</button>
      </div>
    </div>

  </div>
</div>

<script>
let editingId = null;

// ── Open modal ──────────────────────────────────────────────────────
function openEditModal(btn) {
  editingId = parseInt(btn.dataset.id, 10);
  document.getElementById('editLabel').value = btn.dataset.label;
  document.getElementById('editPath').value  = btn.dataset.path;
  document.getElementById('editUser').value  = btn.dataset.userid;

  // Reset state
  setStatus('', '');
  document.getElementById('favConfirmPanel').classList.remove('open');

  document.getElementById('favModalOverlay').classList.add('open');
}

// ── Close modal ─────────────────────────────────────────────────────
function closeModal() {
  document.getElementById('favModalOverlay').classList.remove('open');
  document.getElementById('favConfirmPanel').classList.remove('open');
  editingId = null;
}

document.getElementById('favModalClose').addEventListener('click', closeModal);
document.getElementById('favBtnCancel').addEventListener('click', closeModal);
document.getElementById('favModalOverlay').addEventListener('click', e => {
  if (e.target === document.getElementById('favModalOverlay')) closeModal();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

// ── Status helper ───────────────────────────────────────────────────
function setStatus(msg, type) {
  const el = document.getElementById('favStatus');
  el.textContent = msg;
  el.className = 'fav-status' + (type ? ' ' + type : '');
}

// ── Save changes ────────────────────────────────────────────────────
document.getElementById('favBtnSave').addEventListener('click', async () => {
  const label  = document.getElementById('editLabel').value.trim();
  const path   = document.getElementById('editPath').value.trim();
  const userId = parseInt(document.getElementById('editUser').value, 10);

  if (!label || !path || !userId) {
    setStatus('All fields are required.', 'error');
    return;
  }

  try {
    const res  = await fetch('edit_favorite.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: editingId, label, path, user_id: userId })
    });
    const data = await res.json();

    if (data.success) {
      setStatus('Saved!', 'success');
      updateCard(editingId, label, path, data.user_name);
      setTimeout(closeModal, 900);
    } else {
      setStatus(data.error || 'Save failed.', 'error');
    }
  } catch (err) {
    setStatus('Network error.', 'error');
  }
});

// ── Delete flow ─────────────────────────────────────────────────────
document.getElementById('favBtnDelete').addEventListener('click', () => {
  document.getElementById('favConfirmPanel').classList.add('open');
});

document.getElementById('favConfirmNo').addEventListener('click', () => {
  document.getElementById('favConfirmPanel').classList.remove('open');
});

document.getElementById('favConfirmYes').addEventListener('click', async () => {
  try {
    const res  = await fetch('edit_favorite.php', {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: editingId })
    });
    const data = await res.json();

    if (data.success) {
      removeCard(editingId);
      closeModal();
    } else {
      document.getElementById('favConfirmPanel').classList.remove('open');
      setStatus(data.error || 'Delete failed.', 'error');
    }
  } catch (err) {
    document.getElementById('favConfirmPanel').classList.remove('open');
    setStatus('Network error.', 'error');
  }
});

// ── DOM helpers ─────────────────────────────────────────────────────
function updateCard(id, label, path, userName) {
  const card = document.querySelector(`.fav-card[data-id="${id}"]`);
  if (!card) return;
  card.querySelector('.fav-card-title').textContent = label;
  const linkEl = card.querySelector('.fav-card-link');
  linkEl.textContent = path;
  linkEl.href = path;
  if (userName) {
    card.querySelector('.fav-card-user').innerHTML =
      `<span class="fav-user-dot"></span>${escHtml(userName)}`;
  }
  // Refresh edit button data attributes
  const btn = card.querySelector('.fav-edit-btn');
  btn.dataset.label = label;
  btn.dataset.path  = path;
}

function removeCard(id) {
  const card = document.querySelector(`.fav-card[data-id="${id}"]`);
  if (card) {
    card.style.transition = 'opacity 0.3s, transform 0.3s';
    card.style.opacity = '0';
    card.style.transform = 'scale(0.95)';
    setTimeout(() => {
      card.remove();
      updateCount();
    }, 300);
  }
}

function updateCount() {
  const remaining = document.querySelectorAll('.fav-card').length;
  const el = document.getElementById('favCount');
  if (el) el.textContent = `${remaining} favorite${remaining !== 1 ? 's' : ''}`;
}

function escHtml(str) {
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

</body>
</html>