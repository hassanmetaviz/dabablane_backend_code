<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Expired</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }

        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .header {
            text-align: center;
            border-bottom: 3px solid #e74c3c;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }

        .alert {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }

        .subscription-details {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }

        .cta-button {
            display: inline-block;
            background-color: #e74c3c;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 20px 0;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 14px;
        }

        .highlight {
            color: #e74c3c;
            font-weight: bold;
        }

        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="logo">DabaBlane</div>
            <h1>Subscription Expired</h1>
        </div>

        <p>Dear {{ $user->name }},</p>

        <div class="alert">
            <strong>🚨 Subscription Expired:</strong> Your subscription has expired and your access to premium features
            has been suspended.
        </div>

        <p>We're sorry to inform you that your subscription has expired. Your access to premium features has been
            temporarily suspended until you renew your subscription.</p>

        <div class="subscription-details">
            <h3>Expired Subscription Details:</h3>
            <ul>
                <li><strong>Plan:</strong> {{ $plan->title }}</li>
                <li><strong>Expired On:</strong> {{ \Carbon\Carbon::parse($purchase->end_date)->format('F j, Y') }}</li>
                <li><strong>Price:</strong> {{ number_format($plan->price_ht, 2) }} MAD</li>
                <li><strong>Duration:</strong> {{ $plan->duration_days }} days</li>
            </ul>
        </div>

        <div class="warning">
            <strong>⚠️ Important:</strong> The following features are now limited:
            <ul>
                <li>Premium listing features</li>
                <li>Advanced analytics</li>
                <li>Priority customer support</li>
                <li>Extended storage limits</li>
            </ul>
        </div>

        <p>Don't worry! You can easily renew your subscription to restore full access to all features. We offer flexible
            renewal options to suit your needs, Please Visit App to renew your subscription.</p>

        {{-- <div style="text-align: center;">
            <a href="{{ config('app.frontend_url') }}/vendor/subscription/renew" class="cta-button">
                Renew Subscription Now
            </a>
        </div> --}}

        <p>If you have any questions about your subscription or need assistance with the renewal process, our support
            team is here to help.</p>

        <div class="footer">
            <p>Best regards,<br>The DabaBlane Team</p>
            <p>
                <strong>Contact Information:</strong><br>
                Email: support@dabablane.com<br>
                Phone: +212 615 170 064
            </p>
            <p><small>This is an automated message. Please do not reply to this email.</small></p>
        </div>
    </div>
</body>

</html>
