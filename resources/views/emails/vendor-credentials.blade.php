<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Vos identifiants de connexion - Dabablane</title>
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

        .credentials-box {
            background-color: #f8f9fa;
            border: 2px solid #007bff;
            border-radius: 5px;
            padding: 20px;
            margin: 25px 0;
        }

        .credentials-box h3 {
            margin-top: 0;
            color: #007bff;
            font-size: 18px;
        }

        .credential-item {
            margin: 15px 0;
            padding: 12px;
            background-color: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }

        .credential-label {
            font-weight: bold;
            color: #495057;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .credential-value {
            color: #212529;
            font-size: 16px;
            font-family: 'Courier New', monospace;
            background-color: #f8f9fa;
            padding: 8px;
            border-radius: 3px;
            word-break: break-all;
        }

        .warning-box {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }

        .warning-box strong {
            color: #856404;
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
    </style>
</head>

<body>
    <div class="header">
        <h2>Dabablane</h2>
    </div>

    <div class="content">
        <p style="margin-top: 0;">Bonjour <strong>{{ $vendor->name }}</strong>,</p>

        <p>Votre compte vendeur a été créé avec succès sur la plateforme Dabablane. Voici vos identifiants de connexion :</p>

        <div class="credentials-box">
            <h3>🔐 Vos identifiants de connexion</h3>

            <div class="credential-item">
                <div class="credential-label">Adresse Email :</div>
                <div class="credential-value">{{ $vendor->email }}</div>
            </div>

            <div class="credential-item">
                <div class="credential-label">Mot de passe temporaire :</div>
                <div class="credential-value">{{ $password }}</div>
            </div>
        </div>

        <div class="warning-box">
            <strong>⚠️ Important :</strong>
            <p style="margin: 5px 0 0 0;">
                Pour des raisons de sécurité, nous vous recommandons fortement de changer ce mot de passe après votre première connexion.
            </p>
        </div>

        <div class="info-section">
            <h3>Informations de votre compte :</h3>

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

            @if(!empty($vendor->city))
                <div class="field">
                    <div class="field-label">Ville :</div>
                    <div class="field-value">{{ $vendor->city }}</div>
                </div>
            @endif

            <div class="field">
                <div class="field-label">Statut du Compte :</div>
                <div class="field-value">
                    @php
                        $statusTranslations = [
                            'pending' => 'En attente',
                            'active' => 'Actif',
                            'inactive' => 'Inactif',
                            'suspended' => 'Suspendu',
                            'waiting' => 'En attente'
                        ];
                        $status = $vendor->status ?? 'pending';
                        echo $statusTranslations[$status] ?? ucfirst($status);
                    @endphp
                </div>
            </div>
        </div>

        <div style="background-color: #e7f3ff; border: 1px solid #b3d9ff; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <h3 style="margin-top: 0; color: #004085;">📱 Prochaines étapes :</h3>
            <ol style="margin: 10px 0; padding-left: 20px; color: #004085;">
                <li>Connectez-vous à l'application mobile Dabablane avec les identifiants ci-dessus</li>
                <li>Changez votre mot de passe pour plus de sécurité</li>
                <li>Complétez votre profil vendeur avec toutes les informations nécessaires</li>
                <li>Commencez à créer et gérer vos offres (Blanes)</li>
            </ol>
        </div>

        <p style="margin-top: 30px;">Si vous avez des questions ou besoin d'assistance, n'hésitez pas à nous contacter en répondant à cet e-mail.</p>

        <p>Bienvenue sur Dabablane !<br>
            <strong>L'équipe Dabablane</strong>
        </p>
    </div>

    <div class="footer">
        <p>© {{ date('Y') }} Dabablane. Tous droits réservés.</p>
        <p style="margin-top: 5px;">Ceci est une notification automatique. Veuillez ne pas répondre directement à cet e-mail.</p>
        <p style="margin-top: 5px;"><strong>Note de sécurité :</strong> Ne partagez jamais vos identifiants de connexion avec personne.</p>
    </div>
</body>

</html>












