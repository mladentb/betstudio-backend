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
        .credentials { background: #fef3c7; border: 1px solid #f59e0b; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .credentials code { background: #1f2937; color: #10b981; padding: 4px 8px; border-radius: 4px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏆 BetStudio</h1>
        <p>Pozivnica za tim</p>
    </div>
    <div class="content">
        <h2>Zdravo, {{ $member->name }}!</h2>
        <p><strong>{{ $owner->name }}</strong> vas je pozvao/la da se pridružite timu na BetStudio platformi.</p>
        
        <div class="credentials">
            <h3>🔐 Vaši pristupni podaci:</h3>
            <p><strong>Email:</strong> {{ $member->email }}</p>
            <p><strong>Privremena lozinka:</strong> <code>{{ $tempPassword }}</code></p>
        </div>

        <p style="color: #dc2626;"><strong>⚠️ Važno:</strong> Molimo vas da promenite lozinku nakon prvog logovanja!</p>

        <a href="{{ config('app.frontend_url', 'http://localhost:3000') }}/login" class="btn">Prijavite se</a>

        <p>Kompanija: <strong>{{ $owner->company_name }}</strong></p>
    </div>
    <div class="footer">
        <p>© {{ date('Y') }} BetStudio. Sva prava zadržana.</p>
    </div>
</body>
</html>
