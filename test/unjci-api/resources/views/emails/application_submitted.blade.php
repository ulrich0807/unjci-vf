<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de demande d'adhésion</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 24px; color: #0f172a;">
    <div style="max-width: 640px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 28px; border: 1px solid #e2e8f0;">
        <h2 style="margin-top: 0; color: #0f172a;">Activez votre compte UNJCI</h2>

        <p>Bonjour <strong>{{ $member->first_name }} {{ $member->last_name }}</strong>,</p>

        <p>
            Votre demande d'adhésion a bien été reçue par l'UNJCI. Nous vous remercions pour votre intérêt.
        </p>

        <p>
            Votre dossier est actuellement en cours de traitement. Vous recevrez prochainement une mise à jour de son statut.
        </p>

        <p>
            <strong>Informations du dossier :</strong><br>
            - Numéro de membre UNJCI : <strong>{{ $member->member_number }}</strong><br>
            - Numéro CIJP : <strong>{{ $member->press_card_number }}</strong><br>
            - Type de demande : {{ $member->request_type ?? 'Non renseigné' }}<br>
            - Statut actuel : {{ $member->status ?? 'pending' }}
        </p>

        <p style="margin-top: 24px;">
            Merci,<br>
            <strong>UNJCI</strong>
        </p>
    </div>
</body>
</html>
