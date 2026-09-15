<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
        .footer { background: #1f2937; color: #9ca3af; padding: 20px; text-align: center; font-size: 12px; border-radius: 0 0 10px 10px; }
        .purchase-box { background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
        .total { font-size: 18px; font-weight: bold; color: #8b5cf6; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏆 BetStudio</h1>
        <p>Potvrda kupovine</p>
    </div>
    <div class="content">
        <h2>Hvala na kupovini, {{ $user->name }}!</h2>
        <p>Vaša kupovina je uspešno izvršena.</p>
        
        <div class="purchase-box">
            <h3>Detalji narudžbine #{{ $purchase['id'] ?? 'N/A' }}</h3>
            <p><strong>Datum:</strong> {{ $purchase['date'] ?? now()->format('d.m.Y H:i') }}</p>
            
            @if(isset($purchase['items']))
            <hr>
            @foreach($purchase['items'] as $item)
            <div class="item">
                <span>{{ $item['name'] }}</span>
                <span>{{ $item['price'] }} €</span>
            </div>
            @endforeach
            @endif
            
            <div class="total">
                Ukupno: {{ $purchase['total'] ?? '0.00' }} €
            </div>
        </div>

        <p>Streaming pristup će biti aktivan u roku od nekoliko minuta.</p>
        <p>Ukoliko imate pitanja, kontaktirajte našu podršku.</p>
    </div>
    <div class="footer">
        <p>© {{ date('Y') }} BetStudio. Sva prava zadržana.</p>
    </div>
</body>
</html>
