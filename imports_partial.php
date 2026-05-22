<?php
// ─── imports_partial.php ──────────────────────────────────────────────────────
// HTML partial for the Imports tab panel in festivals.php.
// Included inside <div class="tab-panel" id="panel-imports">.
// Assumes $festivals is already populated by festivals.php.
// All AJAX posts target festivals.php (the parent page).
// ─────────────────────────────────────────────────────────────────────────────
?>
<style>
    /* ── Import tab: scoped variables & resets ── */
    #panel-imports {
        --imp-surface:    #161920;
        --imp-surface-2:  #1e2130;
        --imp-border:     #2a2f45;
        --imp-accent:     #f4a01c;
        --imp-accent-dim: #7a5010;
        --imp-danger:     #e8475f;
        --imp-success:    #2ecc8a;
        --imp-text:       #e8eaf2;
        --imp-text-dim:   #7880a0;
        font-family: 'DM Sans', sans-serif;
        font-size: 15px;
    }

    /* ── Card ── */
    #panel-imports .imp-card {
        background: var(--imp-surface);
        border: 1px solid var(--imp-border);
        border-radius: 8px;
        padding: 28px 32px;
        margin-bottom: 24px;
    }
    #panel-imports .imp-card-title {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 18px;
        letter-spacing: 2px;
        color: var(--imp-text-dim);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    #panel-imports .imp-card-title::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--imp-border);
    }

    /* ── Form controls ── */
    #panel-imports label {
        display: block;
        font-size: 12px;
        font-family: 'DM Mono', monospace;
        color: var(--imp-text-dim);
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 8px;
    }
    #panel-imports select {
        width: 100%;
        max-width: 420px;
        background: var(--imp-surface-2);
        color: var(--imp-text);
        border: 1px solid var(--imp-border);
        border-radius: 6px;
        padding: 10px 14px;
        font-family: 'DM Sans', sans-serif;
        font-size: 15px;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%237880a0' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 14px center;
        cursor: pointer;
        transition: border-color .2s;
    }
    #panel-imports select:focus { outline: none; border-color: var(--imp-accent); }

    /* ── Drop zone ── */
    #panel-imports .imp-drop-zone {
        border: 2px dashed var(--imp-border);
        border-radius: 8px;
        padding: 48px 32px;
        text-align: center;
        cursor: pointer;
        transition: border-color .2s, background .2s;
        position: relative;
    }
    #panel-imports .imp-drop-zone:hover,
    #panel-imports .imp-drop-zone.dragover {
        border-color: var(--imp-accent);
        background: rgba(244,160,28,.04);
    }
    #panel-imports .imp-drop-zone.has-file {
        border-color: var(--imp-success);
        background: rgba(46,204,138,.04);
    }
    #panel-imports .imp-drop-zone input[type="file"] {
        position: absolute;
        inset: 0;
        opacity: 0;
        cursor: pointer;
        width: 100%;
        height: 100%;
    }
    #panel-imports .imp-drop-icon {
        font-size: 36px;
        margin-bottom: 12px;
        display: block;
    }
    #panel-imports .imp-drop-zone p { color: var(--imp-text-dim); font-size: 14px; }
    #panel-imports .imp-file-name {
        color: var(--imp-success);
        font-family: 'DM Mono', monospace;
        font-size: 14px;
        font-weight: 500;
        margin-top: 8px;
    }

    /* ── Buttons ── */
    #panel-imports .imp-btn-row { display: flex; gap: 12px; margin-top: 24px; }
    #panel-imports .imp-btn {
        padding: 11px 28px;
        border-radius: 6px;
        border: none;
        font-family: 'Bebas Neue', sans-serif;
        font-size: 17px;
        letter-spacing: 2px;
        cursor: pointer;
        transition: opacity .2s, transform .1s;
    }
    #panel-imports .imp-btn:active { transform: scale(.97); }
    #panel-imports .imp-btn:disabled { opacity: .35; cursor: not-allowed; transform: none; }
    #panel-imports .imp-btn-preview {
        background: var(--imp-surface-2);
        color: var(--imp-accent);
        border: 1px solid var(--imp-accent-dim);
    }
    #panel-imports .imp-btn-preview:hover:not(:disabled) { background: rgba(244,160,28,.1); }
    #panel-imports .imp-btn-import { background: var(--imp-accent); color: #0d0f14; }
    #panel-imports .imp-btn-import:hover:not(:disabled) { opacity: .88; }
    #panel-imports .imp-btn-reset {
        background: transparent;
        color: var(--imp-text-dim);
        border: 1px solid var(--imp-border);
        font-family: 'DM Sans', sans-serif;
        font-size: 14px;
        letter-spacing: 0;
    }
    #panel-imports .imp-btn-reset:hover { color: var(--imp-text); border-color: var(--imp-text-dim); }

    /* ── Alerts ── */
    #panel-imports .imp-alert {
        border-radius: 6px;
        padding: 14px 18px;
        font-size: 14px;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }
    #panel-imports .imp-alert-danger  { background: rgba(232,71,95,.12);  border: 1px solid rgba(232,71,95,.3);  color: #f08090; }
    #panel-imports .imp-alert-success { background: rgba(46,204,138,.12); border: 1px solid rgba(46,204,138,.3); color: var(--imp-success); }
    #panel-imports .imp-alert-info    { background: rgba(244,160,28,.10); border: 1px solid rgba(244,160,28,.25); color: var(--imp-accent); }

    /* ── Summary bar ── */
    #panel-imports .imp-summary-bar {
        display: flex;
        gap: 32px;
        padding: 16px 24px;
        background: var(--imp-surface-2);
        border: 1px solid var(--imp-border);
        border-radius: 6px;
        margin-bottom: 20px;
    }
    #panel-imports .imp-summary-stat { text-align: center; }
    #panel-imports .imp-summary-stat .num {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 32px;
        color: var(--imp-accent);
        line-height: 1;
    }
    #panel-imports .imp-summary-stat .lbl {
        font-size: 11px;
        font-family: 'DM Mono', monospace;
        color: var(--imp-text-dim);
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-top: 4px;
    }
    #panel-imports .imp-summary-stat.danger .num { color: var(--imp-danger); }
    #panel-imports .imp-summary-stat.ok .num    { color: var(--imp-success); }

    /* ── Error list ── */
    #panel-imports .imp-error-list {
        background: rgba(232,71,95,.07);
        border: 1px solid rgba(232,71,95,.2);
        border-radius: 6px;
        padding: 16px 20px;
        margin-bottom: 20px;
    }
    #panel-imports .imp-error-list h4 {
        color: var(--imp-danger);
        font-size: 13px;
        font-family: 'DM Mono', monospace;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 10px;
    }
    #panel-imports .imp-error-list ul { list-style: none; }
    #panel-imports .imp-error-list li {
        font-size: 13px;
        color: #f08090;
        padding: 3px 0;
        border-bottom: 1px solid rgba(232,71,95,.1);
    }
    #panel-imports .imp-error-list li:last-child { border-bottom: none; }
    #panel-imports .imp-error-list li::before { content: '✕  '; }

    /* ── Preview table ── */
    #panel-imports .imp-table-wrap {
        overflow-x: auto;
        border-radius: 6px;
        border: 1px solid var(--imp-border);
    }
    #panel-imports table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    #panel-imports thead tr { background: var(--imp-surface-2); }
    #panel-imports thead th {
        padding: 10px 14px;
        text-align: left;
        font-family: 'DM Mono', monospace;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--imp-text-dim);
        white-space: nowrap;
        border-bottom: 1px solid var(--imp-border);
    }
    #panel-imports tbody tr {
        border-bottom: 1px solid rgba(42,47,69,.6);
        transition: background .15s;
    }
    #panel-imports tbody tr:hover { background: var(--imp-surface-2); }
    #panel-imports tbody tr.row-error { background: rgba(232,71,95,.08); }
    #panel-imports tbody tr.row-error:hover { background: rgba(232,71,95,.13); }
    #panel-imports tbody td { padding: 9px 14px; color: var(--imp-text); white-space: nowrap; }
    #panel-imports tbody td.mono { font-family: 'DM Mono', monospace; color: var(--imp-text-dim); }

    #panel-imports .imp-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-family: 'DM Mono', monospace;
        font-weight: 500;
    }
    #panel-imports .badge-want { background: rgba(244,160,28,.2); color: var(--imp-accent); }
    #panel-imports .badge-need { background: rgba(46,204,138,.2); color: var(--imp-success); }
    #panel-imports .badge-err  { background: rgba(232,71,95,.2);  color: var(--imp-danger);  }

    #panel-imports .imp-stage-pill {
        display: inline-block;
        padding: 2px 9px;
        border-radius: 20px;
        font-size: 11px;
        font-family: 'DM Mono', monospace;
        background: var(--imp-surface-2);
        border: 1px solid var(--imp-border);
        color: var(--imp-text-dim);
    }

    /* ── Spinner / loader ── */
    #imp-loader {
        position: fixed;
        inset: 0;
        background: rgba(13,15,20,.75);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 999;
        backdrop-filter: blur(2px);
    }
    #imp-loader .loader-box {
        background: var(--card-bg, #161920);
        border: 1px solid var(--border, #2a2f45);
        border-radius: 10px;
        padding: 32px 48px;
        text-align: center;
    }
    #imp-loader .big-spin {
        width: 40px; height: 40px;
        border: 3px solid rgba(244,160,28,.2);
        border-top-color: #f4a01c;
        border-radius: 50%;
        animation: imp-spin .8s linear infinite;
        margin: 0 auto 16px;
    }
    #imp-loader .loader-box p {
        font-family: 'DM Mono', monospace;
        font-size: 13px;
        color: #7880a0;
    }
    @keyframes imp-spin { to { transform: rotate(360deg); } }

    /* ── Confirm modal ── */
    #imp-confirm-modal {
        position: fixed;
        inset: 0;
        background: rgba(13,15,20,.80);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        backdrop-filter: blur(3px);
    }
    #imp-confirm-modal .modal-box {
        background: var(--card-bg, #161920);
        border: 1px solid var(--border, #2a2f45);
        border-radius: 10px;
        padding: 36px 40px;
        max-width: 480px;
        width: 90%;
    }
    #imp-confirm-modal .modal-box h3 {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 22px;
        letter-spacing: 2px;
        color: #e8475f;
        margin-bottom: 14px;
    }
    #imp-confirm-modal .modal-box p {
        font-size: 14px;
        color: #7880a0;
        line-height: 1.6;
        margin-bottom: 8px;
    }
    #imp-confirm-modal .modal-box p strong { color: #e8eaf2; }
    #imp-confirm-modal .modal-divider { border: none; border-top: 1px solid var(--border, #2a2f45); margin: 24px 0; }
    #imp-confirm-modal .modal-btn-row { display: flex; gap: 12px; justify-content: flex-end; }

    #panel-imports .imp-hidden { display: none !important; }
