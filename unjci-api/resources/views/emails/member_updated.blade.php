<!DOCTYPE html>
<html>
<head>
    <title>Mise à jour de votre profil UNJCI</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2>Bonjour {{ $member->first_name }} {{ $member->last_name }},</h2>
    
    <p>Nous vous informons qu'un administrateur a récemment mis à jour les informations de votre profil sur la plateforme UNJCI.</p>
    
    <p>Voici un récapitulatif des informations actuelles de votre compte :</p>
    
    <ul>
        <li><strong>Nom :</strong> {{ $changes['lastName'] ?? $member->last_name }}</li>
        <li><strong>Prénom :</strong> {{ $changes['firstName'] ?? $member->first_name }}</li>
        <li><strong>Pseudonyme :</strong> {{ $changes['alias'] ?? ($member->alias ?? 'N/A') }}</li>
        <li><strong>Téléphone :</strong> {{ $changes['phone'] ?? $member->phone }}</li>
        <li><strong>Statut professionnel :</strong> {{ $changes['professionalStatus'] ?? $member->professional_status }}</li>
        <li><strong>Entreprise :</strong> {{ $changes['employers'] ?? $member->employers }}</li>
        <li><strong>Fonction :</strong> {{ $changes['functionTitle'] ?? $member->function_title }}</li>
        <li><strong>Numéro CIJP :</strong> {{ $changes['pressCardNumber'] ?? $member->press_card_number }}</li>
        <li><strong>Matricule UNJCI :</strong> {{ $changes['currentMemberNumber'] ?? ($member->current_member_number ?? 'N/A') }}</li>
    </ul>

    <p>Si vous n'êtes pas à l'origine de ces modifications ou si vous constatez une erreur, veuillez nous contacter rapidement.</p>

    <p>Cordialement,<br>
    L'équipe UNJCI</p>
</body>
</html>
