<!DOCTYPE html>
<html>
<body style="margin:0;background:#faf7f5;font-family:Arial,Helvetica,sans-serif;color:#2b2226">
    <table width="100%" cellpadding="0" cellspacing="0" style="padding:32px 12px">
        <tr><td align="center">
            <table width="480" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:16px;border:1px solid #edd0d7">
                <tr><td style="padding:28px 32px 8px;text-align:center">
                    <div style="font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#9d445c">{{ config('wedplan.business_name') }}</div>
                    <h1 style="font-family:Georgia,serif;font-size:22px;margin:10px 0 4px">Your wedding status PIN</h1>
                    <p style="font-size:14px;color:#57534e;margin:0">for {{ $booking->client_name }} · {{ $booking->event_date->format('F j, Y') }}</p>
                </td></tr>
                <tr><td style="padding:20px 32px;text-align:center">
                    <div style="display:inline-block;background:#fbf5f6;border:1px dashed #cc7d90;border-radius:12px;padding:14px 28px;font-size:32px;letter-spacing:10px;font-weight:bold">{{ $otp }}</div>
                    <p style="font-size:13px;color:#57534e;margin:16px 0 0">Enter this PIN on the verification screen. It expires in {{ $minutes }} minutes and can only be used once.</p>
                </td></tr>
                <tr><td style="padding:0 32px 28px;text-align:center;font-size:12px;color:#78716c">
                    If you didn't scan your wedding QR code just now, someone else may have it. Your details stay protected without this PIN; you may let your planner know.
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
