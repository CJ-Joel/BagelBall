<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Login</title>
  <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f7fafc;padding:24px} .card{max-width:420px;margin:60px auto;padding:20px;border-radius:8px;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.06)} label{display:block;font-size:13px;color:#4b5563;margin-bottom:6px} input{width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;margin-bottom:12px} button{background:#111827;color:#fff;border:none;padding:10px 14px;border-radius:6px;cursor:pointer} .error{color:#b91c1c;margin-bottom:12px}</style>
</head>
<body>
  <div class="card">
    <h2 style="margin-top:0">Admin Login</h2>
    @if(!empty($error))
      <div class="error">Invalid password.</div>
    @endif
    <form method="post" action="{{ url('/admin/login') }}">
      <label for="password">Password</label>
      <input id="password" name="password" type="password" autocomplete="one-time-code" />
      <input type="hidden" name="device" value="{{ request()->header('User-Agent') }}">
      <button type="submit">Sign in</button>
    </form>
    <p style="margin-top:12px;color:#6b7280;font-size:13px">This login creates a 30-day token passed in the URL — keep the link private.</p>
  </div>
</body>
</html>
