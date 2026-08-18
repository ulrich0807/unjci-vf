-- Catalogue initial des entreprises de presse et médias UNJCI.
-- Importer ce fichier une seule fois depuis phpMyAdmin.
-- Généré automatiquement depuis database/data/press-media.initial.json.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `press_companies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `press_companies_name_unique` (`name`),
  KEY `press_companies_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `press_media` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `press_company_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `type` VARCHAR(20) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `press_media_press_company_id_name_unique` (`press_company_id`, `name`),
  KEY `press_media_is_active_index` (`is_active`),
  KEY `press_media_press_company_id_foreign` (`press_company_id`),
  CONSTRAINT `press_media_press_company_id_foreign`
    FOREIGN KEY (`press_company_id`) REFERENCES `press_companies` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

START TRANSACTION;

INSERT IGNORE INTO `press_companies`
  (`name`, `is_active`, `created_at`, `updated_at`)
VALUES
  ('2CM GROUP SARL', 1, NOW(), NOW()),
  ('A2K COMMUNICATION', 1, NOW(), NOW()),
  ('ABIDJANSHOW.COM', 1, NOW(), NOW()),
  ('ACD CORPORATE SERVICES', 1, NOW(), NOW()),
  ('ACTION + ABIDJAN', 1, NOW(), NOW()),
  ('AD COMMUNICATION', 1, NOW(), NOW()),
  ('AFRIK CHALLENGES SARL', 1, NOW(), NOW()),
  ('AFRIQUE ETUDES ET STRATEGIES', 1, NOW(), NOW()),
  ('AFRIQUE ETUDES STRATEGIES', 1, NOW(), NOW()),
  ('AGENCE DE PRESSE REGIONALE-COTE D’IVOIRE', 1, NOW(), NOW()),
  ('AGENCE DE PRESSE TOP NEWS AFRICA COTE D’IVOIRE', 1, NOW(), NOW()),
  ('AGRI EVENTS MEDIA', 1, NOW(), NOW()),
  ('AKWABA MEDIAS CORPORATION', 1, NOW(), NOW()),
  ('AKWABA MEDIAS CORPORATION (AMC)', 1, NOW(), NOW()),
  ('ALERTE INFOS SARL', 1, NOW(), NOW()),
  ('ASEC MIMOSAS COM. SARL', 1, NOW(), NOW()),
  ('AVENA COMMUNICATION', 1, NOW(), NOW()),
  ('AZIKO SARL', 1, NOW(), NOW()),
  ('BAAB EDITIONS', 1, NOW(), NOW()),
  ('BAKARA COMMUNICATION', 1, NOW(), NOW()),
  ('BETHLEHEM EDITIONS', 1, NOW(), NOW()),
  ('BLAMO’O SARL', 1, NOW(), NOW()),
  ('BUILD GROUPE SARL', 1, NOW(), NOW()),
  ('CENTRAL MEDIA', 1, NOW(), NOW()),
  ('CHAINES D’AVENIR SARL', 1, NOW(), NOW()),
  ('CHANODI', 1, NOW(), NOW()),
  ('CONSORTIUM DOUDOU GOOH GROUP', 1, NOW(), NOW()),
  ('COPIE CONFORME SARL', 1, NOW(), NOW()),
  ('COUL CORPORATE', 1, NOW(), NOW()),
  ('CREDOCHRISTI.COM SARL U', 1, NOW(), NOW()),
  ('DF COMMUNICATION SARL', 1, NOW(), NOW()),
  ('DIMEA –COM SARL', 1, NOW(), NOW()),
  ('DUNUYA COMMUNICATION', 1, NOW(), NOW()),
  ('ECLOSION COMMUNICATION CONSULTING', 1, NOW(), NOW()),
  ('EDITION NEWELL', 1, NOW(), NOW()),
  ('EDITIONS CHAMPION COTE D’IVOIRE', 1, NOW(), NOW()),
  ('EDITIONS LE PROGRES', 1, NOW(), NOW()),
  ('EKN GROUPE', 1, NOW(), NOW()),
  ('EMERGENCE EDITION', 1, NOW(), NOW()),
  ('EMF-SARL', 1, NOW(), NOW()),
  ('ETCHNA GROUPE', 1, NOW(), NOW()),
  ('EXE MEDIAS GROUPE', 1, NOW(), NOW()),
  ('EXPRESS MEDIAS SARL', 1, NOW(), NOW()),
  ('FAITH IMPACT SARL', 1, NOW(), NOW()),
  ('FIRST NEWS', 1, NOW(), NOW()),
  ('GBICH EDITIONS', 1, NOW(), NOW()),
  ('GLOBAL TRAITEMENT', 1, NOW(), NOW()),
  ('GO ! MEDIA', 1, NOW(), NOW()),
  ('GRACE MONDIALE GROUPE', 1, NOW(), NOW()),
  ('GRIOTECH 24 SARL U', 1, NOW(), NOW()),
  ('GROUP SUD MEDIA', 1, NOW(), NOW()),
  ('GROUPE BETHLEME', 1, NOW(), NOW()),
  ('GROUPE CANAL IVOIRE COMMUNICATION', 1, NOW(), NOW()),
  ('GROUPE DE PRESSE LE DIRECT', 1, NOW(), NOW()),
  ('GROUPE DJREY', 1, NOW(), NOW()),
  ('GROUPE GKL MEDIAS', 1, NOW(), NOW()),
  ('GROUPE IDRISSA IRENE OPPORTUNE SERVICES (G2IOS)', 1, NOW(), NOW()),
  ('GROUPE MEDIA AKWABA', 1, NOW(), NOW()),
  ('GROUPE MEDIAS AKWABA', 1, NOW(), NOW()),
  ('GROUPE OCEAN COMMUNICATION', 1, NOW(), NOW()),
  ('GROUPE OCEAN VISION COMMUNICATION', 1, NOW(), NOW()),
  ('GROUPE RTI', 1, NOW(), NOW()),
  ('HABEAS COMMUNICATION', 1, NOW(), NOW()),
  ('HASSEYE EDITIONS', 1, NOW(), NOW()),
  ('HATENE PRODUCTIONS', 1, NOW(), NOW()),
  ('HBHR SA', 1, NOW(), NOW()),
  ('HERIJO COMMUNICATION', 1, NOW(), NOW()),
  ('HOPE EDITION SARL', 1, NOW(), NOW()),
  ('HORIZON MEDIA', 1, NOW(), NOW()),
  ('IDEAL COM NET SARLU', 1, NOW(), NOW()),
  ('IMPACT-STRATEGIES COMMUNICATION MARKETING SARL (ISTRACOM)', 1, NOW(), NOW()),
  ('INNOV IMPACT GROUP', 1, NOW(), NOW()),
  ('INVISIBLES EYES COM', 1, NOW(), NOW()),
  ('IRH SARL', 1, NOW(), NOW()),
  ('JD EDITIONS MAGAZINE ET TELEVISION SARL', 1, NOW(), NOW()),
  ('JEN’S CORPORATION', 1, NOW(), NOW()),
  ('JPS GROUP', 1, NOW(), NOW()),
  ('KABHE EDITIONS', 1, NOW(), NOW()),
  ('KAILCEDRA GROUP', 1, NOW(), NOW()),
  ('KANDO COMMUNICATION SARL', 1, NOW(), NOW()),
  ('LA CLARTE D’EBURNIE', 1, NOW(), NOW()),
  ('LA REFONDATION', 1, NOW(), NOW()),
  ('LAURANA GROUPE', 1, NOW(), NOW()),
  ('LE BANCO.NET SUARL', 1, NOW(), NOW()),
  ('LE POINT SUR SARL U', 1, NOW(), NOW()),
  ('LES EDITIONS ARC-EN CIEL', 1, NOW(), NOW()),
  ('LES EDITIONS DE L’AVENIR', 1, NOW(), NOW()),
  ('LES EDITIONS DU NIMBA', 1, NOW(), NOW()),
  ('LES EDITIONS D’AUJOURDH’HUI', 1, NOW(), NOW()),
  ('LES EDITIONS LE FRONT', 1, NOW(), NOW()),
  ('LES EDITIONS LE RASSEMBLEMENT', 1, NOW(), NOW()),
  ('LES EDITIONS LE REVEIL', 1, NOW(), NOW()),
  ('LES EDITIONS NEWEL', 1, NOW(), NOW()),
  ('LES EDITIONS NORD SUD', 1, NOW(), NOW()),
  ('LES EDITIONS SINGA', 1, NOW(), NOW()),
  ('LES EDITIONS SIPPRAC', 1, NOW(), NOW()),
  ('LES EDITIONS STRATEGIES', 1, NOW(), NOW()),
  ('LES EDITIONS VILLARD', 1, NOW(), NOW()),
  ('LES EDITIONS YASSINE', 1, NOW(), NOW()),
  ('LES MEDIAS DE JESUS CHRIST EDITIONS ET PRODUCTIONS', 1, NOW(), NOW()),
  ('LES SPLENDIDES EDITIONS DU MATIN', 1, NOW(), NOW()),
  ('LG’EDITION', 1, NOW(), NOW()),
  ('LIBELLULE COMMUNCATION', 1, NOW(), NOW()),
  ('LUXAF SARL', 1, NOW(), NOW()),
  ('LYN COM', 1, NOW(), NOW()),
  ('MABOUSSOLE GROUP SARL U', 1, NOW(), NOW()),
  ('MAKOMPE COMMUNICATION', 1, NOW(), NOW()),
  ('MANAGEMENT IMAGE LUXE COMMUNICATION CONSULTING CREATION (MILC)', 1, NOW(), NOW()),
  ('MAYAMA EDITION', 1, NOW(), NOW()),
  ('MEDIA GROUP', 1, NOW(), NOW()),
  ('MEDIA GROUPE', 1, NOW(), NOW()),
  ('MEDIUM X', 1, NOW(), NOW()),
  ('MIENSAH GLOBAL SERVICES', 1, NOW(), NOW()),
  ('MOAHE COMMUNICATION', 1, NOW(), NOW()),
  ('MULTI-CONSULT GESTION', 1, NOW(), NOW()),
  ('NAMOYA CONCEPT INTERNATIONAL ENTREPRISE', 1, NOW(), NOW()),
  ('NASOPRESSE SARL', 1, NOW(), NOW()),
  ('NOUVELLE PRESSE EN LIGNE PANAFRICAINE', 1, NOW(), NOW()),
  ('NOUVELLES DU CONTINENT', 1, NOW(), NOW()),
  ('OFFICE SUN', 1, NOW(), NOW()),
  ('OLYMPE INFO SARL', 1, NOW(), NOW()),
  ('OMNIPRESENCE COMMUNICATION', 1, NOW(), NOW()),
  ('ONDITQUOI.CI SARL', 1, NOW(), NOW()),
  ('ONE PEOPLE NEWS NETWORK AFRICA COTE D’IVOIRE', 1, NOW(), NOW()),
  ('OURASI POST SARL', 1, NOW(), NOW()),
  ('OVAJAB PRODUCTION', 1, NOW(), NOW()),
  ('OVATION', 1, NOW(), NOW()),
  ('OZYL-WHAZNEY EDITIONS', 1, NOW(), NOW()),
  ('OZYL-WHAZNEY SARL U', 1, NOW(), NOW()),
  ('PHARMA CONSULT', 1, NOW(), NOW()),
  ('PLAN B MEDIAS SERVICES', 1, NOW(), NOW()),
  ('POLITIQUE AFRIQUE INFO', 1, NOW(), NOW()),
  ('PRESTIGE EDITO-PARIS', 1, NOW(), NOW()),
  ('RELAIS TV SARL U', 1, NOW(), NOW()),
  ('RK GROUP SARL', 1, NOW(), NOW()),
  ('SADECOM', 1, NOW(), NOW()),
  ('SENEVE MEDIA', 1, NOW(), NOW()),
  ('SENTIERS D’AFRIQUE', 1, NOW(), NOW()),
  ('SEPCI', 1, NOW(), NOW()),
  ('SERENTI GROUP SARL', 1, NOW(), NOW()),
  ('SHINE GROUP', 1, NOW(), NOW()),
  ('SIDEMA GROUP', 1, NOW(), NOW()),
  ('SIDMAR HOLDING EXCELLENCE (SIHOLEX)', 1, NOW(), NOW()),
  ('SIKA TIMES SARL', 1, NOW(), NOW()),
  ('SINO AFRIQUE MEDIA SARL', 1, NOW(), NOW()),
  ('SITE INTERNET TOP VISION', 1, NOW(), NOW()),
  ('SNECI', 1, NOW(), NOW()),
  ('SNPECI', 1, NOW(), NOW()),
  ('SOCEF – NTIC', 1, NOW(), NOW()),
  ('SOCIETE AFRICAINE D’EDITION ET D’IMPRIMERIE(SAEI)', 1, NOW(), NOW()),
  ('SOCIETE AFRICAINE D’EDITON ET DE COMMUNICATION', 1, NOW(), NOW()),
  ('SOCIETE D’EDITION DE PRESSE (SEPCI)', 1, NOW(), NOW()),
  ('SOCIETE LA LETTRE DE L’ENVIRONNEMENT', 1, NOW(), NOW()),
  ('SOCIETE NOUVELLE D’EDITION ET DE PRESSE EN COTE D’IVOIRE (SNPECI)', 1, NOW(), NOW()),
  ('SONACOM-CI SARL U', 1, NOW(), NOW()),
  ('SPEED MEDIA EDITION', 1, NOW(), NOW()),
  ('SWEET AGENCE SARL', 1, NOW(), NOW()),
  ('TOMPKI NEWS', 1, NOW(), NOW()),
  ('TRICLINIUM', 1, NOW(), NOW()),
  ('TSCI LIMITED COMPANY', 1, NOW(), NOW()),
  ('UNITE COMMUNICATION', 1, NOW(), NOW()),
  ('VATI&CO', 1, NOW(), NOW()),
  ('VAYOL COMMUNICATION', 1, NOW(), NOW()),
  ('VOGUES EDITIONS', 1, NOW(), NOW()),
  ('VOLTAGE EDITIONS', 1, NOW(), NOW()),
  ('WEBLOGY OFFSHORE', 1, NOW(), NOW()),
  ('WOROBA.NET SARL', 1, NOW(), NOW()),
  ('WV REELCOM', 1, NOW(), NOW()),
  ('WVREELCOM', 1, NOW(), NOW()),
  ('YA’AS MEDIA', 1, NOW(), NOW()),
  ('YEFIEN COMMUNICATION SARL', 1, NOW(), NOW());

INSERT IGNORE INTO `press_media`
  (`press_company_id`, `name`, `type`, `is_active`, `created_at`, `updated_at`)
SELECT
  company.`id`,
  catalog.`media_name`,
  catalog.`media_type`,
  1,
  NOW(),
  NOW()
FROM (
  SELECT '2CM GROUP SARL' AS company_name, '2CMINFO.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'A2K COMMUNICATION' AS company_name, 'ABIDJANECONOMIE.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'A2K COMMUNICATION' AS company_name, 'L’Abidjanais' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'ABIDJANSHOW.COM' AS company_name, 'ABIDJANSHOW.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'ACD CORPORATE SERVICES' AS company_name, 'EXCELLENCEAFRIK.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'ACTION + ABIDJAN' AS company_name, 'Supersport' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'AD COMMUNICATION' AS company_name, 'LAREGIONALENEWS.CI' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'AFRIK CHALLENGES SARL' AS company_name, 'AFRIKCHALLENGES.INFO' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'AFRIQUE ETUDES ET STRATEGIES' AS company_name, 'AFRIKSOIR.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'AFRIQUE ETUDES STRATEGIES' AS company_name, 'Ivoir’Hebdo' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'AGENCE DE PRESSE REGIONALE-COTE D’IVOIRE' AS company_name, 'APR NEWS' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'AGENCE DE PRESSE TOP NEWS AFRICA COTE D’IVOIRE' AS company_name, 'TOPNEWSAFRICA.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'AGRI EVENTS MEDIA' AS company_name, 'La Tribune Agricole' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'AKWABA MEDIAS CORPORATION' AS company_name, 'Le Tam-tam Parleur' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'AKWABA MEDIAS CORPORATION (AMC)' AS company_name, 'LENQUETEURDETERMINE.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'AKWABA MEDIAS CORPORATION (AMC)' AS company_name, 'LETAMTAMPARLEUR.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'ALERTE INFOS SARL' AS company_name, 'ALERTE-INFO.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'ASEC MIMOSAS COM. SARL' AS company_name, 'Asec Mimosas' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'AVENA COMMUNICATION' AS company_name, 'LEMERIDIEN.CI' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'AZIKO SARL' AS company_name, 'ADJUWA.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'BAAB EDITIONS' AS company_name, 'BaaB d’Abidjan' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'BAAB EDITIONS' AS company_name, 'Bmag' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'BAKARA COMMUNICATION' AS company_name, 'Le Franc-Tireur' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'BETHLEHEM EDITIONS' AS company_name, 'ACTUCHRETIENNE.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'BLAMO’O SARL' AS company_name, 'Blamo’o' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'BUILD GROUPE SARL' AS company_name, 'AFRIQUE-SUR 7.FR' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'CENTRAL MEDIA' AS company_name, 'LINFODELECONOMIE.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'CHAINES D’AVENIR SARL' AS company_name, 'LINFORMATEUR.CI' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'CHANODI' AS company_name, 'MEHIELINFO.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'CONSORTIUM DOUDOU GOOH GROUP' AS company_name, 'INFORELAYEUR.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'COPIE CONFORME SARL' AS company_name, 'Nouvelle Afrique' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'COUL CORPORATE' AS company_name, 'ABIDJANNEWSCI.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'CREDOCHRISTI.COM SARL U' AS company_name, 'CREDOCHRISTI.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'DF COMMUNICATION SARL' AS company_name, 'AFRIQUEINFO.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'DIMEA –COM SARL' AS company_name, 'ECHODISTRICT.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'DUNUYA COMMUNICATION' AS company_name, 'Le Miroir d’Abidjan' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'ECLOSION COMMUNICATION CONSULTING' AS company_name, 'IVOIRE.CI' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'ECLOSION COMMUNICATION CONSULTING' AS company_name, 'LADIPLOMATIQUEDABIDJAN.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'EDITION NEWELL' AS company_name, 'INFODIRECTE.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'EDITIONS CHAMPION COTE D’IVOIRE' AS company_name, 'Champion' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'EDITIONS LE PROGRES' AS company_name, 'LEPROGRES.CI' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'EKN GROUPE' AS company_name, 'Le Quotidien d’Abidjan' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'EMERGENCE EDITION' AS company_name, 'DESTINATIONCI.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'EMF-SARL' AS company_name, 'LECURIEUXDABIDJAN.INFO' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'ETCHNA GROUPE' AS company_name, 'LEVENEMENTAFRICAIN.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'EXE MEDIAS GROUPE' AS company_name, 'NORDSUD.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'EXE MEDIAS GROUPE' AS company_name, 'NORDSUD.INFOS' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'EXE MEDIAS GROUPE' AS company_name, 'NORDSUD.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'EXE MEDIAS GROUPE' AS company_name, 'NORDSUD.PRESSE' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'EXPRESS MEDIAS SARL' AS company_name, 'IVOIREXPRESS.CI' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'FAITH IMPACT SARL' AS company_name, 'IVOIRE INTER.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'FAITH IMPACT SARL' AS company_name, 'LEJOURPILE.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'FIRST NEWS' AS company_name, 'L’Inter' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'FIRST NEWS' AS company_name, 'Soir Info' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'GBICH EDITIONS' AS company_name, 'Gbich !' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'GLOBAL TRAITEMENT' AS company_name, 'MEDIADIVERSITY.INFO' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'GO ! MEDIA' AS company_name, 'Allo ! Police' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'GRACE MONDIALE GROUPE' AS company_name, 'Assayié' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'GRIOTECH 24 SARL U' AS company_name, 'GRIOTECH 24.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'GROUP SUD MEDIA' AS company_name, 'Savane Infos' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'GROUPE BETHLEME' AS company_name, 'Apocalypse' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'GROUPE CANAL IVOIRE COMMUNICATION' AS company_name, 'CANALIVOIRE.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'GROUPE DE PRESSE LE DIRECT' AS company_name, 'LEDIRECTINFO.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'GROUPE DE PRESSE LE DIRECT' AS company_name, 'Le Direct' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'GROUPE DJREY' AS company_name, 'La Nouvelle Alliance' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'GROUPE GKL MEDIAS' AS company_name, 'KIOSQECO.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'GROUPE IDRISSA IRENE OPPORTUNE SERVICES (G2IOS)' AS company_name, 'AFRIKMONDE.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'GROUPE MEDIA AKWABA' AS company_name, 'La Clarté' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'GROUPE MEDIAS AKWABA' AS company_name, 'AKWABAVISION.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'GROUPE OCEAN COMMUNICATION' AS company_name, 'L’Ecole' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'GROUPE OCEAN VISION COMMUNICATION' AS company_name, 'ARTICI.INFO' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'GROUPE OCEAN VISION COMMUNICATION' AS company_name, 'DEMAININFO.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'GROUPE OCEAN VISION COMMUNICATION' AS company_name, 'JUSTEINFOS.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'GROUPE OCEAN VISION COMMUNICATION' AS company_name, 'LECOLEINFO.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'GROUPE OCEAN VISION COMMUNICATION' AS company_name, 'LEDEMOCRATEPLUS.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'GROUPE RTI' AS company_name, 'RTI1' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'GROUPE RTI' AS company_name, 'RTI2' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'GROUPE RTI' AS company_name, 'RTI BOUAKE' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'HABEAS COMMUNICATION' AS company_name, 'LES SENTINELLES.INFO' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'HABEAS COMMUNICATION' AS company_name, 'Les Sentinelles d’Abidjan' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'HASSEYE EDITIONS' AS company_name, 'L’Essor Ivoirien' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'HATENE PRODUCTIONS' AS company_name, 'KOUNDANINFOS.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'HBHR SA' AS company_name, 'Le Nouvel Emploi Magazine' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'HERIJO COMMUNICATION' AS company_name, 'Tribune Ivoire' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'HOPE EDITION SARL' AS company_name, 'LEDEBATIVOIRIEN.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'HORIZON MEDIA' AS company_name, 'Le Mandat' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'IDEAL COM NET SARLU' AS company_name, 'Le Bélier' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'IMPACT-STRATEGIES COMMUNICATION MARKETING SARL (ISTRACOM)' AS company_name, 'RHDP Info' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'INNOV IMPACT GROUP' AS company_name, 'LETILETOO.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'INNOV IMPACT GROUP' AS company_name, 'VOIXDEFEMME.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'INVISIBLES EYES COM' AS company_name, 'Le Perroquet Libéré' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'IRH SARL' AS company_name, 'RH Mag' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'JD EDITIONS MAGAZINE ET TELEVISION SARL' AS company_name, 'JDEDITIONSMAGAZINE.TV' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'JEN’S CORPORATION' AS company_name, 'ADN Politics' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'JPS GROUP' AS company_name, 'ECHOSDUMONDE.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'KABHE EDITIONS' AS company_name, 'AFRIQUEMATIN-NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'KAILCEDRA GROUP' AS company_name, 'NOIR&BLANC.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'KAILCEDRA GROUP' AS company_name, 'POUVOIRS.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'KAILCEDRA GROUP' AS company_name, 'VISVAS.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'KANDO COMMUNICATION SARL' AS company_name, 'CACAOCAFENEWS.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'LA CLARTE D’EBURNIE' AS company_name, 'RHDP News' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'LA REFONDATION' AS company_name, 'Notre Voie' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'LAURANA GROUPE' AS company_name, 'Le Temps' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'LE BANCO.NET SUARL' AS company_name, 'LEBANCO.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'LE POINT SUR SARL U' AS company_name, 'LEPOINTSUR.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'LES EDITIONS ARC-EN CIEL' AS company_name, 'L’Arc-En-Ciel' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'LES EDITIONS DE L’AVENIR' AS company_name, 'LAVENIR.CI' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'LES EDITIONS DE L’AVENIR' AS company_name, 'L’Avenir' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'LES EDITIONS DU NIMBA' AS company_name, 'Liberté' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'LES EDITIONS D’AUJOURDH’HUI' AS company_name, 'Aujourd’hui' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'LES EDITIONS LE FRONT' AS company_name, 'L’Héritage' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'LES EDITIONS LE RASSEMBLEMENT' AS company_name, 'Le Rassemblement' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'LES EDITIONS LE REVEIL' AS company_name, 'Le Nouveau Réveil' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'LES EDITIONS NEWEL' AS company_name, 'Le Scolaire' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'LES EDITIONS NORD SUD' AS company_name, 'Générations Nouvelles' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'LES EDITIONS NORD SUD' AS company_name, 'Nord Sud Infos' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'LES EDITIONS SINGA' AS company_name, 'La Régionale' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'LES EDITIONS SIPPRAC' AS company_name, 'La Retraite Active' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'LES EDITIONS STRATEGIES' AS company_name, 'Abidjan 24' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'LES EDITIONS VILLARD' AS company_name, 'LENQUETEUR.INFO' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'LES EDITIONS VILLARD' AS company_name, 'L’Enquêteur' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'LES EDITIONS YASSINE' AS company_name, 'LINFOEXPRESS.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'LES EDITIONS YASSINE' AS company_name, 'L’Expression' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'LES MEDIAS DE JESUS CHRIST EDITIONS ET PRODUCTIONS' AS company_name, 'LE SERVITEUR DE JESUS-CHRIST' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'LES SPLENDIDES EDITIONS DU MATIN' AS company_name, 'Le Matin' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'LG’EDITION' AS company_name, 'La Voie Originale' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'LIBELLULE COMMUNCATION' AS company_name, 'MINUTES-ECO.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'LUXAF SARL' AS company_name, 'Luxaf' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'LYN COM' AS company_name, 'Le Sursaut' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'MABOUSSOLE GROUP SARL U' AS company_name, 'MABOUSSOLE.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'MAKOMPE COMMUNICATION' AS company_name, 'RHDP24.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'MANAGEMENT IMAGE LUXE COMMUNICATION CONSULTING CREATION (MILC)' AS company_name, 'MILCMAGAZINE.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'MAYAMA EDITION' AS company_name, 'Le Patriote' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'MEDIA GROUP' AS company_name, 'ABIDJANSPORTS.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'MEDIA GROUPE' AS company_name, 'Abidjan Sports' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'MEDIUM X' AS company_name, 'Life' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'MEDIUM X' AS company_name, 'Tycoon' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'MIENSAH GLOBAL SERVICES' AS company_name, 'WORLDCANALINFO.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'MOAHE COMMUNICATION' AS company_name, 'AGROPASTORALNEWS.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'MOAHE COMMUNICATION' AS company_name, 'Agro Pastoral News' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'MOAHE COMMUNICATION' AS company_name, 'BETAILDAFRIQUE.INFO' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'MOAHE COMMUNICATION' AS company_name, 'ELEVAGEDAFRIQUE.INFO' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'MOAHE COMMUNICATION' AS company_name, 'Elevage d’Afrique' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'MOAHE COMMUNICATION' AS company_name, 'IVOIRECANALINFO.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'MOAHE COMMUNICATION' AS company_name, 'VIGILEINFO.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'MULTI-CONSULT GESTION' AS company_name, 'PME Magazine' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'NAMOYA CONCEPT INTERNATIONAL ENTREPRISE' AS company_name, 'COTEDIVOIRE-TODAY.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'NASOPRESSE SARL' AS company_name, 'NASOPRESSE.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'NOUVELLE PRESSE EN LIGNE PANAFRICAINE' AS company_name, 'REVEILECONOMIQUE.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'NOUVELLES DU CONTINENT' AS company_name, 'NDC-INFO.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'OFFICE SUN' AS company_name, 'Le Baromètre' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'OFFICE SUN' AS company_name, 'Le Nouveau Navire' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'OLYMPE INFO SARL' AS company_name, 'LINFODROME.CI' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'OMNIPRESENCE COMMUNICATION' AS company_name, 'HUB24.INFO' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'ONDITQUOI.CI SARL' AS company_name, 'ONDITQUOI.CI' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'ONE PEOPLE NEWS NETWORK AFRICA COTE D’IVOIRE' AS company_name, 'ACTUMANIA.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'OURASI POST SARL' AS company_name, 'OURASIPOST.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'OVAJAB PRODUCTION' AS company_name, 'OVAJAB MEDIA LLC' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'OVATION' AS company_name, '7CULTURE.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'OZYL-WHAZNEY EDITIONS' AS company_name, 'Le Débat Ivoirien' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'OZYL-WHAZNEY SARL U' AS company_name, 'AFRICANEWSQUICK.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'PHARMA CONSULT' AS company_name, 'SANTEAFRIQUE.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'PLAN B MEDIAS SERVICES' AS company_name, 'FILETIVOIRIEN.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'POLITIQUE AFRIQUE INFO' AS company_name, 'POLEAFRIQUE.INFO' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'PRESTIGE EDITO-PARIS' AS company_name, 'LHORIZONINFO.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'RELAIS TV SARL U' AS company_name, 'RELAISTV.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'RK GROUP SARL' AS company_name, 'ECHODABIDJAN.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'SADECOM' AS company_name, 'LEPAYS225.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'SENEVE MEDIA' AS company_name, 'INFOLUCIDE.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'SENTIERS D’AFRIQUE' AS company_name, 'Transport Hebdo' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'SEPCI' AS company_name, 'PRESSECOTEDIVOIRE.CI' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'SERENTI GROUP SARL' AS company_name, 'NEEMAMEDIA.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'SERENTI GROUP SARL' AS company_name, 'TEMBO.MEDIA' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'SHINE GROUP' AS company_name, 'CITYMAG-CI.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'SIDEMA GROUP' AS company_name, 'COTEDIVOIREINFOS.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'SIDMAR HOLDING EXCELLENCE (SIHOLEX)' AS company_name, 'IVOIRINTER24.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'SIKA TIMES SARL' AS company_name, 'SIKAFINANCE.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'SIKA TIMES SARL' AS company_name, 'Sika Finance' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'SINO AFRIQUE MEDIA SARL' AS company_name, 'SINOAFRIQUEMAG.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'SITE INTERNET TOP VISION' AS company_name, 'IVOIREVISION' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'SNECI' AS company_name, 'L’Eléphant Déchaîné' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'SNPECI' AS company_name, 'FRATMAT.INFO' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'SNPECI' AS company_name, 'VITRINE.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'SOCEF – NTIC' AS company_name, 'L’Intelligent d’Abidjan' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'SOCIETE AFRICAINE D’EDITION ET D’IMPRIMERIE(SAEI)' AS company_name, 'Le Jour Plus' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'SOCIETE AFRICAINE D’EDITON ET DE COMMUNICATION' AS company_name, 'Le Canard Déchainé' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'SOCIETE AFRICAINE D’EDITON ET DE COMMUNICATION' AS company_name, 'Le Panafricain' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'SOCIETE D’EDITION DE PRESSE (SEPCI)' AS company_name, 'Dernière Heure Monde' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'SOCIETE LA LETTRE DE L’ENVIRONNEMENT' AS company_name, 'La Lettre de L’Environnement' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'SOCIETE NOUVELLE D’EDITION ET DE PRESSE EN COTE D’IVOIRE (SNPECI)' AS company_name, 'Emergence Economique' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'SOCIETE NOUVELLE D’EDITION ET DE PRESSE EN COTE D’IVOIRE (SNPECI)' AS company_name, 'Fraternité Matin' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'SONACOM-CI SARL U' AS company_name, 'LESECHOSCI.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'SPEED MEDIA EDITION' AS company_name, 'Le Bélier Intrépide' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'SWEET AGENCE SARL' AS company_name, 'MAPRESSE.INFO' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'TOMPKI NEWS' AS company_name, 'CROCINFOS.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'TRICLINIUM' AS company_name, 'LACTUALITE-IVOIRIENNE.CI' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'TSCI LIMITED COMPANY' AS company_name, 'SCOOPER NEWS' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'UNITE COMMUNICATION' AS company_name, 'UNITE.CI' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'VATI&CO' AS company_name, 'LETAU.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'VAYOL COMMUNICATION' AS company_name, 'Echos de la République' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'VOGUES EDITIONS' AS company_name, 'VNEWSCI.COM' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'VOLTAGE EDITIONS' AS company_name, 'Abidjan Planet' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'WEBLOGY OFFSHORE' AS company_name, 'ABIDJAN.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'WOROBA.NET SARL' AS company_name, 'WOROBA.NET' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'WV REELCOM' AS company_name, 'Acturoute the Review' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'WVREELCOM' AS company_name, 'ACTUROUTES.INFO' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'WVREELCOM' AS company_name, 'NIAN' AS media_name, 'Numérique' AS media_type
  UNION ALL SELECT 'YA’AS MEDIA' AS company_name, 'Eco Diplomate' AS media_name, 'Écrit' AS media_type
  UNION ALL SELECT 'YEFIEN COMMUNICATION SARL' AS company_name, 'DIGITALMAG.CI' AS media_name, 'Numérique' AS media_type
) AS catalog
INNER JOIN `press_companies` AS company
  ON company.`name` = catalog.`company_name`;

-- Empêche Laravel de tenter de recréer ces tables lors d'un futur accès au terminal.
SET @unjci_press_media_migration = '2026_08_11_150000_create_press_media_tables';
SET @unjci_press_media_batch = (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);
INSERT INTO `migrations` (`migration`, `batch`)
SELECT @unjci_press_media_migration, @unjci_press_media_batch
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` WHERE BINARY `migration` = BINARY @unjci_press_media_migration
);

COMMIT;

SELECT COUNT(*) AS `entreprises_chargees` FROM `press_companies`;
SELECT COUNT(*) AS `medias_charges` FROM `press_media`;
