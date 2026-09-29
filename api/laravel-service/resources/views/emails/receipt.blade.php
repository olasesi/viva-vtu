<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; background: #f5f7fa; margin: 0; padding: 24px;">
    <div style="max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden;">
        <div style="background: #0f172a; color: #ffffff; padding: 20px 28px;">
            <h2 style="margin: 0;">{{ $title }}</h2>
        </div>
        <div style="padding: 28px;">
            <p>Hi {{ $user->first_name }},</p>
            <p>{{ $lede }}</p>

            <table style="width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 14px;">
                <tr>
                    <td style="padding: 6px 0; color: #64748b;">Reference</td>
                    <td style="padding: 6px 0; text-align: right; font-weight: bold;">{{ $transaction->reference }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #64748b;">Service</td>
                    <td style="padding: 6px 0; text-align: right; font-weight: bold;">{{ $category }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #64748b;">Amount</td>
                    <td style="padding: 6px 0; text-align: right; font-weight: bold;">&#8358;{{ $amount }}</td>
                </tr>
                @if($transaction->status === 'successful')
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Status</td>
                        <td style="padding: 6px 0; text-align: right; color: #16a34a; font-weight: bold;">Successful</td>
                    </tr>
                @endif
                <tr>
                    <td style="padding: 6px 0; color: #64748b;">Date</td>
                    <td style="padding: 6px 0; text-align: right;">{{ $transaction->completed_at?->format('d M Y, H:i') ?? $transaction->created_at->format('d M Y, H:i') }}</td>
                </tr>
            </table>

            <p style="margin-top: 24px; color: #64748b; font-size: 13px;">Thank you for using VIVAVTU.</p>
        </div>
    </div>
</body>
</html>