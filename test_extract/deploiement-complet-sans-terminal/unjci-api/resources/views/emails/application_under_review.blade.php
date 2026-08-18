<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demande en cours de validation</title>
</head>
<body style="margin: 0; padding: 24px; background-color: #f3f7f5; color: #20372d; font-family: Arial, sans-serif;">
    <div style="max-width: 640px; margin: 0 auto; padding: 32px; background-color: #ffffff; border: 1px solid #dce8e2; border-radius: 16px;">
        <h1 style="margin-top: 0; color: #075a43; font-size: 24px;">Votre demande est en cours de validation</h1>

        <p>Bonjour <strong>{{ $member->first_name }} {{ $member->last_name }}</strong>,</p>

        <p>
            Votre paiement a été confirmé. Votre demande a été automatiquement transmise à l'UNJCI pour vérification.
        </p>

        <p>
            Vous recevrez un nouvel e-mail dès que l'examen de votre dossier sera terminé. Pendant cette étape, vous pouvez continuer à accéder à votre espace personnel avec votre adresse e-mail et votre mot de passe.
        </p>

        <p style="margin-top: 28px;">
            Cordialement,<br>
            <strong>L'équipe UNJCI</strong>
        </p>
    </div>
</body>
</html>
