<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Expiring Soon</title>
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
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
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
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="logo">DabaBlane</div>
            <h1>Subscription Expiring Soon</h1>
        </div>

        <p>Dear {{ $user->name }},</p>

        <div class="alert">
            <strong>⚠️ Important Notice:</strong> Your subscription will expire in <span
                class="highlight">{{ $daysRemaining }} day{{ $daysRemaining > 1 ? 's' : '' }}</span>!
        </div>

        <p>We wanted to remind you that your current subscription plan is about to expire. To continue enjoying our
            services without interruption, please renew your subscription.</p>

        <div class="subscription-details">
            <h3>Current Subscription Details:</h3>
            <ul>
                <li><strong>Plan:</strong> {{ $plan->title }}</li>
                <li><strong>Expires:</strong> {{ \Carbon\Carbon::parse($purchase->end_date)->format('F j, Y') }}</li>
                <li><strong>Days Remaining:</strong> {{ $daysRemaining }} day{{ $daysRemaining > 1 ? 's' : '' }}</li>
                <li><strong>Price:</strong> {{ number_format($plan->price_ht, 2) }} MAD</li>
            </ul>
        </div>

        <p>Don't let your subscription lapse! Renew now to:</p>
        <ul>
            <li>Continue accessing all premium features</li>
            <li>Avoid service interruption</li>
            <li>Maintain your vendor status</li>
            <li>Keep your listings active</li>
        </ul>

        {{-- <div style="text-align: center;">
            <a href="{{ config('app.frontend_url') }}/vendor/subscription/renew" class="cta-button">
                Renew Subscription Now
            </a>
        </div> --}}

        <p>If you have any questions or need assistance with the renewal process, please don't hesitate to contact our
            support team.</p>

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
