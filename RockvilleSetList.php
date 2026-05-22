<?php
// ─── RockvilleSetList.php ─────────────────────────────────────────────────
// HTML partial for the Set List tab panel in festivals.php.
// Included inside festivals.php; no PHP data fetching done here.
// All set list data is loaded client-side via AJAX (festivals.php?action=setlist_data).
// Listens for the 'festivalChanged' custom event dispatched by festivals.php.
// ─────────────────────────────────────────────────────────────────────────
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Barlow+Condensed:wght@400;600;700&family=Barlow:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ── Set List panel: all rules scoped to #panel-setlist ──────────────────── */
#panel-setlist {
    --sl-bg:       #0a0a0f;
    --sl-surface:  #111118;
    --sl-surface2: #18181f;
    --sl-border:   #2a2a38;
    --sl-text:     #e8e8f0;
    --sl-text-dim: #7a7a99;

    --tl-ppm:    1.5px;
    --tl-height: calc(var(--tl-ppm) * 810);   /* 11:30AM–1:00AM = 810 min */

    font-family: 'Barlow', sans-serif;
    color: var(--sl-text);
    background: var(--sl-bg);
    border-radius: 12px;
    overflow: hidden;
    min-height: 240px;
}

/* ── Loading / error ── */
#sl-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    min-height: 220px;
    color: var(--sl-text-dim);
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 15px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}
#sl-loading svg { animation: sl-spin 1s linear infinite; }
@keyframes sl-spin { to { transform: rotate(360deg); } }

#sl-error {
    max-width: 540px;
    margin: 48px auto;
    text-align: center;
    padding: 32px;
    background: var(--sl-surface);
    border: 1px solid var(--sl-border);
    border-radius: 12px;
    display: none;
}
#sl-error h2 {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 24px;
    letter-spacing: 0.1em;
    color: #ff5252;
    margin-bottom: 8px;
}
#sl-error p { font-size: 14px; color: var(--sl-text-dim); }

/* ── Header ── */
#sl-header {
    text-align: center;
    padding: 32px 24px 20px;
    border-bottom: 1px solid var(--sl-border);
    background: linear-gradient(180deg, #0d0d1a 0%, var(--sl-bg) 100%);
    display: none;
}
#sl-festival-title {
    font-family: 'Bebas Neue', sans-serif;
    font-size: clamp(28px, 5vw, 56px);
    letter-spacing: 0.04em;
    line-height: 0.95;
    background: linear-gradient(135deg, #fff 0%, var(--apex, #00e5ff) 40%, #fff 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
#sl-festival-sub {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.3em;
    color: var(--sl-text-dim);
    margin-top: 6px;
    text-transform: uppercase;
}

/* ── Controls bar ── */
#sl-controls {
    background: rgba(10,10,15,0.92);
    backdrop-filter: blur(16px);
    border-bottom: 1px solid var(--sl-border);
    padding: 0 24px;
    display: none;
}
#sl-controls-inner {
    max-width: 1600px;
    margin: 0 auto;
    display: flex;
    align-items: stretch;
}
#sl-day-tabs { display: flex; flex: 1; }

.sl-day-tab {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 20px;
    letter-spacing: 0.08em;
    padding: 16px 28px;
    border: none;
    background: none;
    color: var(--sl-text-dim);
    cursor: pointer;
    position: relative;
    transition: color 0.2s;
    white-space: nowrap;
}
.sl-day-tab::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 2px;
    background: var(--apex, #00e5ff);
    transform: scaleX(0);
    transition: transform 0.25s ease;
}
.sl-day-tab:hover { color: var(--sl-text); }
.sl-day-tab.active { color: #fff; }
.sl-day-tab.active::after { transform: scaleX(1); }

#sl-viewer-filters {
    display: none;
    align-items: center;
    gap: 6px;
    padding: 10px 0;
    border-left: 1px solid var(--sl-border);
    padding-left: 16px;
    margin-left: 8px;
    flex-wrap: wrap;
}
.sl-viewer-btn {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 7px 13px;
    border-radius: 6px;
    border: 1px solid var(--sl-border);
    background: transparent;
    color: var(--sl-text-dim);
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}
.sl-viewer-btn:hover { border-color: var(--sl-text-dim); color: var(--sl-text); }
.sl-viewer-btn.active { background: var(--sl-text-dim); border-color: var(--sl-text-dim); color: #000; }

#sl-search-filter {
    display: flex;
    align-items: center;
    padding: 10px 0;
    border-left: 1px solid var(--sl-border);
    padding-left: 20px;
    margin-left: 8px;
}
#sl-search-wrap { position: relative; }
#sl-search-wrap svg {
    position: absolute;
    left: 10px; top: 50%;
    transform: translateY(-50%);
    color: var(--sl-text-dim);
    pointer-events: none;
}
#sl-search-input {
    background: var(--sl-surface2);
    border: 1px solid var(--sl-border);
    border-radius: 6px;
    color: var(--sl-text);
    font-family: 'Barlow', sans-serif;
    font-size: 13px;
    padding: 8px 12px 8px 34px;
    width: 200px;
    outline: none;
    transition: border-color 0.2s;
}
#sl-search-input::placeholder { color: var(--sl-text-dim); }
#sl-search-input:focus { border-color: var(--apex, #00e5ff); }

