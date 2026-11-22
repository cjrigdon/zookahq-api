<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#1e293b;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;">
        <tr>
            <td style="padding:20px 24px;background:#0f766e;color:#ffffff;font-size:18px;font-weight:bold;">
                ZookaHQ
            </td>
        </tr>
        <tr>
            <td style="padding:24px;">
                <h1 style="margin:0 0 12px;font-size:20px;">{{ $title }}</h1>
                <p style="margin:0;line-height:1.5;font-size:15px;">{{ $bodyText }}</p>
                @if ($actionUrl)
                    <p style="margin:20px 0 0;font-size:13px;color:#64748b;">
                        Open ZookaHQ and go to {{ $actionUrl }}
                    </p>
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
