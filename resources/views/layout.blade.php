<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kaizen Minimal</title>
  <style>
    :root { --gap: 12px; --font: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Hiragino Sans", "Noto Sans JP", "Helvetica Neue", Arial, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol"; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: var(--font); background: #f6f7f9; color: #222; }
    header { padding: 16px; background: white; border-bottom: 1px solid #e5e7eb; position: sticky; top:0; }
    main { max-width: 720px; margin: 0 auto; padding: 24px; }
    .card { background: white; border: 1px solid #e5e7eb; border-radius: 16px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,.03); }
    label { display:block; margin-top: var(--gap); font-weight: 600; }
    input[type="text"], input[type="datetime-local"], textarea, select {
      width: 100%; padding: 10px 12px; border:1px solid #d1d5db; border-radius: 10px; background: #fff;
    }
    textarea { min-height: 120px; }
    .actions { margin-top: 18px; display:flex; gap: var(--gap); }
    button { appearance: none; border: none; background: #2563eb; color: white; padding: 10px 16px; border-radius: 10px; font-weight: 700; cursor: pointer; }
    .muted { color:#6b7280; font-size: 12px; }
    .ok { background: #ecfdf5; color:#059669; border:1px solid #a7f3d0; padding:12px; border-radius: 10px; margin-bottom: 12px; }
    .err { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:12px; border-radius: 10px; margin-bottom: 12px; }
  </style>
</head>
<body>
  <header>
    <strong>Kaizen Minimal</strong>
    <div class="muted">Salesforce 不具合登録（Heroku / Laravel）</div>
  </header>
  <main>
    <div class="card">
      @yield('content')
    </div>
  </main>
</body>
</html>
