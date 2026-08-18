<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adhésion UNJCI validée</title>
</head>
<body style="margin: 0; padding: 24px; background-color: #f3f7f5; color: #20372d; font-family: Arial, sans-serif;">
    <div style="max-width: 640px; margin: 0 auto; padding: 32px; background-color: #ffffff; border: 1px solid #dce8e2; border-radius: 16px;">
        <h1 style="margin-top: 0; color: #075a43; font-size: 24px;">Votre adhésion est validée</h1>

        <p>Bonjour <strong>{{ $member->first_name }} {{ $member->last_name }}</strong>,</p>

        <p>
            Après vérification de votre dossier, l'UNJCI a approuvé votre demande d'adhésion.
        </p>

        <p style="margin: 24px 0; padding: 20px; text-align: center; background-color: #eaf7f1; border-radius: 12px;">
            Votre numéro UNJCI<br>
            <strong style="display: inline-block; margin-top: 8px; color: #075a43; font-size: 26px; letter-spacing: 1px;">{{ $member->member_number }}</strong>
        </p>

        <p>
            Vous pouvez désormais utiliser ce numéro UNJCI comme identifiant de connexion, avec votre mot de passe habituel, pour accéder à votre espace personnel.
        </p>

        <p style="margin-top: 28px;">
            Bienvenue parmi les membres de l'UNJCI.<br><br>
            <strong>L'équipe UNJCI</strong>
        </p>
    </div>
</body>
</html>
