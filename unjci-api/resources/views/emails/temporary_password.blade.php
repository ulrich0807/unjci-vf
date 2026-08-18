<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Mot de passe temporaire</title></head>
<body style="margin:0;padding:30px;background:#f3f7f5;font-family:Arial,sans-serif;color:#20372d">
  <div style="max-width:600px;margin:auto;padding:32px;background:#fff;border-radius:16px">
    <h1 style="margin-top:0;color:#075a43;font-size:24px">Mot de passe temporaire</h1>
    <p>Bonjour {{ $user->name }},</p>
    <p>Vous avez demandé un nouvel accès à votre espace membre UNJCI.</p>
    <p style="padding:18px;text-align:center;background:#eaf7f1;border-radius:10px;font-size:20px"><strong>{{ $temporaryPassword }}</strong></p>
    <p>Connectez-vous avec votre adresse e-mail ou, après validation de l’adhésion, votre numéro UNJCI, puis modifiez ce mot de passe depuis votre espace personnel.</p>
    <p>Si vous n’êtes pas à l’origine de cette demande, contactez rapidement l’UNJCI.</p>
  </div>
</body>
</html>
