<!DOCTYPE html>
{{-- Shared error screen. Deliberately self-contained (inline CSS, no Vite
     assets) so it renders even when the frontend build is broken. Every page
     shows a support code (EDU-<status>, plus a per-incident EDU-ERR reference
     on 500s) that maps a user's screenshot straight to the cause. --}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — EduID</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #0b1120; color: #e2e8f0;
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; padding: 24px;
        }
        .card { max-width: 460px; text-align: center; }
        .badge {
            display: inline-block; font-size: 12px; font-weight: 700; letter-spacing: 1.5px;
            color: #a78bfa; background: rgba(139, 92, 246, .12); border: 1px solid rgba(139, 92, 246, .35);
            border-radius: 999px; padding: 6px 14px; margin-bottom: 20px; font-family: ui-monospace, monospace;
        }
        h1 { font-size: 22px; font-weight: 800; color: #f8fafc; margin-bottom: 10px; }
        p.msg { font-size: 14px; line-height: 1.6; color: #94a3b8; margin-bottom: 22px; }
        .ref {
            font-family: ui-monospace, monospace; font-size: 13px; color: #fca5a5;
            background: rgba(244, 63, 94, .08); border: 1px solid rgba(244, 63, 94, .3);
            border-radius: 10px; padding: 10px 14px; margin-bottom: 22px; display: inline-block;
        }
        a.btn {
            display: inline-block; background: #7c3aed; color: #fff; text-decoration: none;
            font-size: 14px; font-weight: 600; padding: 11px 22px; border-radius: 12px;
        }
        a.btn:hover { background: #6d28d9; }
        a.alt { display: inline-block; margin-left: 10px; color: #94a3b8; font-size: 14px; text-decoration: none; padding: 11px 6px; }
        a.alt:hover { color: #e2e8f0; }
        .foot { margin-top: 26px; font-size: 11px; color: #475569; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">@yield('code')</div>
        <h1>@yield('heading')</h1>
        <p class="msg">@yield('message')</p>
        @hasSection('reference')
            <div class="ref">Support reference: @yield('reference')</div><br>
        @endif
        @yield('actions')
        <p class="foot">EduID · If this keeps happening, contact support and quote the code above.</p>
    </div>
</body>
</html>
