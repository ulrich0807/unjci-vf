<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription enregistrée - UNJCI</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 24px; color: #0f172a;">
    <div style="max-width: 640px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 28px; border: 1px solid #e2e8f0;">
        <h2 style="margin-top: 0; color: #0f172a;">Votre inscription est enregistrée</h2>

        <p>Bonjour <strong>{{ $member->first_name }} {{ $member->last_name }}</strong>,</p>

        <p>
            Votre demande d'adhésion a bien été reçue par l'UNJCI. Nous vous remercions pour votre intérêt.
        </p>

        <p>
            Vous pouvez maintenant vous connecter avec votre adresse e-mail et votre mot de passe afin de régler les frais d’adhésion.
        </p>

        <p>
            <strong>Informations du dossier :</strong><br>
            - Numéro CIJP : <strong>{{ $member->press_card_number }}</strong><br>
            - Type de demande : {{ $member->request_type ?? 'Non renseigné' }}<br>
            - Prochaine étape : paiement des frais d’adhésion
        </p>

        <p style="margin-top: 24px;">
            Merci,<br>
            <strong>UNJCI</strong>
        </p>
    </div>
</body>
</html>
