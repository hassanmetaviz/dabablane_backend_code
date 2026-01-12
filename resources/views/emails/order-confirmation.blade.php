<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Confirmation de Commande</title>
</head>
<body style="margin:0;padding:0;font-family:Arial,sans-serif;color:#333333;background-color:#f8fafc;">
    <!--[if (gte mso 9)|(IE)]>
    <table width="600" align="center" cellpadding="0" cellspacing="0" border="0">
    <tr>
    <td>
    <![endif]-->
    
    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="max-width:600px;margin:0 auto;">
        <!-- Header -->
        <tr>
            <td style="background:#00897B;color:#ffffff;padding:30px 20px;text-align:center;">
                <h1 style="font-size:24px;margin:0;padding:0;font-weight:bold;">Merci pour votre commande !</h1>
                <p style="font-size:14px;margin:10px 0 0;padding:0;">Nous avons bien reçu votre demande et la traiterons rapidement</p>
            </td>
        </tr>
        
        <!-- Order Number -->
        <tr>
            <td style="background-color:#E0F2F1;padding:15px;text-align:center;font-weight:bold;color:#00897B;font-size:14px;">
                <span style="background-color:#ffffff;padding:5px 10px;border-radius:20px;display:inline-block;">Commande #{{ $order->NUM_ORD }}</span>
            </td>
        </tr>
        
        <!-- Content -->
        <tr>
            <td style="background-color:#ffffff;padding:20px;">
                <!-- Product Detail -->
                <table width="100%" border="0" cellspacing="0" cellpadding="0" style="margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid #E0F2F1;">
                    <tr>
                        <td width="60" style="vertical-align:middle;">
                            <div style="width:60px;height:60px;background-color:#E0F2F1;color:#00897B;text-align:center;line-height:60px;font-size:24px;border-radius:8px;">📦</div>
                        </td>
                        <td style="vertical-align:middle;padding-left:15px;">
                            <div style="font-weight:bold;margin-bottom:5px;color:#00897B;">{{ $order->blane->name }}</div>
                            <div style="color:#64748b;font-size:12px;">{{ $order->quantity }} × {{ $order->blane->price_current }} DH</div>
                        </td>
                    </tr>
                </table>
                
                <!-- Order Details -->
                <div style="color:#64748b;font-size:14px;font-weight:bold;margin:20px 0 10px;text-transform:uppercase;">Détails de la commande</div>
                <table width="100%" border="0" cellspacing="0" cellpadding="0">
                    <tr>
                        <td width="50%" style="margin-bottom:10px;vertical-align:top;">
                            <div style="color:#64748b;font-size:12px;font-weight:bold;margin-bottom:4px;">Date</div>
                            <div style="color:#333333;font-size:14px;">{{ now()->format('d/m/Y') }}</div>
                        </td>
                        <td width="50%" style="margin-bottom:10px;vertical-align:top;">
                            <div style="color:#64748b;font-size:12px;font-weight:bold;margin-bottom:4px;">Statut</div>
                            <!-- Replace the entire span with this conditional block -->
                            @if($order->status == 'pending')
                                <span style="display:inline-block;padding:4px 10px;border-radius:20px;font-weight:bold;font-size:12px;text-transform:uppercase;background-color:#FFF8E1;color:#FF8F00;">
                                    En attente
                                </span>
                            @elseif($order->status == 'confirmed')
                                <span style="display:inline-block;padding:4px 10px;border-radius:20px;font-weight:bold;font-size:12px;text-transform:uppercase;background-color:#E8F5E9;color:#2E7D32;">
                                    Confirmée
                                </span>
                            @elseif($order->status == 'cancelled')
                                <span style="display:inline-block;padding:4px 10px;border-radius:20px;font-weight:bold;font-size:12px;text-transform:uppercase;background-color:#FFEBEE;color:#C62828;">
                                    Annulée
                                </span>
                            @elseif($order->status == 'paid')
                                <span style="display:inline-block;padding:4px 10px;border-radius:20px;font-weight:bold;font-size:12px;text-transform:uppercase;background-color:#E3F2FD;color:#1565C0;">
                                    Payée
                                </span>
                            @else
                                <span style="display:inline-block;padding:4px 10px;border-radius:20px;font-weight:bold;font-size:12px;text-transform:uppercase;background-color:#E0F2F1;color:#00897B;">
                                    {{ $order->status }}
                                </span>
                            @endif
                        </td>
                    </tr>
                </table>
                
                <!-- Customer Info -->
                <div style="color:#64748b;font-size:14px;font-weight:bold;margin:20px 0 10px;text-transform:uppercase;">Informations client</div>
                <table width="100%" border="0" cellspacing="0" cellpadding="0">
                    <tr>
                        <td width="50%" style="margin-bottom:10px;vertical-align:top;">
                            <div style="color:#64748b;font-size:12px;font-weight:bold;margin-bottom:4px;">Nom</div>
                            <div style="color:#333333;font-size:14px;">{{ $order->customer->name }}</div>
                        </td>
                        <td width="50%" style="margin-bottom:10px;vertical-align:top;">
                            <div style="color:#64748b;font-size:12px;font-weight:bold;margin-bottom:4px;">Email</div>
                            <div style="color:#333333;font-size:14px;">{{ $order->customer->email }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td width="50%" style="margin-bottom:10px;vertical-align:top;">
                            <div style="color:#64748b;font-size:12px;font-weight:bold;margin-bottom:4px;">Téléphone</div>
                            <div style="color:#333333;font-size:14px;">{{ $order->customer->phone }}</div>
                        </td>
                    </tr>
                </table>
                
                <!-- Payment Method -->
                <div style="margin-bottom:10px;">
                    <div style="color:#64748b;font-size:12px;font-weight:bold;margin-bottom:4px;">Méthode de paiement</div>
                    <div style="color:#333333;font-size:14px;">{{ $order->payment_method }}</div>
                </div>
                
                @if ($order->payment_method == "partiel")
                <!-- Partial Payment -->
                <div style="margin-bottom:10px;">
                    <div style="color:#64748b;font-size:12px;font-weight:bold;margin-bottom:4px;">Acompte</div>
                    <div style="color:#333333;font-size:14px;">{{ $order->partiel_price }} DH</div>
                </div>
                @endif
                
                @if (!$order->blane->is_digital)
                <!-- Shipping Info -->
                <div style="margin-bottom:10px;">
                    <div style="color:#64748b;font-size:12px;font-weight:bold;margin-bottom:4px;">Livraison</div>
                    <div style="color:#333333;font-size:14px;">{{ $order->delivery_address }}</div>
                </div>
                <div style="margin-bottom:10px;">
                    <div style="color:#64748b;font-size:12px;font-weight:bold;margin-bottom:4px;">Ville</div>
                    <div style="color:#333333;font-size:14px;">{{ $order->customer->city }}</div>
                </div>
                <!--<div style="margin-bottom:10px;">
                    <div style="color:#64748b;font-size:12px;font-weight:bold;margin-bottom:4px;">Frais de livraison</div>
                    <div style="color:#333333;font-size:14px;">{{ $order->delivery_fee }} DH</div>
                </div>-->
                @endif
                
                @if ($order->comments)
                <!-- Comments -->
                <div style="margin-bottom:10px;">
                    <div style="color:#64748b;font-size:12px;font-weight:bold;margin-bottom:4px;">Commentaires</div>
                    <div style="color:#333333;font-size:14px;">{{ $order->comments }}</div>
                </div>
                @endif
                
                @if ($order->payment_method == "partiel")
                <!-- Remaining Payment -->
                <div style="background-color:#FFF8E1;padding:12px;margin:15px 0;border-left:4px solid #FF8F00;">
                    <div style="color:#64748b;font-size:12px;font-weight:bold;margin-bottom:4px;">Reste à payer</div>
                    <div style="color:#FF8F00;font-size:14px;font-weight:bold;">{{ $order->total_price - $order->partiel_price }} DH</div>
                </div>
                @endif
                
                <!-- Total -->
                <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color:#E0F2F1;padding:15px;margin-top:20px;border:1px solid #B2DFDB;border-radius:8px;">
                    <tr>
                        <td>
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="font-weight:bold;font-size:16px;color:#00897B;">
                                        <span>Total TTC</span>
                                        <span style="float:right;">{{ $order->total_price }} DH</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        
        <!-- Footer -->
        <tr>
            <td style="background-color:#E0F2F1;padding:20px;text-align:center;font-size:12px;color:#00897B;">
                <div style="font-weight:bold;margin-bottom:10px;">Merci d'avoir choisi <span style="font-weight:bold;">Dabablane</span></div>
                <p style="margin:5px 0;">Nous vous contacterons dès que votre commande sera prête.</p>
                <p style="margin:5px 0;">Pour toute question, contactez notre service client.</p>
            </td>
        </tr>
    </table>
    
    <!--[if (gte mso 9)|(IE)]>
    </td>
    </tr>
    </table>
    <![endif]-->
</body>
</html>