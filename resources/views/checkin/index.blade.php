<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <title>Check-in Scanner</title>
  <script src="https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.js"></script>
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
    #preview{width:100%;border-radius:14px;border:1px solid var(--line);background:#000;max-height:400px;object-fit:cover}
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
  </style>
</head>
<body>
  <div class="wrap">
    <div class="top">
      <div>
        <div style="font-size:20px;font-weight:900">Check-in scanner</div>
        <div class="muted">Scan or manually enter barcode</div>
      </div>
      <form method="post" action="{{ route('checkin.logout') }}">
        <button class="btn" type="submit">Logout</button>
      </form>
    </div>

    <div class="row">
      <div class="left">
        <div class="card">
          <video id="preview" playsinline muted autoplay></video>

          <div class="kpis">
            <div class="kpi">
              <div class="label">Status</div>
              <div id="status" class="value status">Ready</div>
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
        </div>

        <div class="card" style="margin-top:12px">
          <div style="font-weight:800;margin-bottom:8px">Enter barcode</div>
          <input id="manual" placeholder="Scan or paste barcode, then press Enter" autocomplete="off" inputmode="text" autofocus />
          <div class="muted" style="margin-top:8px">Works on any device. Camera will auto-start if available.</div>
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

    const els = {
      preview: document.getElementById('preview'),
      status: document.getElementById('status'),
      lastName: document.getElementById('lastName'),
      count: document.getElementById('count'),
      log: document.getElementById('log'),
      manual: document.getElementById('manual'),
    };

    const state = {
      scannedCount: 0,
      dedupe: new Map(),
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
      if (last && (now - last) < 1500) return;
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
        setStatus('Error', 'bad');
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

    // Try to start camera
    if (typeof Quagga !== 'undefined') {
      Quagga.init({
        inputStream: {
          name: "Live",
          type: "LiveStream",
          target: els.preview,
          constraints: {
            width: 640,
            height: 480,
            facingMode: "environment"
          },
        },
        locator: {
          patchSize: "medium",
          halfSample: true
        },
        numOfWorkers: 2,
        decoder: {
          readers: ["qr_code_reader", "code_128_reader", "ean_reader", "ean_8_reader", "code_39_reader", "code_39_vin_reader", "codabar_reader", "upc_reader", "upc_e_reader", "i2of5_reader", "2of5_reader", "code_93_reader"]
        },
        locate: true
      }, function(err) {
        if (err) {
          console.log("Quagga init error:", err);
          setStatus("Camera not available", "warn");
          return;
        }
        Quagga.start();
        setStatus("Scanning...", null);
      });

      Quagga.onDetected(function(result) {
        const code = result.codeResult.code;
        lookup(code);
      });
    } else {
      setStatus("Scanner not loaded", "bad");
    }

    // Manual entry
    els.manual.addEventListener('keydown', async (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        const val = els.manual.value.trim();
        if (!val) return;
        els.manual.value = '';
        await lookup(val);
      }
    });
  </script>
</body>
</html>
