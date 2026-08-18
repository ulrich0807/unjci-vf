<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Code de vérification UNJCI</title></head>
<body style="margin:0;padding:30px;background:#f3f7f5;font-family:Arial,sans-serif;color:#20372d">
  <div style="max-width:600px;margin:auto;padding:32px;background:#fff;border-radius:16px">
    <h1 style="margin-top:0;color:#075a43;font-size:24px">Vérifiez votre adresse e-mail</h1>
    <p>Bonjour {{ $user->name }},</p>
    <p>Votre numéro de carte membre et identifiant de connexion est : <strong>{{ $user->login }}</strong></p>
    <p>Utilisez le code suivant pour confirmer que cette adresse e-mail vous appartient :</p>
    <p style="padding:20px;text-align:center;background:#eaf7f1;border-radius:12px;color:#075a43;font-size:30px;letter-spacing:8px"><strong>{{ $code }}</strong></p>
    <p>Ce code est valable pendant <strong>10 minutes</strong>. Ne le communiquez à personne.</p>
    <p>Si vous n’avez pas effectué cette demande d’adhésion, vous pouvez ignorer cet e-mail.</p>
  </div>
</body>
</html>