</style>

<!-- ── Loader overlay ── -->
<div id="imp-loader" class="imp-hidden">
    <div class="loader-box">
        <div class="big-spin"></div>
        <p id="imp-loader-msg">Processing...</p>
    </div>
</div>

<!-- ── Existing data confirmation modal ── -->
<div id="imp-confirm-modal" class="imp-hidden">
    <div class="modal-box">
        <h3>⚠ Existing Data Detected</h3>
        <p>There are already <strong id="imp-modal-count"></strong> set times recorded for <strong id="imp-modal-festival-name"></strong>.</p>
        <p>Proceeding will <strong style="color:#e8475f;">permanently delete</strong> all existing set times and viewer preferences for this festival before importing the new data.</p>
        <hr class="modal-divider">
        <div class="modal-btn-row">
            <button class="imp-btn imp-btn-reset" id="imp-modal-cancel">Cancel</button>
            <button class="imp-btn imp-btn-import" id="imp-modal-proceed">Proceed with Import</button>
        </div>
    </div>
</div>

<!-- ── Step 1: Select festival & file ── -->
<div class="imp-card" id="imp-step-upload">
    <div class="imp-card-title">01 — Configure Import</div>

    <div>
        <label>Import File (.xlsx)</label>
        <div class="imp-drop-zone" id="imp-drop-zone">
            <input type="file" id="imp-file-input" accept=".xlsx">
            <span class="imp-drop-icon">📂</span>
            <p>Drag & drop your <strong>.xlsx</strong> file here, or click to browse</p>
            <div class="imp-file-name imp-hidden" id="imp-file-name-display"></div>
        </div>
    </div>

    <div class="imp-btn-row">
        <button class="imp-btn imp-btn-preview" id="imp-btn-preview" disabled>Preview Import</button>
        <button class="imp-btn imp-btn-reset"   id="imp-btn-reset">Reset</button>
    </div>
