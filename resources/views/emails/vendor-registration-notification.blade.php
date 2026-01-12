<!DOCTYPE html>
<html>

<head>
    <title>Nouvelle Inscription Vendeur - Action Requise</title>
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
    </style>
</head>

<body>
    <div class="header">
        <h2>Nouvelle Inscription Vendeur - Action Requise</h2>
        <p>Un nouveau vendeur s'est inscrit et attend l'approbation</p>
    </div>

    <div class="content">
        <div class="action-required">
            <h3>⚠️ Action Requise</h3>
            <p>Un nouveau vendeur s'est inscrit sur la plateforme Dabablane et nécessite l'approbation de
                l'administrateur avant de pouvoir commencer à utiliser le système.</p>
        </div>

        <h3>Informations du Vendeur :</h3>

        <div class="field">
            <div class="field-label">Nom du Vendeur :</div>
            <div class="field-value">{{ $vendor->name }}</div>
        </div>

        <div class="field">
            <div class="field-label">Nom de l'Entreprise :</div>
            <div class="field-value">{{ $vendor->company_name ?? 'Non fourni' }}</div>
        </div>

        <div class="field">
            <div class="field-label">Adresse Email :</div>
            <div class="field-value">{{ $vendor->email }}</div>
        </div>

        <div class="field">
            <div class="field-label">Numéro de Téléphone :</div>
            <div class="field-value">{{ $vendor->phone ?? 'Non fourni' }}</div>
        </div>

        <div class="field">
            <div class="field-label">Ville :</div>
            <div class="field-value">{{ $vendor->city ?? 'Non fourni' }}</div>
        </div>

        @if($vendor->address)
        <div class="field">
            <div class="field-label">Adresse :</div>
            <div class="field-value">{{ $vendor->address }}</div>
        </div>
        @endif

        @if($vendor->district)
        <div class="field">
            <div class="field-label">District :</div>
            <div class="field-value">{{ $vendor->district }}</div>
        </div>
        @endif

        @if($vendor->subdistrict)
        <div class="field">
            <div class="field-label">Sous-district :</div>
            <div class="field-value">{{ $vendor->subdistrict }}</div>
        </div>
        @endif

        <div class="field">
            <div class="field-label">Date d'Inscription :</div>
            <div class="field-value">
                @php
                    $date = $vendor->created_at ?? now();
                    echo $date->format('d/m/Y à H:i');
                @endphp
            </div>
        </div>

        <div class="field">
            <div class="field-label">Statut Actuel :</div>
            <div class="field-value">
                <span style="color: #ffc107; font-weight: bold;">
                    @php
                        $statusTranslations = [
                            'pending' => 'En attente',
                            'active' => 'Actif',
                            'suspended' => 'Suspendu',
                            'blocked' => 'Bloqué'
                        ];
                        $status = $vendor->status ?? 'pending';
                        echo $statusTranslations[$status] ?? ucfirst($status);
                    @endphp
                </span>
            </div>
        </div>

        <div class="field">
                <div class="field-label">Lien vers le fournisseur :</div>
                <div class="field-value">
                    @if(app()->environment('local', 'development'))
                    <a href="https://copy-dabablane.vercel.app/admin/vendors"
                            style="color: #007bff; text-decoration: none; word-break: break-all;">
                            https://copy-dabablane.vercel.app/admin/vendors
                        </a>
                @else
                    <a href="{{ config('app.frontend_url') }}/admin/vendors"
                            style="color: #007bff; text-decoration: none; word-break: break-all;">
                            {{ config('app.frontend_url') }}/admin/vendors
                        </a>
                @endif
                </div>
            </div>

        <div class="admin-actions">
            <h3>Actions Administrateur Requises :</h3>
            
            <!-- Admin Vendor Management Link -->
            <div style="text-align: center; margin: 20px 0;">
                @if(app()->environment('local', 'development'))
                    <a href="https://copy-dabablane.vercel.app/admin/vendors" class="btn" target="_blank">
                        📋 Gérer les Vendeurs (Admin)
                    </a>
                @else
                    <a href="{{ config('app.url') }}/admin/vendors" class="btn" target="_blank">
                        📋 Gérer les Vendeurs (Admin)
                    </a>
                @endif
            </div>

            <p>Veuillez examiner les informations du vendeur et prendre les mesures appropriées :</p>
            <ul>
                <li><strong>Approuver :</strong> Si le vendeur répond à tous les critères et que la documentation est
                    complète</li>
                <li><strong>Demander Plus d'Informations :</strong> Si des documents supplémentaires ou des détails sont
                    nécessaires</li>
                <li><strong>Rejeter :</strong> Si le vendeur ne répond pas aux exigences de la plateforme</li>
            </ul>

            <p><strong>Prochaines Étapes :</strong></p>
            <ol>
                <li>Connectez-vous au panneau d'administration</li>
                <li>Accédez à la section de gestion des vendeurs</li>
                <li>Examinez le profil complet du vendeur</li>
                <li>Mettez à jour le statut du vendeur en conséquence</li>
            </ol>

            <!-- Quick Links Section -->
            <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 15px;">
                <h4 style="margin-top: 0; color: #495057;">Liens Rapides :</h4>
                <div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center;">
                    @if(app()->environment('local', 'development'))
                        <a href="https://copy-dabablane.vercel.app/admin/vendors" 
                           style="color: #007bff; text-decoration: none; font-size: 14px;"
                           target="_blank">
                            🔗 Vendeurs en attente
                        </a>
                        <a href="https://copy-dabablane.vercel.app/admin" 
                           style="color: #007bff; text-decoration: none; font-size: 14px;"
                           target="_blank">
                            🔗 Tableau de bord Admin
                        </a>
                    @else
                        <a href="{{ config('app.url') }}/admin/vendors" 
                           style="color: #007bff; text-decoration: none; font-size: 14px;"
                           target="_blank">
                            🔗 Vendeurs en attente
                        </a>
                        <a href="{{ config('app.url') }}/admin" 
                           style="color: #007bff; text-decoration: none; font-size: 14px;"
                           target="_blank">
                            🔗 Tableau de bord Admin
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <p><strong>Veuillez agir dès que possible pour assurer une expérience d'intégration fluide pour le
                    vendeur.</strong>
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
    <title>Nouvelle Inscription Vendeur - Action Requise</title>
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
    </style>
