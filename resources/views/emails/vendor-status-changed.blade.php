<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Mise à jour du statut vendeur - Dabablane</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f7f7f7;
        }

        .header {
            background-color: #111827;
            color: #ffffff;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
            margin-bottom: 0;
        }

        .header h2 {
            margin: 0;
            font-size: 24px;
        }

        .content {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 0 0 5px 5px;
            border: 1px solid #dee2e6;
            margin-top: 0;
        }

        .status-box {
            background-color: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }

        .status-active {
            background-color: #d4edda;
            border-left-color: #28a745;
        }

        .status-pending {
            background-color: #fff3cd;
            border-left-color: #ffc107;
        }

        .status-suspended,
        .status-inactive {
            background-color: #f8d7da;
            border-left-color: #dc3545;
        }

        .status-waiting {
            background-color: #e2e3e5;
            border-left-color: #6c757d;
        }

        .status-box h3 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #004085;
        }

        .status-active h3 {
            color: #155724;
        }

        .status-pending h3 {
            color: #856404;
        }

        .status-suspended h3,
        .status-inactive h3 {
            color: #721c24;
        }

        .status-waiting h3 {
            color: #383d41;
        }

        .comment-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }

        .comment-box strong {
            color: #495057;
            display: block;
            margin-bottom: 10px;
        }

        .comment-box p {
            margin: 0;
            white-space: pre-wrap;
            color: #212529;
        }

        .footer {
            background-color: #f3f4f6;
            color: #6b7280;
            padding: 15px 20px;
            font-size: 12px;
            text-align: center;
            border-radius: 0 0 5px 5px;
            margin-top: 0;
        }

        .footer p {
            margin: 0;
        }

        .info-section {
            margin: 25px 0;
        }

        .info-section h3 {
            color: #111827;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .field {
            margin-bottom: 15px;
        }

        .field-label {
            font-weight: bold;
            color: #495057;
            margin-bottom: 5px;
        }

        .field-value {
            color: #212529;
        }
    </style>
</head>

<body>
    <div class="header">
        <h2>Dabablane</h2>
    </div>

    <div class="content">
        <p style="margin-top: 0;">Bonjour <strong>{{ $vendor->name }}</strong>,</p>

        <p>Nous vous informons que le statut de votre compte vendeur a été mis à jour.</p>

        <div class="status-box status-{{ strtolower($status) }}">
            <h3>
                @php
                    $statusTranslations = [
                        'pending' => 'En attente',
                        'active' => 'Actif',
                        'inactive' => 'Inactif',
                        'suspended' => 'Suspendu',
                        'waiting' => 'En attente'
                    ];
                    echo $statusTranslations[strtolower($status)] ?? ucfirst($status);
                @endphp
            </h3>
            <p style="margin: 0;">
                <strong>Nouveau statut :</strong>
                <span style="text-transform: capitalize;">
                    @php
                        echo $statusTranslations[strtolower($status)] ?? ucfirst($status);
                    @endphp
                </span>
            </p>
        </div>

        <div class="info-section">
            <h3>Informations du Compte :</h3>

            <div class="field">
                <div class="field-label">Nom du Vendeur :</div>
                <div class="field-value">{{ $vendor->name }}</div>
            </div>

            @if(!empty($vendor->company_name))
                <div class="field">
                    <div class="field-label">Nom de l'Entreprise :</div>
                    <div class="field-value">{{ $vendor->company_name }}</div>
                </div>
            @endif

            <div class="field">
                <div class="field-label">Adresse Email :</div>
                <div class="field-value">{{ $vendor->email }}</div>
            </div>

            @if(!empty($vendor->phone))
                <div class="field">
                    <div class="field-label">Numéro de Téléphone :</div>
                    <div class="field-value">{{ $vendor->phone }}</div>
                </div>
            @endif
        </div>

        @if(!empty($comment))
            <div class="comment-box">
                <strong>Message de l'Administrateur :</strong>
                <p>{{ $comment }}</p>
            </div>
        @endif

        @if(strtolower($status) === 'active')
            <div
                style="background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <p style="margin: 0; color: #155724;">
                    <strong>✅ Félicitations !</strong> Votre compte a été approuvé. Vous pouvez maintenant utiliser toutes
                    les fonctionnalités de la plateforme Dabablane.
                </p>
            </div>
        @elseif(in_array(strtolower($status), ['suspended', 'inactive']))
            <div
                style="background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <p style="margin: 0; color: #721c24;">
                    <strong>⚠️ Attention :</strong> Votre compte est actuellement
                    {{ strtolower($status) === 'suspended' ? 'suspendu' : 'inactif' }}. Si vous avez des questions ou
                    souhaitez contester cette décision, veuillez nous contacter.
                </p>
            </div>
        @elseif(strtolower($status) === 'pending' || strtolower($status) === 'waiting')
            <div
                style="background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <p style="margin: 0; color: #856404;">
                    <strong>⏳ En attente :</strong> Votre compte est en cours d'examen par notre équipe. Vous serez notifié
                    dès qu'une décision sera prise.
                </p>
            </div>
        @endif

        <p style="margin-top: 30px;">Si vous avez des questions ou besoin d'assistance, n'hésitez pas à nous contacter
            en répondant à cet e-mail.</p>

        <p>Merci de votre confiance,<br>
            <strong>L'équipe Dabablane</strong>
        </p>
    </div>

    <div class="footer">
        <p>© {{ date('Y') }} Dabablane. Tous droits réservés.</p>
        <p style="margin-top: 5px;">Ceci est une notification automatique. Veuillez ne pas répondre directement à cet
            e-mail.</p>
    </div>
</body>

</html>