/* ── Schedule ── */
#sl-schedule {
    max-width: 1600px;
    margin: 0 auto;
    padding: 32px 24px 48px;
    display: none;
}
.sl-day-panel { display: none; }
.sl-day-panel.active { display: block; }

.sl-timeline-outer { display: flex; align-items: flex-start; }
.sl-time-ruler {
    width: 48px;
    flex-shrink: 0;
    position: relative;
    height: var(--tl-height);
    margin-top: 52px;
}
.sl-time-tick {
    position: absolute;
    left: 0;
    width: 100%;
    display: flex;
    align-items: center;
    gap: 4px;
}
.sl-time-tick-label {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 10px;
    font-weight: 600;
    color: var(--sl-text-dim);
    letter-spacing: 0.05em;
    white-space: nowrap;
    line-height: 1;
}
.sl-time-tick-line { flex: 1; height: 1px; background: var(--sl-border); opacity: 0.5; }

.sl-band-grid { display: grid; gap: 4px; flex: 1; align-items: start; }
.sl-stage-col { display: flex; flex-direction: column; }
.sl-stage-col-body {
    position: relative;
    height: var(--tl-height);
    background: repeating-linear-gradient(
        180deg,
        transparent,
        transparent calc(var(--tl-ppm) * 60 - 1px),
        rgba(255,255,255,0.03) calc(var(--tl-ppm) * 60 - 1px),
        rgba(255,255,255,0.03) calc(var(--tl-ppm) * 60)
    );
}
.sl-stage-header {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 20px;
    letter-spacing: 0.12em;
    text-align: center;
    padding: 12px 8px;
    border-radius: 8px 8px 0 0;
    flex-shrink: 0;
    height: 52px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.sl-band-card {
    position: absolute;
    left: 2px; right: 2px;
    background: var(--sl-surface);
    border: 1px solid var(--sl-border);
    border-radius: 6px;
    padding: 7px 8px;
    cursor: default;
    transition: box-shadow 0.15s, border-color 0.15s;
    overflow: hidden;
    box-sizing: border-box;
    z-index: 1;
}
.sl-band-card:hover { z-index: 10; }
.sl-band-name {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.04em;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sl-band-time {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 11px;
    color: var(--sl-text-dim);
    letter-spacing: 0.04em;
    margin-top: 3px;
    display: flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}
.sl-band-meta {
    font-family: 'Barlow', sans-serif;
    font-size: 10px;
    color: var(--sl-text-dim);
    margin-top: 4px;
    line-height: 1.4;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}
.sl-band-prefs {
    position: absolute;
    top: 5px; right: 5px;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
    z-index: 2;
}
.sl-pref-pill {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    padding: 2px 5px;
    border-radius: 3px;
    white-space: nowrap;
    line-height: 1.4;
}
.sl-pref-pill.want { background: rgba(105,240,174,0.18); color: #69f0ae; border: 1px solid rgba(105,240,174,0.4); }
.sl-pref-pill.need { background: rgba(255,82,82,0.18);   color: #ff5252; border: 1px solid rgba(255,82,82,0.4); }
.sl-band-card.dimmed { display: none; }

.sl-no-results {
    display: none;
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    color: var(--sl-text-dim);
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 18px;
    letter-spacing: 0.1em;
}

/* ── Footer ── */
#sl-footer {
    text-align: center;
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.2em;
    color: var(--sl-text-dim);
    text-transform: uppercase;
    padding: 16px;
    border-top: 1px solid var(--sl-border);
    display: none;
}

@media (max-width: 900px) { .sl-band-grid { grid-template-columns: repeat(3, 1fr) !important; } }
@media (max-width: 640px) {
    #sl-controls-inner { flex-direction: column; align-items: stretch; }
    #sl-search-filter  { border-left: none; border-top: 1px solid var(--sl-border); padding-left: 0; margin-left: 0; }
    .sl-band-grid      { grid-template-columns: repeat(2, 1fr) !important; }
    .sl-day-tab        { padding: 14px 16px; font-size: 17px; }
    #sl-search-input   { width: 100%; }
}
</style>

<!-- ── Set List shell ───────────────────────────────────────────────────── -->
<div id="sl-loading">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
    </svg>
    <span>Select a festival to load the set list</span>
</div>

<div id="sl-error"><h2>Error</h2><p id="sl-error-msg"></p></div>

<div id="sl-header">
    <div id="sl-festival-title"></div>
    <div id="sl-festival-sub"></div>
</div>

<div id="sl-controls">
    <div id="sl-controls-inner">
        <nav id="sl-day-tabs"></nav>
        <div id="sl-viewer-filters"></div>
        <div id="sl-search-filter">
            <div id="sl-search-wrap">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
                <input id="sl-search-input" type="text" placeholder="Search artists…">
            </div>
        </div>
    </div>
</div>

<div id="sl-schedule"></div>
<div id="sl-footer"></div>

<script>
(function () {
    const TL_START = 690;   // 11:30 AM in minutes
    const TL_END   = 1500;  // 1:00 AM next day
    const PPM      = 1.5;   // pixels per minute

    let currentDays   = [];
    let activeViewers = new Set(['all']);
    let currentFestId = null;

    const elLoading  = document.getElementById('sl-loading');
    const elError    = document.getElementById('sl-error');
    const elErrMsg   = document.getElementById('sl-error-msg');
    const elHeader   = document.getElementById('sl-header');
    const elTitle    = document.getElementById('sl-festival-title');
    const elSub      = document.getElementById('sl-festival-sub');
    const elControls = document.getElementById('sl-controls');
    const elDayTabs  = document.getElementById('sl-day-tabs');
    const elViewers  = document.getElementById('sl-viewer-filters');
    const elSearch   = document.getElementById('sl-search-input');
    const elSchedule = document.getElementById('sl-schedule');
    const elFooter   = document.getElementById('sl-footer');

    function esc(s) {
        return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function timeToMins(str) {
        if (!str) return null;
        str = str.trim().toUpperCase();
        const m = str.match(/(\d+):(\d+)\s*([AP])M?/);
        if (!m) return null;
        let h = parseInt(m[1]) % 12, min = parseInt(m[2]);
        if (m[3] === 'P') h += 12;
        let mins = h * 60 + min;
        if (mins < 360) mins += 1440;
        return mins;
    }

    function showOnly(which) {
        elLoading.style.display  = which === 'loading' ? 'flex'  : 'none';
        elError.style.display    = which === 'error'   ? 'block' : 'none';
        elHeader.style.display   = which === 'data'    ? 'block' : 'none';
        elControls.style.display = which === 'data'    ? 'block' : 'none';
        elSchedule.style.display = which === 'data'    ? 'block' : 'none';
        elFooter.style.display   = which === 'data'    ? 'block' : 'none';
    }

    function applyStageCSS(stageFormat) {
        let css = '#panel-setlist {\n';
        for (const [name, meta] of Object.entries(stageFormat)) {
            const k = name.toLowerCase();
            css += `  --${k}: ${meta.hex};\n  --${k}-dim: ${meta['hex-dim']};\n  --${k}-glow: ${meta['hex-glow']};\n`;
        }
        css += '}\n';
        for (const [name] of Object.entries(stageFormat)) {
            const k = name.toLowerCase();
            css += `.sl-stage-header[data-stage="${name}"] { color: var(--${k}); background: var(--${k}-dim); border-bottom: 2px solid var(--${k}); text-shadow: 0 0 20px var(--${k}-glow); }\n`;
            css += `.sl-band-card[data-stage="${name}"] { border-left: 3px solid var(--${k}); }\n`;
            css += `.sl-band-card[data-stage="${name}"]:hover { border-color: var(--${k}); box-shadow: 0 4px 20px rgba(0,0,0,0.6), 0 0 12px var(--${k}-glow); }\n`;
        }
        let el = document.getElementById('sl-dynamic-css');
        if (!el) { el = document.createElement('style'); el.id = 'sl-dynamic-css'; document.head.appendChild(el); }
        el.textContent = css;
    }

    function render(data) {
        const { festival_name, days, stage_format, schedule, viewers, transaction_count, festival_id } = data;
        currentDays = days;
        activeViewers = new Set(['all']);
        const stageNames = Object.keys(stage_format);
        const stageCount = stageNames.length || 1;

        applyStageCSS(stage_format);

        elTitle.textContent = festival_name;
        elSub.textContent   = `SET LIST  ·  ${days.length} DAYS  ·  ${stageCount} STAGES`;

        // Day tabs
        elDayTabs.innerHTML = days.map((day, i) =>
            `<button class="sl-day-tab${i===0?' active':''}" data-day="${esc(day)}">${esc(day)}</button>`
        ).join('');
        elDayTabs.querySelectorAll('.sl-day-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                elDayTabs.querySelectorAll('.sl-day-tab').forEach(t => t.classList.remove('active'));
                elSchedule.querySelectorAll('.sl-day-panel').forEach(p => p.classList.remove('active'));
                tab.classList.add('active');
                const panel = document.getElementById('sl-panel-' + tab.dataset.day);
                if (panel) panel.classList.add('active');
                applyFilters();
            });
        });

        // Viewer filters
        if (viewers.length > 0) {
            elViewers.style.display = 'flex';
            elViewers.innerHTML = `<button class="sl-viewer-btn active" data-viewer="all">All</button>` +
                viewers.map(v => `<button class="sl-viewer-btn" data-viewer="${esc(v)}">${esc(v)}</button>`).join('');
            elViewers.querySelectorAll('.sl-viewer-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const v = btn.dataset.viewer;
                    if (v === 'all') { activeViewers.clear(); activeViewers.add('all'); }
                    else {
                        if (activeViewers.has(v)) activeViewers.delete(v);
                        else { activeViewers.delete('all'); activeViewers.add(v); }
                        if (activeViewers.size === 0) activeViewers.add('all');
                    }
                    elViewers.querySelectorAll('.sl-viewer-btn').forEach(b =>
                        b.classList.toggle('active', activeViewers.has(b.dataset.viewer))
                    );
                    applyFilters();
                });
            });
        } else {
            elViewers.style.display = 'none';
            elViewers.innerHTML = '';
        }

        // Schedule
        elSchedule.innerHTML = '';
        days.forEach((day, dayIdx) => {
            const daySchedule = (schedule[day] || {});
            const panel = document.createElement('section');
            panel.className = `sl-day-panel${dayIdx===0?' active':''}`;
            panel.id = 'sl-panel-' + day;

            const outer = document.createElement('div');
            outer.className = 'sl-timeline-outer';

            const ruler = document.createElement('div');
            ruler.className = 'sl-time-ruler';
            ruler.id = 'sl-ruler-' + day;
            outer.appendChild(ruler);

            const grid = document.createElement('div');
            grid.className = 'sl-band-grid';
            grid.id = 'sl-grid-' + day;
            grid.style.gridTemplateColumns = `repeat(${stageCount}, 1fr)`;

            stageNames.forEach(stageName => {
                const col = document.createElement('div');
                col.className = 'sl-stage-col';
                col.dataset.stage = stageName;

                const hdr = document.createElement('div');
                hdr.className = 'sl-stage-header';
                hdr.dataset.stage = stageName;
                hdr.textContent = stageName;
                col.appendChild(hdr);

                const body = document.createElement('div');
                body.className = 'sl-stage-col-body';

                (daySchedule[stageName] || []).forEach(tx => {
                    const prefs = tx.prefs || {};
                    const card = document.createElement('div');
                    card.className = 'sl-band-card';
                    Object.assign(card.dataset, {
                        stage:   stageName,
                        name:    (tx.performer || '').toLowerCase(),
                        start:   tx.start_Time || '',
                        end:     tx.end_Time   || '',
                        viewers: Object.keys(prefs).map(v => v.toLowerCase()).join(' ')
                    });

                    let html = '';
                    if (Object.keys(prefs).length) {
                        html += `<div class="sl-band-prefs">`;
                        for (const [viewer, type] of Object.entries(prefs))
                            html += `<span class="sl-pref-pill ${esc(type)}">${esc(viewer)} ${esc(type[0].toUpperCase()+type.slice(1))}</span>`;
                        html += `</div>`;
                    }
                    html += `<div class="sl-band-name">${esc(tx.performer || 'Unknown Artist')}</div>`;
                    html += `<div class="sl-band-time"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>${esc((tx.start_Time||'')+' – '+(tx.end_Time||''))}</div>`;
                    if (tx.notes) html += `<div class="sl-band-meta">${esc(tx.notes)}</div>`;
                    card.innerHTML = html;
                    body.appendChild(card);
                });

                col.appendChild(body);
                grid.appendChild(col);
            });

            const noRes = document.createElement('div');
            noRes.className = 'sl-no-results';
            noRes.id = 'sl-no-results-' + day;
            noRes.textContent = 'No artists match your search.';
            grid.appendChild(noRes);

            outer.appendChild(grid);
            panel.appendChild(outer);
            elSchedule.appendChild(panel);
        });

        elFooter.textContent = `FESTIVAL ID: ${esc(String(festival_id))}  ·  ${transaction_count} SETS LOADED`;

        showOnly('data');
        positionCards();
        buildRulers();
        elSearch.value = '';
        applyFilters();
    }

    function positionCards() {
        document.querySelectorAll('.sl-band-card').forEach(card => {
            const start = timeToMins(card.dataset.start);
            const end   = timeToMins(card.dataset.end);
            if (start === null || end === null) return;
            const adjEnd = end < start ? end + 1440 : end;
            card.style.height = Math.max((adjEnd - start) * PPM - 2, 28) + 'px';
            card.style.top    = (TL_END - adjEnd) * PPM + 'px';
        });
    }

    function buildRulers() {
        currentDays.forEach(day => {
            const ruler = document.getElementById('sl-ruler-' + day);
            if (!ruler) return;
            ruler.innerHTML = '';
            for (let m = TL_START; m <= TL_END; m += 60) {
                const tick = document.createElement('div');
                tick.className = 'sl-time-tick';
                tick.style.top = (TL_END - m) * PPM + 'px';
                const hh  = Math.floor((m % 1440) / 60);
                const h12 = hh % 12 || 12;
                tick.innerHTML = `<span class="sl-time-tick-label">${h12}${hh >= 12 ? 'P' : 'A'}</span><span class="sl-time-tick-line"></span>`;
                ruler.appendChild(tick);
            }
        });
    }

    function applyFilters() {
        const query   = elSearch.value.toLowerCase().trim();
        const showAll = activeViewers.has('all');
        currentDays.forEach(day => {
            const grid = document.getElementById('sl-grid-' + day);
            if (!grid) return;
            const cards = [...grid.querySelectorAll('.sl-band-card')];
            cards.forEach(c => {
                const nm = !query || (c.dataset.name || '').includes(query);
                let   vm = showAll;
                if (!showAll) {
                    const cv = (c.dataset.viewers || '').split(' ').filter(Boolean);
                    vm = [...activeViewers].some(v => cv.includes(v.toLowerCase()));
                }
                c.classList.toggle('dimmed', !(nm && vm));
            });
            grid.querySelectorAll('.sl-stage-col').forEach(col =>
                col.style.display = col.querySelectorAll('.sl-band-card:not(.dimmed)').length > 0 ? '' : 'none'
            );
            const nr = document.getElementById('sl-no-results-' + day);
            if (nr) nr.style.display = cards.filter(c => !c.classList.contains('dimmed')).length === 0 ? 'block' : 'none';
        });
    }

    elSearch.addEventListener('input', applyFilters);

    async function loadFestival(festivalId) {
        if (!festivalId) {
            currentDays   = [];
            currentFestId = null;
            showOnly('loading');
            return;
        }
        if (festivalId === currentFestId) return;
        currentFestId = festivalId;
        showOnly('loading');

        try {
            const res  = await fetch(`festivals.php?action=setlist_data&festival_id=${encodeURIComponent(festivalId)}`);
            const data = await res.json();
            if (!res.ok || data.error) {
                elErrMsg.textContent = data.error || 'Failed to load set list.';
                showOnly('error');
                currentFestId = null;
            } else {
                render(data);
            }
        } catch (e) {
            elErrMsg.textContent = 'Network error: ' + e.message;
            showOnly('error');
            currentFestId = null;
        }
    }

    // Listen for festival selection from the parent page
    document.addEventListener('festivalChanged', e => {
        loadFestival(e.detail.id || null);
    });

    // In case the festival was already selected before this script ran
    if (window.selectedFestival && window.selectedFestival.id) {
        loadFestival(window.selectedFestival.id);
    }
})();
</script>