<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;background:#eef2f6;font-family:'Segoe UI',Arial,Helvetica,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f6;padding:36px 12px;">
<tr><td align="center">
<table role="presentation" width="620" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border-radius:18px;overflow:hidden;">

  <!-- header -->
  <tr>
    <td style="background:linear-gradient(135deg,#1e3a5f 0%,#2f5580 100%);padding:32px 40px;text-align:left;">
      <div style="font-size:11px;letter-spacing:2.5px;text-transform:uppercase;color:#b9c9dc;">{{ $company['tagline'] ?? 'Confirmation' }}</div>
      <div style="font-size:22px;font-weight:700;color:#ffffff;margin-top:6px;">{{ $company['name'] }}</div>
    </td>
  </tr>

  <!-- body -->
  <tr>
    <td style="padding:36px 40px 8px;">
      <h1 style="margin:0;font-size:26px;font-weight:700;color:#1e3a5f;letter-spacing:-0.3px;">{{ $heading }}</h1>
      <p style="margin:12px 0 0;font-size:15px;line-height:1.65;color:#5b6775;">{{ $intro }}</p>
    </td>
  </tr>

  <!-- meta pills -->
  <tr>
    <td style="padding:22px 40px 6px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td width="50%" style="padding-right:8px;">
            <div style="background:#f4f7fb;border-radius:12px;padding:14px 16px;">
              <div style="font-size:10px;letter-spacing:1.4px;text-transform:uppercase;color:#8b97a6;">Commande</div>
              <div style="font-size:16px;font-weight:700;color:#1e3a5f;margin-top:3px;">{{ $order->order_number }}</div>
            </div>
          </td>
          <td width="50%" style="padding-left:8px;">
            <div style="background:#f4f7fb;border-radius:12px;padding:14px 16px;">
              <div style="font-size:10px;letter-spacing:1.4px;text-transform:uppercase;color:#8b97a6;">Client</div>
              <div style="font-size:14px;font-weight:600;color:#1f2937;margin-top:3px;">{{ $order->contact_name }}</div>
              <div style="font-size:12px;color:#6b7280;">{{ $order->contact_email }}</div>
            </div>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- items -->
  <tr>
    <td style="padding:18px 40px 4px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="padding:8px 0;font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#8b97a6;border-bottom:2px solid #e7edf4;">Article</td>
          <td style="padding:8px 0;font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#8b97a6;border-bottom:2px solid #e7edf4;text-align:center;width:48px;">Qté</td>
          <td style="padding:8px 0;font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#8b97a6;border-bottom:2px solid #e7edf4;text-align:right;width:100px;">Montant</td>
        </tr>
        @foreach($order->items as $item)
        <tr>
          <td style="padding:13px 0;border-bottom:1px solid #f0f3f7;font-size:14px;color:#1f2937;">{{ $item->product_name_snapshot ?? 'Article' }}</td>
          <td style="padding:13px 0;border-bottom:1px solid #f0f3f7;font-size:14px;text-align:center;color:#6b7280;">{{ $item->quantity }}</td>
          <td style="padding:13px 0;border-bottom:1px solid #f0f3f7;font-size:14px;text-align:right;color:#1f2937;font-weight:600;">{{ number_format((float) ($item->line_total ?? 0), 2, ',', ' ') }} €</td>
        </tr>
        @endforeach
      </table>
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:8px;">
        <tr>
          <td style="padding:12px 0;font-size:13px;color:#5b6775;">Total</td>
          <td style="padding:12px 0;text-align:right;font-size:22px;font-weight:700;color:#1e3a5f;">{{ number_format((float) $order->grand_total, 2, ',', ' ') }} €</td>
        </tr>
      </table>
    </td>
  </tr>

  @if(!empty($payment['iban']))
  <tr>
    <td style="padding:8px 40px 4px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fb;border-left:4px solid #2f5580;border-radius:10px;">
        <tr><td style="padding:18px 20px;">
          <div style="font-size:11px;letter-spacing:1.6px;text-transform:uppercase;color:#2f5580;font-weight:700;margin-bottom:8px;">Virement</div>
          @if(!empty($payment['beneficiary']))<div style="font-size:14px;margin:2px 0;color:#1f2937;"><span style="color:#6b7280;">Bénéficiaire</span> · {{ $payment['beneficiary'] }}</div>@endif
          <div style="font-size:15px;margin:6px 0;color:#1e3a5f;font-weight:600;letter-spacing:0.3px;">{{ $payment['iban'] }}</div>
          @if(!empty($payment['bic']))<div style="font-size:13px;color:#5b6775;">BIC {{ $payment['bic'] }}</div>@endif
          @if(!empty($payment['instructions']))<div style="font-size:12px;color:#6b7280;margin-top:8px;line-height:1.5;">{{ $payment['instructions'] }}</div>@endif
        </td></tr>
      </table>
    </td>
  </tr>
  @endif

  <tr>
    <td style="padding:20px 40px 32px;font-size:14px;line-height:1.65;color:#5b6775;">
      {{ $closing }}
    </td>
  </tr>

  <!-- footer -->
  <tr>
    <td style="background:#f7f9fc;padding:24px 40px;border-top:1px solid #e7edf4;">
      <div style="font-size:13px;font-weight:700;color:#1e3a5f;">{{ $company['name'] }}</div>
      @if(!empty($company['address']))<div style="font-size:12px;color:#6b7280;margin-top:3px;">{{ $company['address'] }}</div>@endif
      @if(!empty($company['phone']))<div style="font-size:12px;color:#6b7280;">{{ $company['phone'] }}</div>@endif
      <div style="font-size:12px;margin-top:8px;color:#6b7280;">
        Besoin d’aide ? <a href="mailto:{{ $company['support'] }}" style="color:#2f5580;font-weight:600;text-decoration:none;">{{ $company['support'] }}</a>
      </div>
      <div style="font-size:11px;color:#a0a8b3;margin-top:12px;line-height:1.5;">
        Email automatique, merci de ne pas répondre.<br>Écrivez-nous à l’adresse de support.
      </div>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>