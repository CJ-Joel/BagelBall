<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Checkin</title>
  <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f7fafc;padding:24px} .card{max-width:900px;margin:24px auto;padding:20px;border-radius:8px;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.06)} a.button{display:inline-block;background:#111827;color:#fff;padding:8px 12px;border-radius:6px;text-decoration:none}</style>
</head>
<body>
  <div class="card">
    <h2 style="margin-top:0">Check-in</h2>
    <p>Signed in as <strong>{{ $admin->email ?? 'unknown' }}</strong></p>
    <form method="post" action="{{ url('/checkin/logout') }}">
      @csrf
      <button type="submit">Logout</button>
    </form>
    <hr />
    <p>Check-in UI goes here.</p>
  </div>
</body>
</html>
