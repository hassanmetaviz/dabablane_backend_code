<!DOCTYPE html>
<html>

<head>
    <title>Nouvelle Création de Blane - Action Requise</title>
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

        .action-required {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }

        .action-required h3 {
            color: #856404;
            margin-top: 0;
        }

        .admin-actions {
            background-color: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }

        .admin-actions h3 {
            color: #004085;
            margin-top: 0;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
        }

        .btn:hover {
            background-color: #0056b3;
        }

        .btn-success {
            background-color: #28a745;
        }

        .btn-success:hover {
            background-color: #1e7e34;
        }

        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background-color: #e0a800;
        }

        .price-info {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-waiting {
            background-color: #ffc107;
            color: #212529;
        }

        .status-active {
            background-color: #28a745;
            color: white;
        }

        .status-inactive {
            background-color: #6c757d;
            color: white;
        }
    </style>
</head>

<body>
    <div class="header">
        <h2>Nouvelle Création de Blane - Action Requise</h2>
        <p>Un nouveau blane a été créé et nécessite l'approbation de l'administrateur</p>
    </div>

    <div class="content">
        <div class="action-required">
            <h3>⚠️ Action Requise</h3>
            <p>Un nouveau blane a été créé sur la plateforme Dabablane et nécessite l'approbation de l'administrateur
                avant d'être visible pour les utilisateurs.</p>
        </div>

        <h3>Informations du Blane :</h3>

        <div class="field">
            <div class="field-label">Nom du Blane :</div>
            <div class="field-value">{{ $blane->name }}</div>
        </div>

        @if($blane->slug)
            <div class="field">
                <div class="field-label">Lien du Blane :</div>
                <div class="field-value">
                    @if(app()->environment('local', 'development'))
                        <a href="https://copy-dabablane.vercel.app/admin/blanes"
                            style="color: #007bff; text-decoration: none; word-break: break-all;">
                            https://copy-dabablane.vercel.app/admin/blanes
                        </a>
                    @else
                        <a href="{{ config('app.frontend_url') }}/admin/blanes"
                            style="color: #007bff; text-decoration: none; word-break: break-all;">
                            {{ config('app.frontend_url') }}/admin/blanes
                        </a>
                    @endif
                </div>
            </div>
        @endif

        <div class="field">
            <div class="field-label">Description :</div>
            <div class="field-value">{{ $blane->description ?? 'Non fournie' }}</div>
        </div>

        <div class="field">
            <div class="field-label">Commerce :</div>
            <div class="field-value">{{ $blane->commerce_name ?? 'Non fourni' }}</div>
        </div>

        <div class="field">
            <div class="field-label">Téléphone du Commerce :</div>
            <div class="field-value">{{ $blane->commerce_phone ?? 'Non fourni' }}</div>
        </div>

        <div class="field">
            <div class="field-label">Ville :</div>
            <div class="field-value">{{ $blane->city ?? 'Non fournie' }}</div>
        </div>

        <div class="field">
            <div class="field-label">District :</div>
            <div class="field-value">{{ $blane->district ?? 'Non fourni' }}</div>
        </div>

        <div class="field">
            <div class="field-label">Sous-districts :</div>
            <div class="field-value">{{ $blane->subdistricts ?? 'Non fournis' }}</div>
        </div>

        <div class="field">
            <div class="field-label">Type :</div>
            <div class="field-value">{{ ucfirst($blane->type ?? 'Non spécifié') }}</div>
        </div>

        <div class="price-info">
            <div class="field">
                <div class="field-label">Prix Actuel :</div>
                <div class="field-value">
                    {{ $blane->price_current ? number_format($blane->price_current, 2) . ' MAD' : 'Non spécifié' }}
                </div>
            </div>

            @if($blane->price_old)
                <div class="field">
                    <div class="field-label">Ancien Prix :</div>
                    <div class="field-value">{{ number_format($blane->price_old, 2) }} MAD</div>
                </div>
            @endif
        </div>

        <div class="field">
            <div class="field-label">Date de Création :</div>
            <div class="field-value">
                @php
                    $date = $blane->created_at ?? now();
                    echo $date->format('d/m/Y à H:i');
                @endphp
            </div>
        </div>

        <div class="field">
            <div class="field-label">Statut Actuel :</div>
            <div class="field-value">
                <span class="status-badge status-{{ $blane->status ?? 'waiting' }}">
                    @php
                        $statusTranslations = [
                            'waiting' => 'En attente',
                            'active' => 'Actif',
                            'inactive' => 'Inactif',
                            'expired' => 'Expiré'
                        ];
                        $status = $blane->status ?? 'waiting';
                        echo $statusTranslations[$status] ?? ucfirst($status);
                    @endphp
                </span>
            </div>
        </div>

        @if($blane->advantages)
            <div class="field">
                <div class="field-label">Avantages :</div>
                <div class="field-value">{{ $blane->advantages }}</div>
            </div>
        @endif

        @if($blane->conditions)
            <div class="field">
                <div class="field-label">Conditions :</div>
                <div class="field-value">{{ $blane->conditions }}</div>
            </div>
        @endif

        <div class="admin-actions">
            <h3>Actions Administrateur Requises :</h3>
            @if($blane->slug)
                <div style="text-align: center; margin: 20px 0;">
                    @if(app()->environment('local', 'development'))
                        <a href="https://copy-dabablane.vercel.app/admin/blanes" class="btn" target="_blank">
                            Gérer les Blanes (Admin)
                        </a>
                    @else
                        <a href="{{ config('app.url') }}/admin/blanes" class="btn" target="_blank">
                            Gérer les Blanes (Admin)
                        </a>
                    @endif
                </div>
            @endif
            <p>Veuillez examiner les informations du blane et prendre les mesures appropriées :</p>
            <ul>
                <li><strong>Approuver :</strong> Si le blane répond à tous les critères et est prêt à être publié</li>
                <li><strong>Demander des Modifications :</strong> Si des informations supplémentaires ou des corrections
                    sont nécessaires</li>
                <li><strong>Rejeter :</strong> Si le blane ne répond pas aux exigences de la plateforme</li>
            </ul>

            <p><strong>Prochaines Étapes :</strong></p>
            <ol>
                <li>Connectez-vous au panneau d'administration</li>
                <li>Accédez à la section de gestion des blanes</li>
                <li>Examinez le profil complet du blane</li>
                <li>Mettez à jour le statut du blane en conséquence</li>
                <li>Envoyez une notification appropriée au créateur</li>
            </ol>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <p><strong>Veuillez agir dès que possible pour assurer une expérience utilisateur fluide.</strong>
            </p>
            <p style="color: #6c757d; font-size: 14px;">
                Ceci est une notification automatique de la plateforme Dabablane.
                Veuillez ne pas répondre à cet email.
            </p>
        </div>
    </div>
</body>

</html>


<!-- <!DOCTYPE html>
<html>

<head>
    <title>Nouvelle Création de Blane - Action Requise</title>
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

        .action-required {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }

        .action-required h3 {
            color: #856404;
            margin-top: 0;
        }

        .admin-actions {
            background-color: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }

        .admin-actions h3 {
            color: #004085;
            margin-top: 0;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
        }

        .btn:hover {
            background-color: #0056b3;
        }

        .btn-success {
            background-color: #28a745;
        }

        .btn-success:hover {
            background-color: #1e7e34;
        }

        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background-color: #e0a800;
        }

        .price-info {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-waiting {
            background-color: #ffc107;
            color: #212529;
        }

        .status-active {
            background-color: #28a745;
            color: white;
        }

        .status-inactive {
            background-color: #6c757d;
            color: white;
        }
    </style>
</head>

<body>
    <div class="header">
        <h2>Nouvelle Création de Blane - Action Requise</h2>
        <p>Un nouveau blane a été créé et nécessite l'approbation de l'administrateur</p>
    </div>

    <div class="content">
        <div class="action-required">
            <h3>⚠️ Action Requise</h3>
            <p>Un nouveau blane a été créé sur la plateforme Dabablane et nécessite l'approbation de l'administrateur
                avant d'être visible pour les utilisateurs.</p>
        </div>

        <h3>Informations du Blane :</h3>

        <div class="field">
            <div class="field-label">Nom du Blane :</div>
            <div class="field-value">{{ $blane->name }}</div>
        </div>

        @if($blane->slug)
            <div class="field">
                <div class="field-label">Lien du Blane :</div>
                <div class="field-value">
                    <a href="{{ config('app.frontend_url') }}/blane/{{ $blane->slug }}"
                        style="color: #007bff; text-decoration: none; word-break: break-all;">
                        {{ config('app.frontend_url') }}/blane/{{ $blane->slug }}
                    </a>
                </div>
            </div>
        @endif

        <div class="field">
            <div class="field-label">Description :</div>
            <div class="field-value">{{ $blane->description ?? 'Non fournie' }}</div>
        </div>

        <div class="field">
            <div class="field-label">Commerce :</div>
            <div class="field-value">{{ $blane->commerce_name ?? 'Non fourni' }}</div>
        </div>

        <div class="field">
            <div class="field-label">Téléphone du Commerce :</div>
            <div class="field-value">{{ $blane->commerce_phone ?? 'Non fourni' }}</div>
        </div>

        <div class="field">
            <div class="field-label">Ville :</div>
            <div class="field-value">{{ $blane->city ?? 'Non fournie' }}</div>
        </div>

        <div class="field">
            <div class="field-label">District :</div>
            <div class="field-value">{{ $blane->district ?? 'Non fourni' }}</div>
        </div>

        <div class="field">
            <div class="field-label">Sous-districts :</div>
            <div class="field-value">{{ $blane->subdistricts ?? 'Non fournis' }}</div>
        </div>

        <div class="field">
            <div class="field-label">Type :</div>
            <div class="field-value">{{ ucfirst($blane->type ?? 'Non spécifié') }}</div>
        </div>

        <div class="price-info">
            <div class="field">
                <div class="field-label">Prix Actuel :</div>
                <div class="field-value">
                    {{ $blane->price_current ? number_format($blane->price_current, 2) . ' MAD' : 'Non spécifié' }}
                </div>
            </div>

            @if($blane->price_old)
                <div class="field">
                    <div class="field-label">Ancien Prix :</div>
                    <div class="field-value">{{ number_format($blane->price_old, 2) }} MAD</div>
                </div>
            @endif
        </div>

        <div class="field">
            <div class="field-label">Date de Création :</div>
            <div class="field-value">
                @php
                    $date = $blane->created_at ?? now();
                    echo $date->format('d/m/Y à H:i');
                @endphp
            </div>
        </div>

        <div class="field">
            <div class="field-label">Statut Actuel :</div>
            <div class="field-value">
                <span class="status-badge status-{{ $blane->status ?? 'waiting' }}">
                    @php
                        $statusTranslations = [
                            'waiting' => 'En attente',
                            'active' => 'Actif',
                            'inactive' => 'Inactif',
                            'expired' => 'Expiré'
                        ];
                        $status = $blane->status ?? 'waiting';
                        echo $statusTranslations[$status] ?? ucfirst($status);
                    @endphp
                </span>
            </div>
        </div>

        @if($blane->advantages)
            <div class="field">
                <div class="field-label">Avantages :</div>
                <div class="field-value">{{ $blane->advantages }}</div>
            </div>
        @endif

        @if($blane->conditions)
            <div class="field">
                <div class="field-label">Conditions :</div>
                <div class="field-value">{{ $blane->conditions }}</div>
            </div>
        @endif

        <div class="admin-actions">
            <h3>Actions Administrateur Requises :</h3>
            @if($blane->slug)
                <div style="text-align: center; margin: 20px 0;">
                    <a href="{{ config('app.frontend_url') }}/blane/{{ $blane->slug }}" class="btn" target="_blank">
                        Voir le Blane
                    </a>
                </div>
            @endif
            <p>Veuillez examiner les informations du blane et prendre les mesures appropriées :</p>
            <ul>
                <li><strong>Approuver :</strong> Si le blane répond à tous les critères et est prêt à être publié</li>
                <li><strong>Demander des Modifications :</strong> Si des informations supplémentaires ou des corrections
                    sont nécessaires</li>
                <li><strong>Rejeter :</strong> Si le blane ne répond pas aux exigences de la plateforme</li>
            </ul>

            <p><strong>Prochaines Étapes :</strong></p>
            <ol>
                <li>Connectez-vous au panneau d'administration</li>
                <li>Accédez à la section de gestion des blanes</li>
                <li>Examinez le profil complet du blane</li>
                <li>Mettez à jour le statut du blane en conséquence</li>
                <li>Envoyez une notification appropriée au créateur</li>
            </ol>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <p><strong>Veuillez agir dès que possible pour assurer une expérience utilisateur fluide.</strong>
            </p>
            <p style="color: #6c757d; font-size: 14px;">
                Ceci est une notification automatique de la plateforme Dabablane.
                Veuillez ne pas répondre à cet email.
            </p>
        </div>
    </div>
</body>

</html> -->