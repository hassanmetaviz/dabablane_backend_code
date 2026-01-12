<!DOCTYPE html>
<html>

<head>
    <title>New Contact Form Submission</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .content {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }

        .field {
            margin-bottom: 15px;
        }

        .field-label {
            font-weight: bold;
            color: #495057;
        }

        .field-value {
            margin-top: 5px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h2>New Contact Form Submission</h2>
    </div>

    <div class="content">
        <div class="field">
            <div class="field-label">Name:</div>
            <div class="field-value">{{ $contact->fullName }}</div>
        </div>

        <div class="field">
            <div class="field-label">Email:</div>
            <div class="field-value">{{ $contact->email }}</div>
        </div>

        <div class="field">
            <div class="field-label">Phone:</div>
            <div class="field-value">{{ $contact->phone }}</div>
        </div>

        <div class="field">
            <div class="field-label">Subject:</div>
            <div class="field-value">{{ $contact->subject }}</div>
        </div>

        <div class="field">
            <div class="field-label">Message:</div>
            <div class="field-value">{{ $contact->message }}</div>
        </div>

        <div class="field">
            <div class="field-label">Submitted at:</div>
            <div class="field-value">{{ $contact->created_at->format('Y-m-d H:i:s') }}</div>
        </div>
    </div>
</body>

</html>