<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
        .footer { background: #1f2937; color: #9ca3af; padding: 20px; text-align: center; font-size: 12px; border-radius: 0 0 10px 10px; }
        .btn { display: inline-block; background: #10b981; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
        .success-box { background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏆 BetStudio</h1>
        <p>Nalog Odobren</p>
    </div>
    <div class="content">
        <h2>Odlične vesti, {{ $user->name }}!</h2>
        
        <div class="success-box">
            <strong>✅ Vaš nalog je odobren!</strong><br>
            Sada možete pristupiti BetStudio platformi i početi sa korišćenjem svih funkcionalnosti.
        </div>

        <p>Vaš nalog tipa <strong>{{ $user->account_type == 'buyer' ? 'Kupac' : 'Prodavac' }}</strong> je sada aktivan.</p>

        <a href="{{ config('app.frontend_url', 'http://localhost:3000') }}/login" class="btn">Prijavite se sada</a>

        <p>Hvala vam što ste odabrali BetStudio!</p>
    </div>
    <div class="footer">
        <p>© {{ date('Y') }} BetStudio. Sva prava zadržana.</p>
    </div>
</body>
</html>
