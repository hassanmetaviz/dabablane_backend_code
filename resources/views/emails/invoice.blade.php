@component('mail::message')
# Votre facture #{{ $invoice->invoice_number }}

Merci d'avoir acheté le plan {{ env('APP_NAME', 'DabaBlane') }} !

## Détails de l'achat

**Facture #{{ $invoice->invoice_number }}**
**Date d'émission :** {{ $invoice->issued_at->format('d/m/Y') }}
{{-- **Statut :**
@if($purchase->status == 'pending')
<span
    style="display:inline-block;padding:4px 10px;border-radius:20px;font-weight:bold;font-size:12px;text-transform:uppercase;background-color:#FFF8E1;color:#FF8F00;">En
    attente</span>
@elseif($purchase->status == 'completed')
<span
    style="display:inline-block;padding:4px 10px;border-radius:20px;font-weight:bold;font-size:12px;text-transform:uppercase;background-color:#E8F5E9;color:#2E7D32;">Confirmé</span>
@elseif($purchase->status == 'manual')
<span
    style="display:inline-block;padding:4px 10px;border-radius:20px;font-weight:bold;font-size:12px;text-transform:uppercase;background-color:#E0F2F1;color:#00897B;">En
    attente d'activation</span>
@else
<span
    style="display:inline-block;padding:4px 10px;border-radius:20px;font-weight:bold;font-size:12px;text-transform:uppercase;background-color:#E0F2F1;color:#00897B;">{{
    $purchase->status }}</span>
@endif --}}
**Statut :**
@if($purchase->status == 'pending')
    <span
        style="display:inline-block;padding:4px 10px;border-radius:20px;font-weight:bold;font-size:12px;text-transform:uppercase;background-color:#FFF8E1;color:#FF8F00;">En
        attente de paiement</span>
@elseif($purchase->status == 'completed')
    <span
        style="display:inline-block;padding:4px 10px;border-radius:20px;font-weight:bold;font-size:12px;text-transform:uppercase;background-color:#E8F5E9;color:#2E7D32;">Confirmé
        et activé</span>
@elseif($purchase->status == 'manual')
    <span
        style="display:inline-block;padding:4px 10px;border-radius:20px;font-weight:bold;font-size:12px;text-transform:uppercase;background-color:#E0F2F1;color:#00897B;">En
        attente d'activation</span>
@else
    <span
        style="display:inline-block;padding:4px 10px;border-radius:20px;font-weight:bold;font-size:12px;text-transform:uppercase;background-color:#E0F2F1;color:#00897B;">{{ $purchase->status }}</span>
@endif

### Plan
- **Nom :** {{ $purchase->plan->title }}
- **Prix HT :** {{ $purchase->plan_price_ht }} DH
- **Durée :** {{ $purchase->plan->duration_days }} jours

@if($purchase->addOns->isNotEmpty())
    ### Options supplémentaires
    @foreach($purchase->addOns as $addOn)
        - **{{ $addOn->title }}** (x{{ $addOn->pivot->quantity }}): {{ $addOn->pivot->total_price_ht }} DH
    @endforeach
@endif

@if($purchase->promoCode)
    ### Code promotionnel
    - **Code :** {{ $purchase->promoCode->code }}
    - **Réduction :** {{ $purchase->discount_amount }} DH
@endif

### Total
- **Sous-total HT :** {{ $purchase->subtotal_ht }} DH
- **TVA (20%) :** {{ $purchase->vat_amount }} DH
- **Total TTC :** {{ $purchase->total_ttc }} DH

### Informations client
- **Nom :** {{ $purchase->user->name }}
- **Email :** {{ $purchase->user->email }}
- **Téléphone :** {{ $purchase->user->phone ?? 'Non spécifié' }}

### Méthode de paiement
- {{ $purchase->payment_method }}

## Informations de facturation
- **Email de facturation :** {{ $config->billing_email }}
- **Téléphone de contact :** {{ $config->contact_phone }}
- **Mentions légales :** {{ $config->invoice_legal_mentions ?? 'Dabablane, Maroc. Tous droits réservés.' }}

Vous trouverez la facture en pièce jointe.

Merci d'avoir choisi {{ env('APP_NAME', 'DabaBlane') }} !
Pour toute question, contactez notre service client à {{ $config->contact_email }} ou au {{ $config->contact_phone }}.

@component('mail::button', ['url' => env('CMI_SHOP_URL', 'https://dabablane.com')])
Visiter Dabablane
@endcomponent

Cordialement,
{{ env('APP_NAME', 'DabaBlane') }}
@endcomponent