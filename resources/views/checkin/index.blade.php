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
    #preview{width:100%;border-radius:14px;border:1px solid var(--line);background:#000;max-height:400px;object-fit:cover}
    #preview video { object-fit: cover; width: 100%; height: auto; max-height: 400px; }
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
  </style>
</head>
<body>
  <div id="flashOverlay"></div>
  <div class="wrap">
    <div class="top">
      <div>
        <div style="font-size:20px;font-weight:900">Check-in scanner</div>
        <div class="muted">Scan or manually enter barcode</div>
      </div>
      <!-- Token-based authentication in use — logout button removed -->
    </div>

    <div class="row">
      <div class="left">
        <div class="card">
          <div id="preview"></div>

          <div class="card result-card" id="resultCard" style="display:none;">
            <div class="result-name" id="resultName"></div>
            <div class="result-meta" id="resultMeta"></div>
            <div class="result-details" id="resultDetails"></div>
          </div>

          <div class="kpis">
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

        <div class="card" style="margin-top:12px">
          <div style="font-weight:800;margin-bottom:8px">Search by name</div>
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
      search: document.getElementById('search'),
      searchResults: document.getElementById('searchResults'),
    };

    const state = {
      scannedCount: 0,
      dedupe: new Map(),
      priorName: '—',
      currentName: null,
       resultHideTimer: null,
    };

    function setStatus(text, kind) {
      els.status.textContent = text;
      els.status.classList.remove('ok','warn','bad');
      if (kind) els.status.classList.add(kind);
    }

    function beep(ok) {
      try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const o = ctx.createOscillator();
        const g = ctx.createGain();
        o.type = 'sine';
        o.frequency.value = ok ? 880 : 220;
        g.gain.value = 0.03;
        o.connect(g); g.connect(ctx.destination);
        o.start();
        setTimeout(() => { o.stop(); ctx.close(); }, 90);
      } catch (e) {}
      if (navigator.vibrate) navigator.vibrate(ok ? 35 : [20, 30, 20]);
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

    function addLogItem({name, status, barcode}) {
      const div = document.createElement('div');
      div.className = 'log-item';

      const pillKind = status === 'redeemed' ? 'ok' : (status === 'already_redeemed' ? 'warn' : 'bad');
      const pillText = status === 'redeemed' ? 'Checked in' : (status === 'already_redeemed' ? 'Already' : status);

      div.innerHTML = `
        <div class="log-name">${escapeHtml(name)} <span class="pill ${pillKind}">${escapeHtml(pillText)}</span></div>
        <div class="log-meta">${new Date().toLocaleTimeString()} · ${escapeHtml(barcode || '')}</div>
      `;

      els.log.prepend(div);
    }

    function escapeHtml(s) {
      return String(s).replace(/[&<>\"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#39;'}[c]));
    }

    async function lookup(barcode) {
      const now = Date.now();
      const last = state.dedupe.get(barcode);
      if (last && (now - last) < 1500) return;
      state.dedupe.set(barcode, now);

      setStatus('Looking up…', null);

      const headers = {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json'
      };
      if (checkinToken) {
        headers['X-CHECKIN-TOKEN'] = checkinToken;
        headers['Authorization'] = 'Bearer ' + checkinToken;
      }

      const res = await fetch(scanUrl, {
        method: 'POST',
        headers,
        body: JSON.stringify({ barcode })
      });

      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        setStatus('Error', 'bad');
        beep(false);
        flashScreen(false);
        return;
      }

      if (!data.ok) {
        setStatus('Not found', 'bad');
        els.lastName.textContent = '—';
        addLogItem({ name: 'Not found', status: data.status || 'not_found', barcode });
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
      addLogItem({ name: data.name || 'Unknown', status: data.status, barcode });
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

    // Try to start camera with jsQR
    if (typeof jsQR !== 'undefined') {
      const canvas = document.createElement('canvas');
      canvas.style.display = 'none';
      document.body.appendChild(canvas);
      const ctx = canvas.getContext('2d');
      
      navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        .then(stream => {
          const video = document.createElement('video');
          video.srcObject = stream;
          video.setAttribute('playsinline', 'true');
          video.play();
          
          // Create a container to hold the video
          const container = els.preview;
          container.innerHTML = '';
          container.appendChild(video);
          
          let scanning = true;
          
          const scan = () => {
            if (!scanning || video.videoWidth <= 0 || video.videoHeight <= 0) {
              requestAnimationFrame(scan);
              return;
            }
            
            try {
              canvas.width = video.videoWidth;
              canvas.height = video.videoHeight;
              ctx.drawImage(video, 0, 0);
              
              const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
              const code = jsQR(imageData.data, imageData.width, imageData.height);
              
              if (code) {
                console.log("QR decoded:", code.data);
                setStatus("Detected: " + code.data.substring(0, 10) + "...", "ok");
                flashScanDetected();
                beep(true);
                setTimeout(() => setStatus("Processing...", null), 300);
                lookup(code.data);
                scanning = false;
                setTimeout(() => scanning = true, 1500);
              }
            } catch (e) {
              console.error("Scan error:", e);
            }
            
            requestAnimationFrame(scan);
          };
          
          video.onloadedmetadata = () => {
            scan();
            console.log("jsQR scanner started");
            setStatus("Scanning...", null);
          };
        })
        .catch(err => {
          console.error("Camera error:", err);
          setStatus("Camera not available", "warn");
        });
    } else {
      console.error("jsQR library not loaded");
      setStatus("Scanner not loaded", "bad");
    }

    // Search functionality
    els.search.addEventListener('input', async (e) => {
      const query = e.target.value.trim();
      els.searchResults.innerHTML = '';
      
      if (query.length < 2) return;
      
      try {
        const res = await fetch('/api/search-registrants?q=' + encodeURIComponent(query));
        const data = await res.json();
        
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
          
          div.innerHTML = `
            <div class="search-result-name">${escapeHtml(person.first_name + ' ' + person.last_name)}</div>
            <div class="search-result-email">${escapeHtml(person.email || '')}</div>
            <div class="search-result-status ${statusClass}">${statusText}</div>
          `;
          
          div.addEventListener('click', async () => {
            await lookup(person.barcode_id);
            els.search.value = '';
            els.searchResults.innerHTML = '';
          });
          
          els.searchResults.appendChild(div);
        });
      } catch (err) {
        console.error('Search error:', err);
      }
    });
  </script>
</body>
</html>
