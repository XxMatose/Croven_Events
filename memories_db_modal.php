<!-- ═══════════════════════════════════════════════════════════════════
     memories_db_modal.php
     Drop this file into your project root and add one line to schedule.php:
         require 'memories_db_modal.php';
     Place that require just before </body> in schedule.php.
     ═══════════════════════════════════════════════════════════════════ -->

<!-- ── Memories DB Modal Overlay ──────────────────────────────────── -->
<div class="mdb-overlay" id="mdbOverlay">
  <div class="mdb-modal" id="mdbModal">

    <!-- Header -->
    <div class="mdb-header">
      <button class="mdb-new-btn" id="mdbNewBtn" title="Add new memory">&#43;</button>
      <span class="mdb-title" id="mdbTitle">Memories</span>
      <div class="mdb-header-actions">
        <!-- Back to grid button (hidden until detail view) -->
        <button class="mdb-back-btn" id="mdbBackBtn" style="display:none">&#8592; All Photos</button>
        <button class="mdb-close-btn" id="mdbCloseBtn" title="Close">&#10005;</button>
      </div>
    </div>

    <!-- Body -->
    <div class="mdb-body" id="mdbBody">

      <!-- Loading state -->
      <div class="mdb-loading" id="mdbLoading">
        <div class="mdb-spinner"></div>
        <span>Loading memories…</span>
      </div>

      <!-- Empty state -->
      <div class="mdb-empty" id="mdbEmpty" style="display:none">
        <span style="font-size:2rem">&#128444;</span>
        <span>No memories found for this event.</span>
      </div>

      <!-- Photo grid -->
      <div class="mdb-grid" id="mdbGrid" style="display:none"></div>

      <!-- Detail / edit view -->
      <div class="mdb-detail" id="mdbDetail" style="display:none">

        <!-- Left: image + URL -->
        <div class="mdb-detail-left">
          <div class="mdb-detail-image-wrap" id="mdbDetailImageWrap">
            <img class="mdb-detail-img" id="mdbDetailImg" src="" alt="Memory photo" style="display:none">
            <div class="mdb-detail-placeholder" id="mdbDetailPlaceholder">
              <span>&#128444;</span>
            </div>
          </div>
          <div class="mdb-image-url-wrap">
            <label class="mdb-label">Image URL</label>
            <input class="mdb-input mdb-image-url-input mdb-editable" id="mdbFImageUrl" type="text" readonly placeholder="No image URL">
          </div>
        </div>

        <!-- Right: form -->
        <div class="mdb-detail-form" id="mdbDetailForm">

          <!-- Row 1: Title + Memory Owner -->
          <div class="mdb-field-row mdb-grid-2">
            <div class="mdb-field">
              <label class="mdb-label">Title</label>
              <input class="mdb-input mdb-editable" id="mdbFTitle" type="text" placeholder="Memory title" readonly>
            </div>
            <div class="mdb-field">
              <label class="mdb-label">Memory Owner</label>
              <input class="mdb-input mdb-editable" id="mdbFOwner" type="text" placeholder="Who does this memory belong to?" readonly>
            </div>
          </div>

          <!-- Row 2: Trade Direction + Day Traded -->
          <div class="mdb-field-row mdb-grid-2">
            <div class="mdb-field">
              <label class="mdb-label">Trade Direction</label>
              <select class="mdb-input mdb-select mdb-editable" id="mdbFDirection" disabled>
                <option value="">— select —</option>
                <option value="Given">Given</option>
                <option value="Received">Received</option>
                <option value="Shared">Shared</option>
              </select>
            </div>
            <div class="mdb-field">
              <label class="mdb-label">Day Traded</label>
              <input class="mdb-input mdb-editable" id="mdbFTradeDay" type="date" readonly>
            </div>
          </div>

          <!-- Row 3: Story -->
          <div class="mdb-field">
            <label class="mdb-label">Story</label>
            <textarea class="mdb-input mdb-textarea mdb-editable" id="mdbFStory" rows="5" placeholder="Write the story behind this memory…" readonly></textarea>
          </div>

          <!-- Footer row: feedback + edit/save -->
          <div class="mdb-form-footer">
            <span class="mdb-feedback" id="mdbFeedback"></span>
            <button class="mdb-edit-btn" id="mdbEditBtn">&#9998; Edit</button>
            <button class="mdb-save-btn" id="mdbSaveBtn" style="display:none">Save Changes</button>
          </div>

        </div>
      </div>

    </div><!-- /mdb-body -->
  </div>
