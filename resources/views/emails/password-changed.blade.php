<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
        .footer { background: #1f2937; color: #9ca3af; padding: 20px; text-align: center; font-size: 12px; border-radius: 0 0 10px 10px; }
        .warning-box { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔐 BetStudio</h1>
        <p>Bezbednosno obaveštenje</p>
    </div>
    <div class="content">
        <h2>Zdravo, {{ $user->name }}</h2>
        
        <div class="warning-box">
            <strong>🔒 Vaša lozinka je uspešno promenjena</strong><br>
            Ova promena je izvršena {{ now()->format('d.m.Y H:i') }}
        </div>

        <p>Ako ste vi izvršili ovu promenu, možete ignorisati ovaj email.</p>
        
        <p style="color: #dc2626;"><strong>⚠️ Ako NISTE vi promenili lozinku:</strong></p>
        <ol>
            <li>Odmah se prijavite na svoj nalog</li>
            <li>Promenite lozinku</li>
            <li>Kontaktirajte našu podršku</li>
        </ol>
    </div>
    <div class="footer">
        <p>© {{ date('Y') }} BetStudio. Sva prava zadržana.</p>
    </div>
</body>
</html>
