<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <title>Check-in Scanner</title>
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
    .status{font-weight:700}
    .status.ok{color:var(--ok)}
    .status.warn{color:var(--warn)}
    .status.bad{color:var(--bad)}
    .viewport{position:relative;border-radius:14px;overflow:hidden;background:#000;border:1px solid var(--line)}
    video{width:100%;height:auto;display:block}
    .overlay{position:absolute;inset:0;pointer-events:none;display:flex;align-items:center;justify-content:center}
    .frame{width:min(70%,320px);aspect-ratio:1/1;border:2px solid rgba(255,255,255,.75);border-radius:18px;box-shadow:0 0 0 9999px rgba(0,0,0,.25)}
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
    input{width:100%;padding:12px;border-radius:12px;border:1px solid var(--line);background:#0b1220;color:#e5e7eb;font-size:16px}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="top">
      <div>
        <div style="font-size:20px;font-weight:900">Check-in scanner</div>
        <div class="muted">Continuous scan (iPhone camera). Hold ticket inside the box.</div>
      </div>
      <form method="post" action="{{ route('checkin.logout') }}">
        <button class="btn" type="submit">Logout</button>
      </form>
    </div>

    <div class="row">
      <div class="left">
        <div class="card">
          <div class="viewport">
            <video id="video" playsinline muted></video>
            <div class="overlay"><div class="frame"></div></div>
          </div>

          <div class="kpis">
            <div class="kpi">
              <div class="label">Status</div>
              <div id="status" class="value status">Starting…</div>
            </div>
            <div class="kpi">
              <div class="label">Last scan</div>
              <div id="lastName" class="value">—</div>
            </div>
            <div class="kpi">
              <div class="label">Scanned</div>
              <div id="count" class="value">0</div>
            </div>
          </div>

          <div style="margin-top:10px" class="muted">
            Tip: for best results, use Safari on iPhone and allow camera.
          </div>
        </div>

        <div class="card" style="margin-top:12px">
          <div style="font-weight:800;margin-bottom:8px">Manual entry (fallback)</div>
          <input id="manual" placeholder="Paste barcode value and press Enter" autocomplete="off" inputmode="text" />
          <div class="muted" style="margin-top:8px">Works if camera scanning is blocked or for testing.</div>
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

  <script type="module">
    // Uses the built-in BarcodeDetector API when available (Safari 17.4+).
    // If BarcodeDetector isn't supported, manual entry still works.

    const scanUrl = @json(route('checkin.scan'));
    const csrf = @json(csrf_token());

    const els = {
      video: document.getElementById('video'),
      status: document.getElementById('status'),
      lastName: document.getElementById('lastName'),
      count: document.getElementById('count'),
      log: document.getElementById('log'),
      manual: document.getElementById('manual'),
    };

    const state = {
      running: false,
      dedupe: new Map(),
      scannedCount: 0,
      lastPayload: null,
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
      if (last && (now - last) < 1500) return; // prevent rapid repeats
      state.dedupe.set(barcode, now);

      setStatus('Looking up…', null);

      const res = await fetch(scanUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json'
        },
        body: JSON.stringify({ barcode })
      });

      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        setStatus('Error (' + res.status + ')', 'bad');
        beep(false);
        return;
      }

      if (!data.ok) {
        setStatus('Not found', 'bad');
        els.lastName.textContent = '—';
        addLogItem({ name: 'Not found', status: data.status || 'not_found', barcode });
        beep(false);
        return;
      }

      const good = data.status === 'redeemed' || data.status === 'already_redeemed';
      setStatus(data.status === 'redeemed' ? 'Checked in' : 'Already checked in', data.status === 'redeemed' ? 'ok' : 'warn');
      els.lastName.textContent = data.name || 'Unknown';
      state.scannedCount += 1;
      els.count.textContent = String(state.scannedCount);
      addLogItem({ name: data.name || 'Unknown', status: data.status, barcode });
      beep(good);
    }

    async function startCamera() {
      const constraints = {
        video: {
          facingMode: { ideal: 'environment' },
          width: { ideal: 1280 },
          height: { ideal: 720 }
        },
        audio: false
      };

      const stream = await navigator.mediaDevices.getUserMedia(constraints);
      els.video.srcObject = stream;
      await els.video.play();
    }

    function getSupportedFormats() {
      // Try to include some common 2D formats.
      // Note: iOS support varies by OS version.
      const wanted = [
        'qr_code',
        'pdf417',
        'aztec',
        'data_matrix',
        'code_128',
      ];
      return wanted;
    }

    async function scanLoop(detector) {
      state.running = true;
      setStatus('Scanning…', 'ok');

      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d', { willReadFrequently: true });

      while (state.running) {
        const v = els.video;
        if (v.readyState >= 2) {
          canvas.width = v.videoWidth;
          canvas.height = v.videoHeight;
          ctx.drawImage(v, 0, 0, canvas.width, canvas.height);

          try {
            const bitmap = await createImageBitmap(canvas);
            const codes = await detector.detect(bitmap);
            bitmap.close();
            for (const c of codes) {
              const raw = (c.rawValue || '').trim();
              if (raw) {
                // Fire and forget; loop continues for continuous scanning.
                lookup(raw);
              }
            }
          } catch (e) {
            // ignore frame errors
          }
        }

        await new Promise(r => setTimeout(r, 120));
      }
    }

    async function init() {
      if (!navigator.mediaDevices?.getUserMedia) {
        setStatus('Camera not available', 'bad');
        return;
      }

      if (!('BarcodeDetector' in window)) {
        setStatus('Scanner unsupported (use manual entry)', 'warn');
        // still start camera for preview; manual entry can be used.
        await startCamera().catch(() => {});
        return;
      }

      await startCamera();
      const detector = new BarcodeDetector({ formats: getSupportedFormats() });
      scanLoop(detector);
    }

    els.manual.addEventListener('keydown', async (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        const val = els.manual.value.trim();
        if (!val) return;
        els.manual.value = '';
        await lookup(val);
      }
    });

    init().catch((e) => {
      setStatus('Failed to start camera', 'bad');
    });
  </script>
</body>
</html>
