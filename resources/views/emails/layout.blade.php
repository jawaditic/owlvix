<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Owlvix</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; -webkit-text-size-adjust: 100%; }
        a { text-decoration: none; }
        @media (max-width: 600px) {
            .container { width: 100% !important; }
            .px { padding-left: 22px !important; padding-right: 22px !important; }
            .hero-owl { display: none !important; }
            .h1 { font-size: 26px !important; }
            .stack { display: block !important; width: 100% !important; }
        }
    </style>
</head>
@php
    $font = "Inter, 'Segoe UI', Helvetica, Arial, sans-serif";
    $display = "Poppins, 'Segoe UI', Helvetica, Arial, sans-serif";
@endphp
<body style="margin:0;padding:0;background:#eef2f7;">
    {{-- Inbox preview text --}}
    <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#eef2f7;opacity:0;">
        @yield('preheader')&nbsp;&#847;&nbsp;&#847;&nbsp;&#847;&nbsp;&#847;&nbsp;&#847;&nbsp;&#847;&nbsp;&#847;&nbsp;&#847;
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef2f7;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;">

                    {{-- Dark hero --}}
                    <tr>
                        <td bgcolor="#070b14" style="background:#070b14;background-image:radial-gradient(120% 140% at 85% 100%, #0f3d29 0%, #070b14 55%);border-radius:20px 20px 0 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td class="px" style="padding:26px 36px 0;">
                                        <a href="{{ url('/') }}" style="font-family:{{ $display }};font-size:20px;font-weight:700;color:#ffffff;">
                                            <img src="{{ asset('apple-touch-icon.png') }}" width="30" height="30" alt="" style="vertical-align:middle;border-radius:8px;border:0;margin-right:8px;">Owl<span style="color:#1ec773;">vix</span>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px" style="padding:22px 36px 0;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td valign="bottom" style="padding-bottom:30px;">
                                                    @hasSection('eyebrow')
                                                        <p style="margin:0 0 10px;font-family:{{ $font }};font-size:12px;font-weight:600;letter-spacing:1.6px;text-transform:uppercase;color:#1ec773;">@yield('eyebrow')</p>
                                                    @endif
                                                    <h1 class="h1" style="margin:0;font-family:{{ $display }};font-size:30px;line-height:1.2;font-weight:700;color:#ffffff;">@yield('headline')</h1>
                                                    @hasSection('subline')
                                                        <p style="margin:12px 0 0;font-family:{{ $font }};font-size:15px;line-height:1.6;color:#a5b4c8;">@yield('subline')</p>
                                                    @endif
                                                </td>
                                                @hasSection('owl')
                                                    <td class="hero-owl" width="150" valign="bottom" align="right" style="padding-left:12px;">
                                                        <img src="{{ asset('images/owl.png') }}" width="150" alt="" style="display:block;border:0;width:150px;height:auto;">
                                                    </td>
                                                @endif
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr><td style="height:4px;line-height:4px;font-size:0;background:#1ec773;background-image:linear-gradient(90deg,#1ec773,#4f8cff);">&nbsp;</td></tr>
                            </table>
                        </td>
                    </tr>

                    {{-- White body --}}
                    <tr>
                        <td class="px" bgcolor="#ffffff" style="background:#ffffff;padding:34px 36px 30px;font-family:{{ $font }};font-size:15px;line-height:1.65;color:#1e293b;">
                            @yield('body')
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td class="px" bgcolor="#f8fafc" style="background:#f8fafc;border-top:1px solid #e2e8f0;border-radius:0 0 20px 20px;padding:22px 36px;font-family:{{ $font }};font-size:12px;line-height:1.6;color:#64748b;">
                            @hasSection('footer_note')
                                <p style="margin:0 0 8px;">@yield('footer_note')</p>
                            @endif
                            <p style="margin:0;">
                                <strong style="color:#0f172a;">Owlvix</strong> · Web apps, mobile apps &amp; AI ·
                                <a href="{{ url('/') }}" style="color:#17a85f;font-weight:600;">owlvix.com</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:18px 0 0;font-family:{{ $font }};font-size:11px;color:#94a3b8;">
                            © {{ date('Y') }} Owlvix. Made with care (and an owl).
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