</head>

<body>
    <div class="header">
        <h2>Nouvelle Inscription Vendeur - Action Requise</h2>
        <p>Un nouveau vendeur s'est inscrit et attend l'approbation</p>
    </div>

    <div class="content">
        <div class="action-required">
            <h3>⚠️ Action Requise</h3>
            <p>Un nouveau vendeur s'est inscrit sur la plateforme Dabablane et nécessite l'approbation de
                l'administrateur avant de pouvoir commencer à utiliser le système.</p>
        </div>

        <h3>Informations du Vendeur :</h3>

        <div class="field">
            <div class="field-label">Nom du Vendeur :</div>
            <div class="field-value">{{ $vendor->name }}</div>
        </div>

        <div class="field">
            <div class="field-label">Nom de l'Entreprise :</div>
            <div class="field-value">{{ $vendor->company_name ?? 'Non fourni' }}</div>
        </div>

        <div class="field">
            <div class="field-label">Adresse Email :</div>
            <div class="field-value">{{ $vendor->email }}</div>
        </div>

        <div class="field">
            <div class="field-label">Numéro de Téléphone :</div>
            <div class="field-value">{{ $vendor->phone ?? 'Non fourni' }}</div>
        </div>

        <div class="field">
            <div class="field-label">Ville :</div>
            <div class="field-value">{{ $vendor->city ?? 'Non fourni' }}</div>
        </div>

        <div class="field">
            <div class="field-label">Date d'Inscription :</div>
            <div class="field-value">
                @php
                    $date = $vendor->created_at ?? now();
                    echo $date->format('d/m/Y à H:i');
                @endphp
            </div>
        </div>

        <div class="field">
            <div class="field-label">Statut Actuel :</div>
            <div class="field-value">
                <span style="color: #ffc107; font-weight: bold;">
                    @php
                        $statusTranslations = [
                            'pending' => 'En attente',
                            'active' => 'Actif',
                            'suspended' => 'Suspendu',
                            'blocked' => 'Bloqué'
                        ];
                        $status = $vendor->status ?? 'pending';
                        echo $statusTranslations[$status] ?? ucfirst($status);
                    @endphp
                </span>
            </div>
        </div>

        <div class="admin-actions">
            <h3>Actions Administrateur Requises :</h3>
            <p>Veuillez examiner les informations du vendeur et prendre les mesures appropriées :</p>
            <ul>
                <li><strong>Approuver :</strong> Si le vendeur répond à tous les critères et que la documentation est
                    complète</li>
                <li><strong>Demander Plus d'Informations :</strong> Si des documents supplémentaires ou des détails sont
                    nécessaires</li>
                <li><strong>Rejeter :</strong> Si le vendeur ne répond pas aux exigences de la plateforme</li>
            </ul>

            <p><strong>Prochaines Étapes :</strong></p>
            <ol>
                <li>Connectez-vous au panneau d'administration</li>
                <li>Accédez à la section de gestion des vendeurs</li>
                <li>Examinez le profil complet du vendeur</li>
                <li>Mettez à jour le statut du vendeur en conséquence</li>
            </ol>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <p><strong>Veuillez agir dès que possible pour assurer une expérience d'intégration fluide pour le
                    vendeur.</strong>
            </p>
            <p style="color: #6c757d; font-size: 14px;">
                Ceci est une notification automatique de la plateforme Dabablane.
                Veuillez ne pas répondre à cet email.
            </p>
        </div>
    </div>
</body>

</html> -->