</div>

<style>
/* ═══════════════════════════════════════════════════════════════════
   Memories DB Modal styles
   ═══════════════════════════════════════════════════════════════════ */

/* ── Overlay ─────────────────────────────────────────────────────── */
.mdb-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.84);
  z-index: 970;
  align-items: center;
  justify-content: center;
  padding: 20px;
  backdrop-filter: blur(4px);
}
.mdb-overlay.open { display: flex; }

/* ── Modal shell ─────────────────────────────────────────────────── */
.mdb-modal {
  background: #0d0d0d;
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 16px;
  width: 100%;
  max-width: 980px;
  max-height: 88vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 32px 80px rgba(0,0,0,0.85);
  overflow: hidden;
}

/* ── Header ──────────────────────────────────────────────────────── */
.mdb-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  border-bottom: 1px solid rgba(255,255,255,0.08);
  flex-shrink: 0;
  gap: 12px;
}
.mdb-title {
  font-size: 0.9rem;
  font-weight: 700;
  color: #fff;
  letter-spacing: 0.04em;
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.mdb-header-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}
.mdb-back-btn {
  background: rgba(167,139,250,0.12);
  border: 1px solid rgba(167,139,250,0.3);
  color: #a78bfa;
  border-radius: 8px;
  padding: 5px 12px;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}
.mdb-back-btn:hover { background: rgba(167,139,250,0.22); }
.mdb-close-btn {
  background: none;
  border: none;
  font-size: 1.3rem;
  cursor: pointer;
  color: #fff;
  opacity: 0.4;
  line-height: 1;
  padding: 2px 8px;
  border-radius: 6px;
  transition: opacity 0.15s;
}
.mdb-close-btn:hover { opacity: 1; }

/* ── New memory button ───────────────────────────────────────────── */
.mdb-new-btn {
  background: #ef4444;
  border: none;
  border-radius: 50%;
  width: 26px;
  height: 26px;
  font-size: 1.2rem;
  line-height: 1;
  color: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: background 0.15s, transform 0.12s;
  font-weight: 700;
  padding: 0;
}
.mdb-new-btn:hover { background: #dc2626; transform: scale(1.1); }

/* ── Body ────────────────────────────────────────────────────────── */
.mdb-body {
  flex: 1;
  overflow-y: auto;
  min-height: 0;
}

/* ── Loading / empty ─────────────────────────────────────────────── */
.mdb-loading, .mdb-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 280px;
  gap: 14px;
  color: rgba(255,255,255,0.35);
  font-size: 0.85rem;
}
.mdb-spinner {
  width: 28px;
  height: 28px;
  border: 3px solid rgba(255,255,255,0.1);
  border-top-color: rgba(255,255,255,0.55);
  border-radius: 50%;
  animation: mdb-spin 0.7s linear infinite;
}
@keyframes mdb-spin { to { transform: rotate(360deg); } }

/* ── Photo grid ──────────────────────────────────────────────────── */
.mdb-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 6px;
  padding: 14px;
}

/* ── Tile ────────────────────────────────────────────────────────── */
.mdb-tile {
  aspect-ratio: 1;
  border-radius: 10px;
  overflow: hidden;
  cursor: pointer;
  background: #1a1a1a;
  border: 2px solid transparent;
  position: relative;
  transition: transform 0.14s, border-color 0.14s, opacity 0.14s;
}
.mdb-tile:hover {
  transform: scale(1.04);
  border-color: rgba(167,139,250,0.5);
}
.mdb-tile img {
  width: 100%; height: 100%;
  object-fit: cover;
  display: block;
}
/* Placeholder tile */
.mdb-tile-placeholder {
  width: 100%; height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  opacity: 0.2;
  color: #fff;
}
/* Hover overlay label */
.mdb-tile-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0);
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  justify-content: flex-end;
  padding: 8px;
  transition: background 0.16s;
  pointer-events: none;
}
.mdb-tile:hover .mdb-tile-overlay { background: rgba(0,0,0,0.42); }
.mdb-tile-name {
  font-size: 0.68rem;
  font-weight: 600;
  color: #fff;
  opacity: 0;
  transform: translateY(4px);
  transition: opacity 0.16s, transform 0.16s;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.mdb-tile:hover .mdb-tile-name { opacity: 1; transform: translateY(0); }

/* ── Detail view ─────────────────────────────────────────────────── */
.mdb-detail {
  display: flex;
  gap: 0;
  min-height: 400px;
  height: 100%;
}

/* Left image panel */
.mdb-detail-image-wrap {
  flex: 1;
  background: #0a0a0a;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
  min-height: 300px;
}
.mdb-detail-img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
}
.mdb-detail-placeholder {
  font-size: 4rem;
  opacity: 0.12;
  color: #fff;
}

