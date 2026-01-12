<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Facture #{{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            font-size: 12px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header img {
            max-width: 150px;
        }

        .header h1 {
            color: #00897B;
            font-size: 18px;
        }

        .section {
            margin-bottom: 20px;
        }

        .section-title {
            font-weight: bold;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 10px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .table th,
        .table td {
            border: 1px solid #B2DFDB;
            padding: 8px;
            text-align: left;
        }

        .table th {
            background-color: #E0F2F1;
            color: #00897B;
        }

        .total {
            background-color: #E0F2F1;
            padding: 10px;
            border: 1px solid #B2DFDB;
            border-radius: 5px;
        }

        .total span {
            font-weight: bold;
            font-size: 14px;
        }

        .footer {
            text-align: center;
            font-size: 10px;
            color: #00897B;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            {{-- @if($config->invoice_logo_path) --}}
            {{-- <img src="{{ public_path(Storage::path($config->invoice_logo_path)) }}" alt="Logo"> --}}
            @if($config->invoice_logo_path)
                <img src="{{ storage_path('app/' . str_replace('storage/', 'public/', $config->invoice_logo_path)) }}"
                    alt="Logo">
            @endif
            {{-- @endif --}}
            <h1>Facture #{{ $invoice->invoice_number }}</h1>
            <p>Date d'émission : {{ $invoice->issued_at->format('d/m/Y') }}</p>
        </div>

        <div class="section">
            <div class="section-title">Détails de l'achat</div>
            <table class="table">
                <tr>
                    <th>Plan</th>
                    <td>{{ $purchase->plan->title }}</td>
                </tr>
                <tr>
                    <th>Prix HT</th>
                    <td>{{ $purchase->plan_price_ht }} DH</td>
                </tr>
                <tr>
                    <th>Durée</th>
                    <td>{{ $purchase->plan->duration_days }} jours</td>
                </tr>
                @if($purchase->addOns->isNotEmpty())
                    <tr>
                        <th>Options supplémentaires</th>
                        <td>
                            @foreach($purchase->addOns as $addOn)
                                {{ $addOn->title }} (x{{ $addOn->pivot->quantity }}): {{ $addOn->pivot->total_price_ht }} DH<br>
                            @endforeach
                        </td>
                    </tr>
                @endif
                @if($purchase->promoCode)
                    <tr>
                        <th>Code promotionnel</th>
                        <td>{{ $purchase->promoCode->code }} (Réduction: {{ $purchase->discount_amount }} DH)</td>
                    </tr>
                @endif
                {{-- <tr>
                    <th>Statut</th>
                    <td>{{ $purchase->status == 'manual' ? 'En attente d\'activation' : ucfirst($purchase->status) }}
                    </td>
                </tr> --}}
                <tr>
                    <th>Statut</th>
                    <td>
                        @if($purchase->status == 'pending')
                            En attente de paiement
                        @elseif($purchase->status == 'completed')
                            Confirmé et activé
                        @elseif($purchase->status == 'manual')
                            En attente d'activation manuelle
                        @else
                            {{ ucfirst($purchase->status) }}
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Informations client</div>
            <table class="table">
                <tr>
                    <th>Nom</th>
                    <td>{{ $purchase->user->name }}</td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td>{{ $purchase->user->email }}</td>
                </tr>
                <tr>
                    <th>Téléphone</th>
                    <td>{{ $purchase->user->phone ?? 'Non spécifié' }}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Total</div>
            <div class="total">
                <span>Sous-total HT: {{ $purchase->subtotal_ht }} DH</span><br>
                <span>TVA (20%): {{ $purchase->vat_amount }} DH</span><br>
                <span>Total TTC: {{ $purchase->total_ttc }} DH</span>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Informations de facturation</div>
            <p>Email de facturation : {{ $config->billing_email }}</p>
            <p>Téléphone de contact : {{ $config->contact_phone }}</p>
            <p>Mentions légales : {{ $config->invoice_legal_mentions ?? 'Dabablane, Maroc. Tous droits réservés.' }}</p>
        </div>

        <div class="footer">
            <p>Merci d'avoir choisi {{ env('APP_NAME', 'DabaBlane') }} !</p>
            <p>Contactez-nous à {{ $config->contact_email }} ou au {{ $config->contact_phone }}</p>
        </div>
    </div>
</body>

</html>