</div>

<!-- ── Step 2: Preview results ── -->
<div id="imp-preview-section" class="imp-hidden">

    <div class="imp-summary-bar" id="imp-summary-bar"></div>

    <div id="imp-error-block" class="imp-error-list imp-hidden">
        <h4>Errors Detected — Fix before importing</h4>
        <ul id="imp-error-list"></ul>
    </div>

    <div id="imp-clean-block" class="imp-hidden">
        <div class="imp-alert imp-alert-success">
            ✓ &nbsp;No errors detected. Ready to import.
        </div>
        <div class="imp-btn-row" style="margin-top:0; margin-bottom:20px;">
            <button class="imp-btn imp-btn-import" id="imp-btn-import">Import to Database</button>
        </div>
    </div>

    <div id="imp-import-result" class="imp-hidden"></div>

    <div class="imp-card" style="padding: 0; overflow:hidden;">
        <div style="padding: 20px 24px 16px; border-bottom: 1px solid var(--imp-border);">
            <div class="imp-card-title" style="margin-bottom:0;">02 — Preview Data</div>
        </div>
        <div class="imp-table-wrap" style="border:none; border-radius:0;">
            <table id="imp-preview-table">
                <thead id="imp-preview-thead"></thead>
                <tbody id="imp-preview-tbody"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    // ── Use the global festival dropdown from festivals.php ──
    // We create a proxy object that reads from window.selectedFestival
    // so the rest of the code can use festivalSelect.value / .options[].dataset
    // without changes.
    const festivalSelect = {
        get value() { return window.selectedFestival ? window.selectedFestival.id : ''; },
        get options() {
            return [{
                value: window.selectedFestival ? window.selectedFestival.id : '',
                dataset: {
                    name: window.selectedFestival ? window.selectedFestival.name : '',
                    year: window.selectedFestival ? window.selectedFestival.year : ''
                }
            }];
        },
        get selectedIndex() { return 0; }
    };

    // Re-check ready state whenever the global festival changes
    document.addEventListener('festivalChanged', checkReady);

    const dropZone       = document.getElementById('imp-drop-zone');
    const fileInput      = document.getElementById('imp-file-input');
    const fileNameDisp   = document.getElementById('imp-file-name-display');
    const btnPreview     = document.getElementById('imp-btn-preview');
    const btnReset       = document.getElementById('imp-btn-reset');
    const btnImport      = document.getElementById('imp-btn-import');
    const previewSection = document.getElementById('imp-preview-section');
    const loader         = document.getElementById('imp-loader');
    const loaderMsg      = document.getElementById('imp-loader-msg');

    let selectedFile   = null;
    let previewRows    = null;
    let previewViewers = [];
    let importing      = false;

    // ── File selection ──
    fileInput.addEventListener('change', () => {
        if (fileInput.files[0]) setFile(fileInput.files[0]);
    });

    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        const f = e.dataTransfer.files[0];
        if (f && f.name.endsWith('.xlsx')) {
            setFile(f);
        } else {
            showAlert('Only .xlsx files are accepted.', 'danger');
        }
    });

    function setFile(f) {
        selectedFile = f;
        dropZone.classList.add('has-file');
        fileNameDisp.textContent = '📄 ' + f.name;
        fileNameDisp.classList.remove('imp-hidden');
        checkReady();
    }

    // festivalChanged event (from global dropdown) already wired above

    function checkReady() {
        btnPreview.disabled = !(festivalSelect.value && selectedFile);
    }

    // ── Reset ──
    btnReset.addEventListener('click', () => {
        selectedFile = null;
        fileInput.value = '';
        fileNameDisp.classList.add('imp-hidden');
        fileNameDisp.textContent = '';
        dropZone.classList.remove('has-file', 'dragover');
        previewSection.classList.add('imp-hidden');
        previewRows    = null;
        previewViewers = [];
        importing      = false;
        btnPreview.disabled = true;
        document.getElementById('imp-import-result').classList.add('imp-hidden');
    });

    // ── Preview ──
    btnPreview.addEventListener('click', async () => {
        const festivalId   = festivalSelect.value;
        const festivalName = festivalSelect.options[festivalSelect.selectedIndex].dataset.name;

        const fd = new FormData();
        fd.append('action', 'preview');
        fd.append('festival_id', festivalId);
        fd.append('festival_name', festivalName);
        fd.append('import_file', selectedFile);

        showLoader('Parsing spreadsheet...');
        try {
            const res  = await fetch('festivals.php', { method: 'POST', body: fd });
            const data = await res.json();
            hideLoader();
            renderPreview(data);
        } catch(e) {
            hideLoader();
            showAlert('Unexpected error during preview: ' + e.message, 'danger');
        }
    });

    // ── Import ──
    btnImport.addEventListener('click', async () => {
        if (!previewRows) return;

        const festivalId   = festivalSelect.value;
        const festivalName = festivalSelect.options[festivalSelect.selectedIndex].dataset.name;

        showLoader('Checking for existing data...');
        try {
            const checkFd = new FormData();
            checkFd.append('action', 'check_existing');
            checkFd.append('festival_id', festivalId);

            const checkRes  = await fetch('festivals.php', { method: 'POST', body: checkFd });
            const checkData = await checkRes.json();
            hideLoader();

            if (checkData.exists) {
                document.getElementById('imp-modal-count').textContent        = checkData.count + ' set time' + (checkData.count !== 1 ? 's' : '');
                document.getElementById('imp-modal-festival-name').textContent = festivalName;
                document.getElementById('imp-confirm-modal').classList.remove('imp-hidden');
            } else {
                await runImport(festivalId, festivalName);
            }
        } catch(e) {
            hideLoader();
            showAlert('Unexpected error during check: ' + e.message, 'danger');
        }
    });

    // ── Modal buttons ──
    document.getElementById('imp-modal-cancel').addEventListener('click', () => {
        document.getElementById('imp-confirm-modal').classList.add('imp-hidden');
    });

    document.getElementById('imp-modal-proceed').addEventListener('click', async () => {
        document.getElementById('imp-confirm-modal').classList.add('imp-hidden');
        const festivalId   = festivalSelect.value;
        const festivalName = festivalSelect.options[festivalSelect.selectedIndex].dataset.name;
        await runImport(festivalId, festivalName);
    });

    // ── Shared import logic ──
    async function runImport(festivalId, festivalName) {
        if (importing) return;
        importing = true;
        btnImport.disabled = true;

        const fd = new FormData();
        fd.append('action', 'import');
        fd.append('festival_id', festivalId);
        fd.append('festival_name', festivalName);
        fd.append('rows', JSON.stringify(previewRows));

        showLoader('Importing to database...');
        try {
            const res  = await fetch('festivals.php', { method: 'POST', body: fd });
            const data = await res.json();
            hideLoader();

            const resultDiv = document.getElementById('imp-import-result');
            resultDiv.classList.remove('imp-hidden');

            if (data.success) {
                resultDiv.innerHTML = `
                    <div class="imp-alert imp-alert-success">
                        ✓ &nbsp;<strong>Import complete!</strong>
                        &nbsp;${data.trans_count} set times imported,
                        ${data.pref_count} viewer preferences recorded.
                    </div>`;
                btnImport.disabled = true;
                document.getElementById('imp-clean-block').classList.add('imp-hidden');
            } else {
                importing = false;
                btnImport.disabled = false;
                resultDiv.innerHTML = `<div class="imp-alert imp-alert-danger">✕ &nbsp;${escHtml(data.message)}</div>`;
            }
        } catch(e) {
            importing = false;
            btnImport.disabled = false;
            hideLoader();
            showAlert('Unexpected error during import: ' + e.message, 'danger');
        }
    }

    // ── Render preview ──
    function renderPreview(data) {
        previewSection.classList.remove('imp-hidden');
        document.getElementById('imp-import-result').classList.add('imp-hidden');

        previewRows    = data.rows         || [];
        previewViewers = data.viewer_names || [];

        const errorCount = (data.errors || []).length;
        const errorRows  = previewRows.filter(r => r.has_error).length;
        const prefCount  = previewRows.reduce((s, r) => s + r.preferences.length, 0);

        document.getElementById('imp-summary-bar').innerHTML = `
            <div class="imp-summary-stat"><div class="num">${previewRows.length}</div><div class="lbl">Set Times</div></div>
            <div class="imp-summary-stat"><div class="num">${prefCount}</div><div class="lbl">Viewer Prefs</div></div>
            <div class="imp-summary-stat"><div class="num">${previewViewers.length}</div><div class="lbl">Viewers</div></div>
            <div class="imp-summary-stat ${errorRows > 0 ? 'danger' : 'ok'}">
                <div class="num">${errorRows}</div><div class="lbl">Errors</div>
            </div>`;

        const errorBlock = document.getElementById('imp-error-block');
        const errorList  = document.getElementById('imp-error-list');
        const cleanBlock = document.getElementById('imp-clean-block');

        if (errorCount > 0) {
            errorBlock.classList.remove('imp-hidden');
            cleanBlock.classList.add('imp-hidden');
            errorList.innerHTML = (data.errors || []).map(e => `<li>${escHtml(e)}</li>`).join('');
            btnImport.disabled = true;
        } else {
            errorBlock.classList.add('imp-hidden');
            cleanBlock.classList.remove('imp-hidden');
            btnImport.disabled = false;
        }

        const thead = document.getElementById('imp-preview-thead');
        const tbody = document.getElementById('imp-preview-tbody');

        const viewerCols = previewViewers.map(v => `<th>${escHtml(v)}</th>`).join('');
        thead.innerHTML = `<tr>
            <th>#</th><th>Day</th><th>Start</th><th>End</th>
            <th>Performer</th><th>Stage</th>${viewerCols}<th>Status</th>
        </tr>`;

        tbody.innerHTML = previewRows.map(row => {
            const viewerCells = previewViewers.map(v => {
                const pref = row.preferences.find(p => p.viewer === v);
                if (!pref) return '<td>—</td>';
                const badge = pref.want
                    ? `<span class="imp-badge badge-want">W</span>`
                    : `<span class="imp-badge badge-need">N</span>`;
                return `<td>${badge}</td>`;
            }).join('');

            const status = row.has_error
                ? `<span class="imp-badge badge-err">Error</span>`
                : `<span style="color:#2ecc8a;font-size:13px;">✓</span>`;

            return `<tr class="${row.has_error ? 'row-error' : ''}">
                <td class="mono">${row.row_num}</td>
                <td>${escHtml(row.day)}</td>
                <td class="mono">${escHtml(row.start_Time)}</td>
                <td class="mono">${escHtml(row.end_Time)}</td>
                <td>${escHtml(row.performer)}</td>
                <td><span class="imp-stage-pill">${escHtml(row.stage)}</span></td>
                ${viewerCells}
                <td>${status}</td>
            </tr>`;
        }).join('');
    }

    // ── Utilities ──
    function showLoader(msg) {
        loaderMsg.textContent = msg || 'Processing...';
        loader.classList.remove('imp-hidden');
    }
    function hideLoader() {
        loader.classList.add('imp-hidden');
    }
    function showAlert(msg, type) {
        const div = document.createElement('div');
        div.className = `imp-alert imp-alert-${type}`;
        div.textContent = msg;
        document.getElementById('panel-imports').prepend(div);
        setTimeout(() => div.remove(), 6000);
    }
    function escHtml(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
})();
</script>