<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <title>Check-in Scanner</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#0b1220;color:#e5e7eb;padding:24px;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
    .card{max-width:520px;text-align:center;padding:40px;border-radius:12px;background:#111827;box-shadow:0 10px 30px rgba(0,0,0,.35)}
    h1{margin:0 0 12px;font-size:32px}
    .subtitle{color:#9ca3af;margin-bottom:24px}
    .btn{display:inline-block;padding:14px 28px;border-radius:10px;border:0;background:#22c55e;color:#0b1220;font-weight:700;font-size:16px;text-decoration:none;cursor:pointer}
  </style>
</head>
<body>
  <div class="card">
    <h1>Check-in</h1>
    <div class="subtitle">Scan 2D ticket barcodes</div>
    <form method="post" action="{{ route('checkin.login.post') }}">
      <button class="btn" type="submit">Open scanner →</button>
    </form>
  </div>
</body>
</html>
