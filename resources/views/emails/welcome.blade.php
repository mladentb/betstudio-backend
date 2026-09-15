<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
        .footer { background: #1f2937; color: #9ca3af; padding: 20px; text-align: center; font-size: 12px; border-radius: 0 0 10px 10px; }
        .btn { display: inline-block; background: #3b82f6; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
        .info-box { background: #dbeafe; border-left: 4px solid #3b82f6; padding: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏆 BetStudio</h1>
        <p>Premium Sports Streaming Platform</p>
    </div>
    <div class="content">
        <h2>Dobrodošli, {{ $user->name }}!</h2>
        <p>Hvala vam što ste se registrovali na BetStudio platformu.</p>
        
        <div class="info-box">
            <strong>Sledeći korak:</strong><br>
            Vaša registracija je primljena i čeka odobrenje od strane našeg tima. 
            Obavestićemo vas putem emaila kada vaš nalog bude aktiviran.
        </div>

        <p><strong>Vaši podaci:</strong></p>
        <ul>
            <li>Ime: {{ $user->name }}</li>
            <li>Email: {{ $user->email }}</li>
            <li>Tip naloga: {{ $user->account_type == 'buyer' ? 'Kupac' : 'Prodavac' }}</li>
            @if($user->company_name)
            <li>Kompanija: {{ $user->company_name }}</li>
            @endif
        </ul>

        <p>Ukoliko imate pitanja, slobodno nas kontaktirajte.</p>
    </div>
    <div class="footer">
        <p>© {{ date('Y') }} BetStudio. Sva prava zadržana.</p>
        <p>Ovaj email je automatski generisan, molimo ne odgovarajte na njega.</p>
    </div>
</body>
</html>
