<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <title>Check-in Login</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#0b1220;color:#e5e7eb;margin:0;padding:32px;display:flex;align-items:center;justify-content:center;height:100vh}
    .card{background:#111827;border-radius:12px;padding:20px;width:420px;box-shadow:0 8px 24px rgba(0,0,0,.45)}
    h1{margin:0 0 12px 0;font-size:20px}
    label{display:block;margin-bottom:6px;color:#9ca3af}
    input{width:100%;padding:10px;border-radius:8px;border:1px solid #273244;background:#0b1220;color:#e5e7eb}
    .btn{margin-top:12px;width:100%;padding:10px;border-radius:8px;background:#22c55e;color:#061018;border:0;font-weight:700;cursor:pointer}
    .muted{color:#9ca3af;font-size:13px;margin-top:8px}
    .error{color:#ef4444;margin-top:8px}
    form p{margin:0}
  </style>
</head>
<body>
  <div class="card">
    <h1>Check-in login</h1>
    <form method="post" action="{{ route('checkin.login.post') }}">
      @csrf
      <label for="password">Password</label>
      <input id="password" name="password" type="password" autocomplete="current-password" autofocus />
      <button class="btn" type="submit">Enter</button>
    </form>
    @if($errors->has('password'))
      <div class="error">{{ $errors->first('password') }}</div>
    @endif
    <div class="muted">Set the password with the environment variable <code>CHECKIN_PASSWORD</code>.</div>
  </div>
</body>
</html>
