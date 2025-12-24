<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <title>Check-in Scanner</title>
  <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
  <style>
    :root{--bg:#0b1220;--card:#111827;--muted:#9ca3af;--ok:#22c55e;--warn:#f59e0b;--bad:#ef4444;--line:#273244}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:var(--bg);color:#e5e7eb;margin:0;padding:16px}
    .wrap{max-width:980px;margin:0 auto}
    .top{display:flex;gap:12px;align-items:center;justify-content:space-between;margin-bottom:12px}
    .card{background:var(--card);border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.35);padding:14px}
    .row{display:flex;gap:12px;flex-wrap:wrap}
    .left{flex:1 1 540px}
    .right{flex:1 1 360px}
    .muted{color:var(--muted);font-size:14px}
    .btn{display:inline-block;border:1px solid var(--line);background:#0b1220;color:#e5e7eb;padding:10px 12px;border-radius:12px;text-decoration:none}
    button.btn{cursor:pointer}
    .status{font-weight:800; font-size:20px; letter-spacing:0.6px}
    .status.ok{color:var(--ok)}
    /* Use red for 'already admitted' to be more visible */
    .status.warn{color:var(--bad)}
    .status.bad{color:var(--bad)}
    #preview{width:100%;border-radius:14px;border:1px solid var(--line);background:#000;max-height:320px;object-fit:cover}
    #preview video { object-fit: cover; width: 100%; height: auto; max-height: 320px; }
    .kpis{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px}
    .kpi{flex:1 1 140px;border:1px solid var(--line);border-radius:12px;padding:10px;background:#0b1220}
    .kpi .label{font-size:12px;color:var(--muted)}
    .kpi .value{font-size:18px;font-weight:800;margin-top:2px}
    .log{max-height:52vh;overflow:auto;border:1px solid var(--line);border-radius:12px;background:#0b1220}
    .log-item{padding:10px 12px;border-bottom:1px solid var(--line)}
    .log-item:last-child{border-bottom:0}
    .log-name{font-weight:800}
    .log-meta{color:var(--muted);font-size:12px;margin-top:2px}
    .pill{display:inline-block;font-size:12px;padding:2px 8px;border-radius:999px;border:1px solid var(--line);margin-left:8px}
    .pill.ok{border-color:rgba(34,197,94,.45);color:var(--ok)}
    .pill.warn{border-color:rgba(245,158,11,.45);color:var(--warn)}
    .pill.bad{border-color:rgba(239,68,68,.45);color:var(--bad)}
    .pill.drink-pill{border-color:rgba(34,197,94,.55);color:#0b1220;background:linear-gradient(135deg,#22c55e 0%,#a3e635 100%);}
    input{width:100%;padding:12px;border-radius:12px;border:1px solid var(--line);background:#0b1220;color:#e5e7eb;font-size:16px;box-sizing:border-box}
    .search-result { padding: 10px 12px; border: 1px solid var(--line); border-radius: 8px; margin-top: 8px; cursor: pointer; background: #0b1220; transition: all 0.2s; }
    .search-result:hover { background: #111827; border-color: #3f4651; }
    .search-result-name { font-weight: 700; }
    .search-result-email { font-size: 12px; color: var(--muted); margin-top: 2px; }
    .search-result-status { font-size: 12px; margin-top: 4px; }
    .search-result-status.checked-in { color: var(--ok); }
    .search-result-status.not-checked-in { color: var(--warn); }
    @keyframes scanFlash { 0% { background: rgba(34, 197, 94, 0.3); } 100% { background: rgba(34, 197, 94, 0); } }
    #preview.scan-detected { animation: scanFlash 0.6s ease-out; }
    @keyframes fullScreenFlashGreen { 0% { background: rgba(34, 197, 94, 0.6); } 100% { background: rgba(34, 197, 94, 0); } }
    @keyframes fullScreenFlashRed { 0% { background: rgba(239, 68, 68, 0.6); } 100% { background: rgba(239, 68, 68, 0); } }
    #flashOverlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 9999; }
    #flashOverlay.flash-green { animation: fullScreenFlashGreen 0.6s ease-out; }
    #flashOverlay.flash-red { animation: fullScreenFlashRed 0.6s ease-out; }
    .result-card { margin-top: 12px; min-height: 80px; display: flex; flex-direction: column; justify-content: center; align-items: center; }
    .result-name { font-size: 32px; font-weight: 900; color: var(--ok); text-align: center; word-break: break-word; }
    .result-name.already { color: var(--bad); }
    .result-meta { font-size: 18px; font-weight:800; color: var(--muted); margin-top: 8px; text-align: center; }
    .result-meta.already { color: var(--bad); }
    .result-meta.ok { color: var(--ok); }
    .result-details { font-size: 15px; color: var(--muted); margin-top: 6px; text-align: center; }
    .result-details.ok { color: var(--ok); }
    .result-details.warn { color: var(--warn); }
    .result-details.bad { color: var(--bad); }
    .drink-badge { display: none; align-items: center; gap: 10px; background: linear-gradient(135deg, #0ea5e9 0%, #22c55e 100%); color: #0b1220; padding: 10px 12px; border-radius: 12px; border: 1px solid var(--line); margin-top: 10px; box-sizing: border-box; width: 100%; }
    .drink-icon { width: 34px; height: 34px; background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none"><path fill="%23facc15" d="M16 6h32v6L36 32v16h-8V32L16 12z"/><path fill="%23fbbf24" d="M20 12h24l-8 16H28z"/><path fill="%2322c55e" d="M24 48h16v6a8 8 0 0 1-16 0z"/><path fill="%230ea5e9" d="M26 6h12v4H26z"/><circle cx="24" cy="20" r="3" fill="%23f59e0b"/><circle cx="40" cy="22" r="3" fill="%23f59e0b"/></svg>'); background-size: contain; background-repeat: no-repeat; background-position: center; flex-shrink: 0; }
    .drink-text { display: flex; flex-direction: column; }
    .drink-text .title { font-weight: 800; font-size: 15px; }
    .drink-text .note { font-size: 13px; color: rgba(11,18,32,0.7); }
  </style>
</head>
<body>
  <div id="flashOverlay"></div>
  <div class="wrap">
    <div class="top">
      <div>
        <div style="font-size:20px;font-weight:900">🥯 Check-in scanner</div>
      </div>
      <div style="display:flex;align-items:center;gap:8px">
        <!-- Show server-side read-only indicator and UI toggle -->
        <?php $serverReadOnly = (bool) env('CHECKIN_READONLY', false); ?>
        <div id="serverReadOnlyBadge" class="muted" style="font-weight:700">
          <?php if ($serverReadOnly): ?>
            Read-only (server)
          <?php endif; ?>
        </div>
        <button id="toggleReadOnlyBtn" class="btn" style="font-size:13px;padding:8px 10px">Read-only: Off</button>
      </div>
    </div>

    <div class="row">
      <div class="left">
          <div class="card">
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
              <div id="modeTabs" style="display:flex;gap:8px">
                <button id="tabScan" class="btn" style="padding:8px 12px">Scan</button>
                <button id="tabSearch" class="btn" style="padding:8px 12px">Search</button>
              </div>
              <div style="margin-left:auto"></div>
            </div>
            <div id="preview"></div>
            <div id="fullScreenScanner" style="display:none;position:fixed;inset:0;background:#000;z-index:10000;padding:12px;box-sizing:border-box">
              <button id="closeFullScanner" class="btn" style="position:absolute;top:12px;right:12px;z-index:10001">Close</button>
              <div id="fullPreview" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;">
                <!-- video will be attached here when full-screen -->
              </div>
            </div>

          <div class="card result-card" id="resultCard" style="display:none;">
            <div class="result-name" id="resultName"></div>
            <div class="result-meta" id="resultMeta"></div>
            <div class="result-details" id="resultDetails"></div>
            <div class="drink-badge" id="drinkBadge">
              <div class="drink-icon" aria-hidden="true"></div>
              <div class="drink-text">
                <div class="title">Drink eligible</div>
                <div class="note">Pregame registration found</div>
              </div>
            </div>
          </div>

          <div id="kpis" class="kpis">
            <div class="kpi">
              <div class="label">Status</div>
              <div id="status" class="value status">Ready</div>
            </div>
            <div class="kpi">
              <div class="label">Prior scan</div>
              <div id="lastName" class="value">—</div>
            </div>
          </div>
        </div>

        <div id="searchCard" class="card" style="margin-top:12px">
          <div id="searchCardHeader" style="font-weight:800;margin-bottom:8px">Search by name</div>
          <input id="search" placeholder="First or last name" autocomplete="off" inputmode="text" />
          <div id="searchResults" style="margin-top:8px; max-height:300px; overflow-y:auto;"></div>
        </div>
      </div>

      <div class="right">
        <div class="card">
          <div style="font-weight:800;margin-bottom:8px">Recent scans</div>
          <div id="log" class="log"></div>
        </div>
      </div>
    </div>
  </div>

  <script>
    const scanUrl = @json(route('checkin.scan'));
    const reverseUrl = @json(route('checkin.reverse'));
    const serverReadOnly = @json((bool) env('CHECKIN_READONLY', false));
    const csrf = @json(csrf_token());
    // If the page was opened with ?checkin_token=... include it for subsequent POSTs.
    // Fall back to server env only when present (use carefully).
    const checkinToken = @json(request()->query('checkin_token') ?? env('CHECKIN_TOKEN', null));

    const els = {
      preview: document.getElementById('preview'),
      status: document.getElementById('status'),
      lastName: document.getElementById('lastName'),
      log: document.getElementById('log'),
      resultCard: document.getElementById('resultCard'),
      resultName: document.getElementById('resultName'),
      resultMeta: document.getElementById('resultMeta'),
      resultDetails: document.getElementById('resultDetails'),
      drinkBadge: document.getElementById('drinkBadge'),
      search: document.getElementById('search'),
      searchResults: document.getElementById('searchResults'),
    };

    const state = {
      scannedCount: 0,
      dedupe: new Map(),
      priorName: '—',
      currentName: null,
       resultHideTimer: null,
      // Search request id to ignore out-of-order responses
      searchReqId: 0,
      // UI-level read-only flag (stored in localStorage)
      uiReadOnly: (localStorage.getItem('checkin_ui_readonly') === '1'),
      // UI mode: 'scan' or 'search'
      mode: 'scan',
      fullScreen: false,
    };

    function setStatus(text, kind) {
      els.status.textContent = text;
      els.status.classList.remove('ok','warn','bad');
      if (kind) els.status.classList.add(kind);
    }

    function beep(ok) {
      // Sound/vibration disabled by request; keep function for call sites.
      return;
    }

    function flashScanDetected() {
      els.preview.classList.remove('scan-detected');
      void els.preview.offsetWidth; // Trigger reflow
      els.preview.classList.add('scan-detected');
    }

    function flashScreen(isSuccess) {
      const overlay = document.getElementById('flashOverlay');
      const className = isSuccess ? 'flash-green' : 'flash-red';
      overlay.classList.remove('flash-green', 'flash-red');
      void overlay.offsetWidth; // Trigger reflow
      overlay.classList.add(className);
    }

    function addLogItem({name, status, barcode, drinkEligible}) {
      const div = document.createElement('div');
      div.className = 'log-item';

      const pillKind = status === 'redeemed' ? 'ok' : (status === 'already_redeemed' ? 'warn' : 'bad');
      const pillText = status === 'redeemed' ? 'Checked in' : (status === 'already_redeemed' ? 'Already' : status);

      const drinkHtml = drinkEligible ? ' <span class="pill drink-pill" title="Drink eligible">🍹 Drink</span>' : '';

      div.innerHTML = `
        <div class="log-name">${escapeHtml(name)} <span class="pill ${pillKind}">${escapeHtml(pillText)}</span>${drinkHtml}</div>
        <div class="log-meta">${new Date().toLocaleTimeString()} · ${escapeHtml(barcode || '')}</div>
      `;

      // If this entry represents a redeemed ticket, add a Reverse button
      if (status === 'redeemed' || status === 'already_redeemed') {
        const btn = document.createElement('button');
        btn.className = 'btn';
        btn.style.marginLeft = '8px';
        btn.textContent = 'Reverse';
        btn.addEventListener('click', async (ev) => {
          // Respect server-side read-only and UI toggle
          const effectiveReadOnly = serverReadOnly || state.uiReadOnly;
          if (effectiveReadOnly) {
            alert('Check-in is in read-only mode; reversals are disabled.');
            return;
          }
          ev.preventDefault();
          if (!confirm('Reverse this check-in? This will mark the ticket as not redeemed.')) return;
          try {
            btn.disabled = true;
            btn.textContent = 'Reversing...';
            const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
            if (checkinToken) { headers['X-CHECKIN-TOKEN'] = checkinToken; /* Do not set Authorization to avoid clobbering Basic auth */ }
            const res = await fetch(reverseUrl, {
              method: 'POST', headers, body: JSON.stringify({ barcode }), credentials: 'same-origin'
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok) {
              alert('Reverse failed: ' + (data.status || res.status));
              btn.disabled = false;
              btn.textContent = 'Reverse';
              return;
            }
            // Update the pill to show reversed
            const pill = div.querySelector('.pill');
            if (pill) {
              pill.textContent = 'Reversed';
              pill.className = 'pill bad';
            }
            btn.textContent = 'Reversed';
            btn.disabled = true;
          } catch (err) {
            console.error('Reverse error', err);
            alert('Reverse failed');
            btn.disabled = false;
            btn.textContent = 'Reverse';
          }
        });
        // append button into the meta row
        const meta = div.querySelector('.log-meta');
        if (meta) meta.appendChild(btn);
      }

      els.log.prepend(div);
    }

    // Mode handling (scan vs search)
    const tabScan = document.getElementById('tabScan');
    const tabSearch = document.getElementById('tabSearch');
    const fullScreenScanner = document.getElementById('fullScreenScanner');
    const closeFullScanner = document.getElementById('closeFullScanner');
    const fullPreview = document.getElementById('fullPreview');
    const searchCardEl = document.getElementById('searchCard');
    const kpisEl = document.getElementById('kpis');

    function setMode(m) {
      state.mode = m;
      tabScan.style.opacity = (m === 'scan') ? '1' : '0.6';
      tabSearch.style.opacity = (m === 'search') ? '1' : '0.6';
      if (m === 'scan') {
        // move search card back below KPIs
        if (kpisEl && searchCardEl) {
          const parent = kpisEl.parentNode;
          if (kpisEl.nextSibling) parent.insertBefore(searchCardEl, kpisEl.nextSibling);
          else parent.appendChild(searchCardEl);
        }
        // show camera preview area
        if (!serverReadOnly && !state.uiReadOnly) startScanner();
      } else {
        // search mode: move search card above KPIs and hide camera preview to maximize results space
        if (kpisEl && searchCardEl && kpisEl.parentNode) {
          kpisEl.parentNode.insertBefore(searchCardEl, kpisEl);
        }
        stopScanner();
      }
    }

    tabScan.addEventListener('click', () => {
      if (serverReadOnly) return; // prevent enabling scan in server read-only
      setMode('scan');
    });
    tabSearch.addEventListener('click', () => setMode('search'));

    // Full-screen scanner open/close
    function openFullScanner() {
      fullScreenScanner.style.display = 'block';
      // Start scanner in the fullPreview container
      stopScanner();
      startScanner(fullPreview);
      state.fullScreen = true;
    }
    function closeFullScannerFn() {
      // stop scanner attached to full preview and hide overlay
      stopScanner();
      fullPreview.innerHTML = '';
      fullScreenScanner.style.display = 'none';
      state.fullScreen = false;
      // restore mode-based camera
      if (state.mode === 'scan' && !serverReadOnly && !state.uiReadOnly) startScanner();
    }
    closeFullScanner.addEventListener('click', closeFullScannerFn);

    // Read-only toggle handling
    const toggleBtn = document.getElementById('toggleReadOnlyBtn');
    function refreshToggleButton() {
      const effective = serverReadOnly || state.uiReadOnly;
      toggleBtn.textContent = 'Read-only: ' + (effective ? 'On' : 'Off');
      toggleBtn.disabled = serverReadOnly; // cannot toggle if server enforces read-only
      if (serverReadOnly) toggleBtn.classList.add('btn');
    }
    toggleBtn.addEventListener('click', () => {
      state.uiReadOnly = !state.uiReadOnly;
      localStorage.setItem('checkin_ui_readonly', state.uiReadOnly ? '1' : '0');
      refreshToggleButton();
      // Start/stop scanner based on new state
      if (serverReadOnly || state.uiReadOnly) {
        stopScanner();
      } else {
        startScanner();
      }
    });
    refreshToggleButton();

    // Sync read-only toggle across tabs/windows
    window.addEventListener('storage', (ev) => {
      if (ev.key === 'checkin_ui_readonly') {
        state.uiReadOnly = (ev.newValue === '1');
        refreshToggleButton();
        if (serverReadOnly || state.uiReadOnly) {
          stopScanner();
        } else {
          startScanner();
        }
      }
    });

    function escapeHtml(s) {
      return String(s).replace(/[&<>\"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#39;'}[c]));
    }

    async function lookup(barcode, options = {}) {
      const { proceedWaitlist = false, skipDedupe = false } = options;
      const now = Date.now();
      // Prevent write operations when read-only is active
      if (serverReadOnly || state.uiReadOnly) {
        setStatus('Read-only mode (disabled)', 'bad');
        beep(false);
        return;
      }
      const last = state.dedupe.get(barcode);
      if (!skipDedupe && last && (now - last) < 1500) return;
      state.dedupe.set(barcode, now);

      setStatus('Looking up…', null);

      const headers = {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json'
      };
      if (checkinToken) { 
        headers['X-CHECKIN-TOKEN'] = checkinToken; 
        // Avoid overriding any HTTP Basic auth by omitting Authorization: Bearer here.
      }

      const res = await fetch(scanUrl, {
        method: 'POST',
        headers,
        body: JSON.stringify({ barcode, proceed_waitlist: proceedWaitlist }),
        // Include credentials so browser-supplied HTTP Basic auth is sent with the AJAX request.
        // This lets server-level basic auth (if enabled for /checkin) succeed for the POST.
        credentials: 'same-origin'
      });

      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        if (res.status === 401) {
          setStatus('Unauthorized', 'bad');
        } else if (res.status === 422) {
          setStatus('Invalid request', 'bad');
        } else {
          setStatus('Error', 'bad');
        }
        beep(false);
        flashScreen(false);
        return;
      }

      if (data.status === 'waitlist_confirm') {
        const proceed = confirm('This is a waitlist ticket with admission permitted based on capacity. Please confirm admission.');
        if (proceed) {
          state.dedupe.delete(barcode);
          return lookup(barcode, { proceedWaitlist: true, skipDedupe: true });
        }
        setStatus('Waitlist admission canceled', 'warn');
        els.lastName.textContent = '—';
        els.drinkBadge.style.display = 'none';
        els.resultCard.style.display = 'none';
        addLogItem({ name: data.name || 'Unknown', status: 'waitlist_cancelled', barcode, drinkEligible: !!data.drink_eligible });
        beep(false);
        flashScreen(false);
        return;
      }

      if (!data.ok) {
        setStatus('Not found', 'bad');
        els.lastName.textContent = '—';
        els.drinkBadge.style.display = 'none';
        addLogItem({ name: 'Not found', status: data.status || 'not_found', barcode, drinkEligible: false });
        beep(false);
        flashScreen(false);
        els.resultCard.style.display = 'none';
        return;
      }

      const good = data.status === 'redeemed' || data.status === 'already_redeemed';
      setStatus(data.status === 'redeemed' ? 'Checked in' : 'Already checked in', data.status === 'redeemed' ? 'ok' : 'warn');
      
      // Only update prior name if current name is different
      if (state.currentName !== (data.name || 'Unknown')) {
        state.priorName = state.currentName || '—';
        state.currentName = data.name || 'Unknown';
        els.lastName.textContent = state.priorName;
      }
      
      state.scannedCount += 1;
      addLogItem({ name: data.name || 'Unknown', status: data.status, barcode, drinkEligible: !!data.drink_eligible });
      beep(good);
      
      // Flash green for new check-in, red for already used
      const isNewCheckIn = data.status === 'redeemed';
      flashScreen(isNewCheckIn);
      
       // Show result card (persist until next scan) and auto-hide after 10s
       els.resultName.textContent = data.name || 'Unknown';
       const isAlready = data.status === 'already_redeemed';
       els.resultName.classList.toggle('already', isAlready);
       els.resultMeta.textContent = isAlready ? 'Already admitted ✕' : 'Checked in ✓';
       // Toggle meta classes so styling (red/green) applies to the status text
       els.resultMeta.classList.toggle('already', isAlready);
       els.resultMeta.classList.toggle('ok', !isAlready);
      // Show minimal details (ticket type if provided)
      els.resultDetails.textContent = data.ticket_type || '';
      els.resultDetails.className = 'result-details';
       els.resultCard.style.display = 'flex';
       if (data.drink_eligible) {
         els.drinkBadge.style.display = 'flex';
       } else {
         els.drinkBadge.style.display = 'none';
       }
       // Clear existing hide timer
       if (state.resultHideTimer) {
         clearTimeout(state.resultHideTimer);
         state.resultHideTimer = null;
       }
       // Auto-hide after 10 seconds
       state.resultHideTimer = setTimeout(() => {
         els.resultCard.style.display = 'none';
         state.resultHideTimer = null;
       }, 10000);
    }

    // Scanner management: start/stop behavior, disable when read-only
    const scanner = {
      stream: null,
      video: null,
      canvas: null,
      ctx: null,
      scanning: false,
    };

    function stopScanner() {
      try {
        if (scanner.stream) {
          scanner.stream.getTracks().forEach(t => t.stop());
          scanner.stream = null;
        }
        if (scanner.video && scanner.video.parentNode) {
          scanner.video.pause();
          scanner.video.srcObject = null;
          scanner.video.parentNode.removeChild(scanner.video);
        }
        if (scanner.canvas && scanner.canvas.parentNode) {
          scanner.canvas.parentNode.removeChild(scanner.canvas);
        }
      } catch (e) {
        console.error('Error stopping scanner', e);
      }
      scanner.video = null; scanner.canvas = null; scanner.ctx = null; scanner.scanning = false;
      els.preview.innerHTML = '';
      setStatus(serverReadOnly || state.uiReadOnly ? 'Read-only mode (camera off)' : 'Ready', serverReadOnly || state.uiReadOnly ? 'bad' : null);
    }

    async function startScanner(containerEl) {
      if (typeof jsQR === 'undefined') {
        console.error('jsQR library not loaded');
        setStatus('Scanner not loaded', 'bad');
        return;
      }
      // If read-only, do not start camera
      if (serverReadOnly || state.uiReadOnly) {
        setStatus('Read-only mode (camera disabled)', 'bad');
        return;
      }
      // create canvas
      const canvas = document.createElement('canvas');
      canvas.style.display = 'none';
      document.body.appendChild(canvas);
      const ctx = canvas.getContext('2d');

      try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        scanner.stream = stream;
        const video = document.createElement('video');
        video.srcObject = stream;
        video.setAttribute('playsinline', 'true');
        video.play();

        // Create a container to hold the video
        const container = (containerEl && containerEl.appendChild) ? containerEl : els.preview;
        container.innerHTML = '';
        container.appendChild(video);

        scanner.video = video;
        scanner.canvas = canvas;
        scanner.ctx = ctx;
        scanner.scanning = true;

        const scan = () => {
          if (!scanner.scanning || !scanner.video || scanner.video.videoWidth <= 0 || scanner.video.videoHeight <= 0) {
            requestAnimationFrame(scan);
            return;
          }
          try {
            scanner.canvas.width = scanner.video.videoWidth;
            scanner.canvas.height = scanner.video.videoHeight;
            scanner.ctx.drawImage(scanner.video, 0, 0);

            const imageData = scanner.ctx.getImageData(0, 0, scanner.canvas.width, scanner.canvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height);

            if (code) {
              // Ignore very short numeric codes (likely false positives)
              const digitCount = (code.data.match(/\d/g) || []).length;
              if (digitCount < 6) {
                setStatus('Ignored short code', 'warn');
                flashScanDetected();
                scanner.scanning = false;
                setTimeout(() => { if (scanner) scanner.scanning = true; }, 1000);
              } else {
                setStatus('Detected: ' + code.data.substring(0, 10) + '...', 'ok');
                flashScanDetected();
                beep(true);
                setTimeout(() => setStatus('Processing...', null), 300);
                lookup(code.data);
                scanner.scanning = false;
                setTimeout(() => { if (scanner) scanner.scanning = true; }, 1500);
              }
            }
          } catch (e) {
            console.error('Scan error:', e);
          }
          requestAnimationFrame(scan);
        };

        video.onloadedmetadata = () => {
          scan();
          console.log('jsQR scanner started');
          setStatus('Scanning...', null);
        };
      } catch (err) {
        console.error('Camera error:', err);
        setStatus('Camera not available', 'warn');
      }
    }

    // Start scanner unless read-only
    // Initialize mode: if server read-only, default to search; otherwise default to scan.
    if (serverReadOnly || state.uiReadOnly) {
      setMode('search');
      setStatus('Read-only mode (camera disabled)', 'bad');
    } else {
      setMode('scan');
    }

    // Search functionality
    // Debounce helper
    function debounce(fn, wait) {
      let t;
      return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), wait);
      };
    }

    const doSearch = async (query, myId) => {
      if (query.length < 2) {
        els.searchResults.innerHTML = '';
        return;
      }
      try {
        const res = await fetch('/api/search-registrants?q=' + encodeURIComponent(query));
        const data = await res.json();

        // Ignore this response if a newer search has been started
        if (myId !== state.searchReqId) return;

        els.searchResults.innerHTML = '';
        if (!data.results || data.results.length === 0) {
          els.searchResults.innerHTML = '<div class="muted" style="padding:10px">No results found</div>';
          return;
        }

        data.results.forEach(person => {
          const div = document.createElement('div');
          div.className = 'search-result';

          const checkedIn = person.redeemed_at !== null;
          const statusClass = checkedIn ? 'checked-in' : 'not-checked-in';
          const statusText = checkedIn ? '✓ Checked in' : 'Not checked in yet';

          // Determine if order_date is today at 5:00pm or later
          let nameHtml = escapeHtml(person.first_name + ' ' + person.last_name);
          if (person.order_date) {
            try {
              const orderDate = new Date(person.order_date);
              const now = new Date();
              const isSameDay = orderDate.getFullYear() === now.getFullYear() &&
                                orderDate.getMonth() === now.getMonth() &&
                                orderDate.getDate() === now.getDate();
              // Only show icon/time when read-only is active (server or UI)
              const effectiveReadOnly = serverReadOnly || state.uiReadOnly;
              if (isSameDay && effectiveReadOnly) {
                // Adjust time by subtracting 5 hours to account for timezone offset
                const adjusted = new Date(orderDate.getTime() - (5 * 60 * 60 * 1000));
                const timeStr = adjusted.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                nameHtml += ' <span title="Order placed today">🟡</span>';
                nameHtml += ` <span class="muted">(ticket purchased at ${escapeHtml(timeStr)})</span>`;
              }
            } catch (e) {
              // ignore parse errors
            }
          }

          let emailHtml = escapeHtml(person.email || '');
          if (person.pregame_name) {
            emailHtml += ' | ' + escapeHtml(person.pregame_name);
          }

          div.innerHTML = `
            <div class="search-result-name">${nameHtml}</div>
            <div class="search-result-email">${emailHtml}</div>
            <div class="search-result-status ${statusClass}">${statusText}</div>
          `;

          div.addEventListener('click', async () => {
            // Some results may not have a `barcode_id`; prefer that, but
            // fall back to `eventbrite_ticket_id` so lookup can find the
            // ticket by either identifier.
            const id = person.barcode_id || person.eventbrite_ticket_id || '';
            await lookup(id);
            els.search.value = '';
            els.searchResults.innerHTML = '';
          });

          els.searchResults.appendChild(div);
        });
      } catch (err) {
        console.error('Search error:', err);
      }
    };

    const debouncedSearch = debounce((q, id) => doSearch(q, id), 180);

    els.search.addEventListener('input', (e) => {
      const query = e.target.value.trim();
      // increment request id so older responses are ignored
      state.searchReqId += 1;
      const myId = state.searchReqId;
      debouncedSearch(query, myId);
    });

    // Collapse camera preview when search input focused (mobile keyboard)
    els.search.addEventListener('focus', () => {
      if (!state.fullScreen) {
        els.preview.classList.add('camera-collapsed');
        // stop scanner to free camera and maximize results visibility
        stopScanner();
      }
    });
    els.search.addEventListener('blur', () => {
      els.preview.classList.remove('camera-collapsed');
      // restore camera if in scan mode
      if (state.mode === 'scan' && !serverReadOnly && !state.uiReadOnly && !state.fullScreen) startScanner();
    });

    // Floating action: open full-screen scanner when tapping the preview
    els.preview.addEventListener('click', () => {
      if (state.mode === 'scan' && !serverReadOnly && !state.uiReadOnly) openFullScanner();
    });
  </script>
</body>
</html>