/* Right form panel */
.mdb-detail-form {
  flex: 1;
  padding: 22px 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  overflow-y: auto;
}

/* Field rows */
.mdb-field-row { display: flex; gap: 12px; }
.mdb-grid-2 > .mdb-field { flex: 1; min-width: 0; }
.mdb-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.mdb-label {
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: rgba(255,255,255,0.4);
}
.mdb-input {
  padding: 9px 11px;
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 8px;
  font-size: 0.875rem;
  background: rgba(255,255,255,0.05);
  color: #fff;
  font-family: inherit;
  outline: none;
  width: 100%;
  box-sizing: border-box;
  transition: border-color 0.15s;
}
.mdb-input:focus { border-color: #a78bfa; }
.mdb-input::placeholder { opacity: 0.3; }
.mdb-select { appearance: auto; cursor: pointer; }
.mdb-textarea {
  resize: vertical;
  line-height: 1.6;
  min-height: 110px;
}
.mdb-select option { background: #1a1a1a; color: #fff; }

/* Form footer */
.mdb-form-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 4px;
  margin-top: auto;
}
.mdb-feedback {
  font-size: 0.82rem;
  flex: 1;
  min-height: 1.1em;
}
.mdb-feedback.success { color: #4ade80; }
.mdb-feedback.error   { color: #f87171; }

.mdb-save-btn {
  background: #a78bfa;
  border: none;
  border-radius: 9px;
  padding: 9px 22px;
  font-size: 0.875rem;
  font-weight: 700;
  color: #0d0d0d;
  cursor: pointer;
  transition: opacity 0.15s;
  flex-shrink: 0;
}
.mdb-save-btn:hover    { opacity: 0.88; }
.mdb-save-btn:disabled { opacity: 0.4; cursor: not-allowed; }

/* ── Left column (image + URL) ───────────────────────────────────── */
.mdb-detail-left {
  flex: 0 0 36%;
  width: 36%;
  display: flex;
  flex-direction: column;
  border-right: 1px solid rgba(255,255,255,0.07);
}
.mdb-image-url-wrap {
  padding: 10px 14px 12px;
  border-top: 1px solid rgba(255,255,255,0.07);
  display: flex;
  flex-direction: column;
  gap: 5px;
  background: rgba(255,255,255,0.02);
}
.mdb-image-url-input {
  font-size: 0.75rem;
  font-family: monospace;
  opacity: 0.6;
  cursor: text;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.mdb-image-url-input:focus { opacity: 0.9; }

/* ── Read-only mode ───────────────────────────────────────────────── */
.mdb-detail-form.readonly .mdb-editable {
  background: transparent;
  border-color: transparent;
  opacity: 0.85;
}
/* Inputs and selects: block interaction entirely */
.mdb-detail-form.readonly input.mdb-editable,
.mdb-detail-form.readonly select.mdb-editable {
  pointer-events: none;
  cursor: default;
}
/* Textarea: allow scroll but block editing */
.mdb-detail-form.readonly textarea.mdb-editable {
  pointer-events: auto;
  cursor: default;
  user-select: text;
  -webkit-user-select: text;
}
.mdb-detail-form.readonly .mdb-editable:focus {
  border-color: transparent;
  outline: none;
}

/* ── Edit button ──────────────────────────────────────────────────── */
.mdb-edit-btn {
  background: rgba(255,255,255,0.07);
  border: 1px solid rgba(255,255,255,0.18);
  border-radius: 9px;
  padding: 9px 22px;
  font-size: 0.875rem;
  font-weight: 600;
  color: #fff;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s;
  flex-shrink: 0;
}
.mdb-edit-btn:hover { background: rgba(255,255,255,0.13); border-color: rgba(255,255,255,0.3); }

/* ── Mobile ──────────────────────────────────────────────────────── */
@media (max-width: 640px) {
  .mdb-overlay { padding: 0; }
  .mdb-modal {
    max-width: 100%; max-height: 100dvh;
    height: 100dvh; border-radius: 0; border: none;
  }
  .mdb-grid {
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 4px; padding: 8px;
  }
  .mdb-detail { flex-direction: column; }
  .mdb-detail-left {
    flex: none; width: 100%; border-right: none;
    border-bottom: 1px solid rgba(255,255,255,0.07);
  }
  .mdb-detail-image-wrap { height: 200px; }
  .mdb-detail-form { padding: 16px; }
  .mdb-field-row.mdb-grid-2 { flex-direction: column; }
}
</style>

<script>
/* ═══════════════════════════════════════════════════════════════════
   Memories DB Modal — JS
   ═══════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  /* ── DOM refs ── */
  var overlay    = document.getElementById('mdbOverlay');
  var closeBtn   = document.getElementById('mdbCloseBtn');
  var backBtn    = document.getElementById('mdbBackBtn');
  var titleEl    = document.getElementById('mdbTitle');
  var loading    = document.getElementById('mdbLoading');
  var emptyEl    = document.getElementById('mdbEmpty');
  var gridEl     = document.getElementById('mdbGrid');
  var detailEl   = document.getElementById('mdbDetail');
  var detailImg  = document.getElementById('mdbDetailImg');
  var placeholder= document.getElementById('mdbDetailPlaceholder');
  var saveBtn    = document.getElementById('mdbSaveBtn');
  var feedback   = document.getElementById('mdbFeedback');

  var detailForm = document.getElementById('mdbDetailForm');
  var editBtn    = document.getElementById('mdbEditBtn');
  var newBtn     = document.getElementById('mdbNewBtn');

  var fTitle     = document.getElementById('mdbFTitle');
  var fOwner     = document.getElementById('mdbFOwner');
  var fDirection = document.getElementById('mdbFDirection');
  var fTradeDay  = document.getElementById('mdbFTradeDay');
  var fStory     = document.getElementById('mdbFStory');
  var fImageUrl  = document.getElementById('mdbFImageUrl');


  /* ── State ── */
  var currentEventId   = 0;
  var currentEventName = '';
  var allMemories      = [];
  var currentMemId     = null;
  var isNewMemory      = false;

  /* ── Open (called from the memories link) ── */
  window.openMemoriesDbModal = function (eventId, eventName) {
    currentEventId   = eventId;
    currentEventName = eventName;

    titleEl.textContent = 'Memories…';
    showGrid();
    overlay.classList.add('open');
    fetchMemories(eventId);
  };

  /* ── Fetch memories from API ── */
  function fetchMemories(eventId) {
    setState('loading');
    fetch('api/memories_api.php?event_id=' + encodeURIComponent(eventId))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.error) { setState('empty'); return; }
        allMemories = data.memories || [];
        if (!allMemories.length) { setState('empty'); return; }

        // Build header title from the first row's view data
        var m = allMemories[0];
        var titleParts = [];
        if (m.event_Year)      titleParts.push(m.event_Year);
        if (m.event_Name)      titleParts.push(m.event_Name);
        if (m.event_StartDate) {
          var dateStr = fmtDate(m.event_StartDate);
          if (m.event_EndDate && m.event_EndDate !== m.event_StartDate)
            dateStr += ' – ' + fmtDate(m.event_EndDate);
          titleParts.push(dateStr);
        }
        titleEl.textContent = titleParts.join('  ·  ') || 'Memories';

        buildGrid(allMemories);
        setState('grid');
      })
      .catch(function () { setState('empty'); });
  }

  /* ── Build photo grid ── */
  function buildGrid(memories) {
    gridEl.innerHTML = '';
    memories.forEach(function (mem) {
      var tile = document.createElement('div');
      tile.className = 'mdb-tile';
      tile.dataset.id = mem.id;

      if (mem.image_path) {
        var img = document.createElement('img');
        img.src     = mem.image_path;
        img.alt     = mem.title || 'Memory';
        img.loading = 'lazy';
        tile.appendChild(img);
      } else {
        var ph = document.createElement('div');
        ph.className = 'mdb-tile-placeholder';
        ph.innerHTML = '&#128444;';
        tile.appendChild(ph);
      }

      // Hover overlay with title
      var ov = document.createElement('div');
      ov.className = 'mdb-tile-overlay';
      var nm = document.createElement('span');
      nm.className = 'mdb-tile-name';
      nm.textContent = mem.title || ('Memory #' + mem.id);
      ov.appendChild(nm);
      tile.appendChild(ov);

      tile.addEventListener('click', function () { openDetail(mem.id); });
      gridEl.appendChild(tile);
    });
  }

  /* ── Append a single tile to the existing grid ── */
  function addTileToGrid(mem) {
    var tile = document.createElement('div');
    tile.className  = 'mdb-tile';
    tile.dataset.id = mem.id;

    if (mem.image_path) {
      var img = document.createElement('img');
      img.src     = mem.image_path;
      img.alt     = mem.title || 'Memory';
      img.loading = 'lazy';
      tile.appendChild(img);
    } else {
      var ph = document.createElement('div');
      ph.className = 'mdb-tile-placeholder';
      ph.innerHTML = '&#128444;';
      tile.appendChild(ph);
    }

    var ov = document.createElement('div');
    ov.className = 'mdb-tile-overlay';
    var nm = document.createElement('span');
    nm.className   = 'mdb-tile-name';
    nm.textContent = mem.title || ('Memory #' + mem.id);
    ov.appendChild(nm);
    tile.appendChild(ov);

    tile.addEventListener('click', function () { openDetail(mem.id); });
    gridEl.appendChild(tile);
  }

  /* ── Read-only / edit mode toggle ── */
  function setEditMode(on) {
    var editables = detailForm.querySelectorAll('.mdb-editable');
    if (on) {
      detailForm.classList.remove('readonly');
      editables.forEach(function (el) {
        if (el.tagName === 'SELECT') el.disabled = false;
        else el.removeAttribute('readonly');
      });
      editBtn.style.display  = 'none';
      saveBtn.style.display  = 'inline-block';
    } else {
      detailForm.classList.add('readonly');
      editables.forEach(function (el) {
        if (el.tagName === 'SELECT') el.disabled = true;
        else el.setAttribute('readonly', true);
      });
      editBtn.style.display  = 'inline-block';
      saveBtn.style.display  = 'none';
    }
  }

  editBtn.addEventListener('click', function () { setEditMode(true); });

  /* ── Open blank form to create a new memory ── */
  function openNewMemory() {
    isNewMemory  = true;
    currentMemId = null;

    // Clear image
    detailImg.style.display   = 'none';
    placeholder.style.display = 'flex';
    fImageUrl.value = '';

    // Clear all fields
    fTitle.value     = '';
    fOwner.value     = '';
    fDirection.value = '';
    fTradeDay.value  = '';
    fStory.value     = '';

    clearFeedback();
    // Go straight into edit mode so fields are writable
    setState('detail');
    backBtn.style.display = 'inline-block';
    setEditMode(true);
    saveBtn.textContent = 'Create Memory';
  }

  newBtn.addEventListener('click', openNewMemory);

  /* ── Open detail view ── */
  function openDetail(memId) {
    var mem = allMemories.find(function (m) { return m.id == memId; });
    if (!mem) return;

    currentMemId = mem.id;

    // Image
    if (mem.image_path) {
      detailImg.src = mem.image_path;
      detailImg.style.display   = 'block';
      placeholder.style.display = 'none';
    } else {
      detailImg.style.display   = 'none';
      placeholder.style.display = 'flex';
    }

    // Image URL (editable in edit mode)
    fImageUrl.value = mem.image_path || '';

    // Form fields
    fTitle.value     = mem.title        || '';
    fOwner.value     = mem.memory_owner || '';
    fDirection.value = mem.direction    || '';
    fTradeDay.value  = mem.trade_day    || '';
    fStory.value     = mem.story        || '';

    // Always open in read-only mode
    setEditMode(false);
    clearFeedback();
    setState('detail');
    backBtn.style.display = 'inline-block';
  }

  /* ── Save changes (create or update) ── */
  saveBtn.addEventListener('click', function () {
    saveBtn.disabled    = true;
    saveBtn.textContent = isNewMemory ? 'Creating…' : 'Saving…';
    clearFeedback();

    var payload = {
      title:        fTitle.value.trim(),
      memory_owner: fOwner.value.trim(),
      direction:    fDirection.value,
      trade_day:    fTradeDay.value || null,
      story:        fStory.value.trim(),
      image_path:   fImageUrl.value.trim(),
    };

    if (isNewMemory) {
      payload.action   = 'create';
      payload.event_id = currentEventId;
    } else {
      if (!currentMemId) return;
      payload.action = 'update';
      payload.id     = currentMemId;
    }

    fetch('api/memories_api.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload)
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.success && data.memory) {
        var mem = data.memory;

        if (isNewMemory) {
          // Add to local cache and append a tile to the grid
          allMemories.push(mem);
          addTileToGrid(mem);
          isNewMemory  = false;
          currentMemId = mem.id;

          setFeedback('Memory created!', 'success');
          setTimeout(function () {
            clearFeedback();
            setEditMode(false);
            setState('grid');
            showGrid();
            currentMemId        = null;
            saveBtn.textContent = 'Save Changes';
            // Ensure grid is shown even if it was previously empty
            emptyEl.style.display = 'none';
            gridEl.style.display  = 'grid';
          }, 1000);

        } else {
          // Update local cache
          var idx = allMemories.findIndex(function (m) { return m.id == currentMemId; });
          if (idx !== -1) {
            allMemories[idx] = mem;
            var tile = gridEl.querySelector('.mdb-tile[data-id="' + currentMemId + '"]');
            if (tile) {
              var nm = tile.querySelector('.mdb-tile-name');
              if (nm) nm.textContent = mem.title || ('Memory #' + mem.id);
              var tImg = tile.querySelector('img');
              if (mem.image_path) {
                if (tImg) { tImg.src = mem.image_path; }
                else {
                  var ph = tile.querySelector('.mdb-tile-placeholder');
                  if (ph) { ph.outerHTML = '<img src="' + mem.image_path + '" alt="" style="width:100%;height:100%;object-fit:cover;display:block">'; }
                }
              }
              if (mem.image_path) {
                detailImg.src = mem.image_path;
                detailImg.style.display   = 'block';
                placeholder.style.display = 'none';
              }
            }
          }
          setFeedback('Saved!', 'success');
          setTimeout(function () { setEditMode(false); clearFeedback(); }, 1200);
        }

      } else {
        setFeedback(data.error || 'Save failed.', 'error');
      }
    })
    .catch(function () { setFeedback('Network error. Please try again.', 'error'); })
    .finally(function () {
      saveBtn.disabled = false;
      if (!isNewMemory) saveBtn.textContent = 'Save Changes';
    });
  });

  /* ── Back to grid ── */
  backBtn.addEventListener('click', function () {
    setEditMode(false);
    clearFeedback();
    isNewMemory         = false;
    saveBtn.textContent = 'Save Changes';
    setState('grid');
    showGrid();
    currentMemId = null;
    backBtn.style.display = 'none';
  });

  /* ── Close ── */
  function closeModal() {
    overlay.classList.remove('open');
    setTimeout(function () {
      allMemories         = [];
      currentMemId        = null;
      isNewMemory         = false;
      gridEl.innerHTML    = '';
      backBtn.style.display = 'none';
      saveBtn.textContent = 'Save Changes';
      setState('loading');
    }, 220);
  }

  closeBtn.addEventListener('click', closeModal);
  overlay.addEventListener('click', function (e) {
    if (e.target === overlay) closeModal();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && overlay.classList.contains('open')) closeModal();
  });

  /* ── State helpers ── */
  function setState(state) {
    loading.style.display  = state === 'loading' ? 'flex'  : 'none';
    emptyEl.style.display  = state === 'empty'   ? 'flex'  : 'none';
    gridEl.style.display   = state === 'grid'    ? 'grid'  : 'none';
    detailEl.style.display = state === 'detail'  ? 'flex'  : 'none';
  }

  function showGrid() {
    backBtn.style.display = 'none';
  }

  function setFeedback(msg, type) {
    feedback.textContent = msg;
    feedback.className   = 'mdb-feedback' + (type ? ' ' + type : '');
  }

  function clearFeedback() {
    feedback.textContent = '';
    feedback.className   = 'mdb-feedback';
  }

  function fmtDate(iso) {
    if (!iso) return '';
    var parts  = iso.split('-');
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    return months[parseInt(parts[1], 10) - 1] + ' ' + parseInt(parts[2], 10) + ' ' + parts[0];
  }

})();
</script>