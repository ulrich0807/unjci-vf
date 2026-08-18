# =============================================================================
# UNJCI — Script de construction du dossier de déploiement
# =============================================================================
# Ce script reconstruit entièrement le dossier deploiement-complet-sans-terminal
# à partir des sources (Angular build + Laravel API).
#
# Usage : exécuter depuis la racine du projet (c:\xampp\htdocs\unjci-vf)
# =============================================================================

$ErrorActionPreference = "Stop"

$projectRoot  = "c:\xampp\htdocs\unjci-vf"
$deployDir    = "$projectRoot\deploiement-complet-sans-terminal"
$angularDist  = "$projectRoot\unjci-front\dist\unjci-membership\browser"
$laravelSrc   = "$projectRoot\unjci-api"

Write-Host ""
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host "  UNJCI — Construction du dossier de deploiement" -ForegroundColor Cyan
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host ""

# ── 1. Vérifications ──
if (-not (Test-Path $angularDist)) {
    Write-Host "[ERREUR] Le build Angular n'existe pas : $angularDist" -ForegroundColor Red
    Write-Host "         Lancez d'abord : cd unjci-front; npx ng build --configuration production" -ForegroundColor Yellow
    exit 1
}
if (-not (Test-Path "$laravelSrc\vendor\autoload.php")) {
    Write-Host "[ERREUR] vendor/ Laravel manquant. Lancez : cd unjci-api; composer install --no-dev" -ForegroundColor Red
    exit 1
}

# ── 2. Suppression de l'ancien dossier ──
if (Test-Path $deployDir) {
    Write-Host "[1/6] Suppression de l'ancien dossier..." -ForegroundColor Yellow
    Remove-Item -Recurse -Force $deployDir
}

Write-Host "[2/6] Creation du nouveau dossier..." -ForegroundColor Green
New-Item -ItemType Directory -Path $deployDir | Out-Null

# ── 3. Copie du build Angular (racine) ──
Write-Host "[3/6] Copie du build Angular..." -ForegroundColor Green
# Copier tous les fichiers du build Angular à la racine du dossier de déploiement
Get-ChildItem -Path $angularDist -File | ForEach-Object {
    Copy-Item $_.FullName -Destination $deployDir
}
# Copier les sous-dossiers (assets, etc.) sauf le dossier api et unjci-api s'il y en avait
Get-ChildItem -Path $angularDist -Directory | Where-Object { $_.Name -notin @("api", "unjci-api") } | ForEach-Object {
    Copy-Item $_.FullName -Destination "$deployDir\$($_.Name)" -Recurse
}

# ── 4. Copie de l'API Laravel (sans .env, sans bootstrap/cache/*.php) ──
Write-Host "[4/6] Copie du backend Laravel..." -ForegroundColor Green
$excludeFiles = @(".env", ".phpunit.result.cache")
$excludeDirs  = @("node_modules", ".git", "tests")

# Utiliser robocopy pour copier efficacement
robocopy "$laravelSrc" "$deployDir\unjci-api" /E /NFL /NDL /NJH /NJS /NP `
    /XF $excludeFiles `
    /XD ($excludeDirs | ForEach-Object { "$laravelSrc\$_" }) | Out-Null

# Supprimer les fichiers de cache bootstrap (ils sont régénérés automatiquement par Laravel)
$cacheFiles = @(
    "$deployDir\unjci-api\bootstrap\cache\services.php",
    "$deployDir\unjci-api\bootstrap\cache\packages.php"
)
foreach ($f in $cacheFiles) {
    if (Test-Path $f) {
        Remove-Item $f -Force
        Write-Host "   Supprime : bootstrap\cache\$(Split-Path $f -Leaf) (regenere automatiquement)" -ForegroundColor DarkGray
    }
}

# Créer le .htaccess de sécurité pour bloquer l'accès direct à unjci-api
Set-Content -Path "$deployDir\unjci-api\.htaccess" -Value "Require all denied`n" -NoNewline

# S'assurer que le .gitignore du cache permet la recréation
$cacheGitignore = "$deployDir\unjci-api\bootstrap\cache\.gitignore"
if (-not (Test-Path $cacheGitignore)) {
    Set-Content -Path $cacheGitignore -Value "*`n!.gitignore`n"
}

# ── 5. Création du proxy API ──
Write-Host "[5/6] Creation du proxy API..." -ForegroundColor Green
New-Item -ItemType Directory -Path "$deployDir\api" -Force | Out-Null

# index.php — point d'entrée Laravel via le dossier /api
$apiIndexPhp = @'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../unjci-api/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../unjci-api/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../unjci-api/bootstrap/app.php';

$app->handleRequest(Request::capture());
'@
Set-Content -Path "$deployDir\api\index.php" -Value $apiIndexPhp

# .htaccess du proxy API
$apiHtaccess = @'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Handle X-XSRF-Token Header
    RewriteCond %{HTTP:x-xsrf-token} .
    RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
'@
Set-Content -Path "$deployDir\api\.htaccess" -Value $apiHtaccess

# Créer l'arborescence storage pour les uploads (dossiers vides)
$storageDirs = @(
    "$deployDir\api\storage",
    "$deployDir\api\storage\members\photos",
    "$deployDir\api\storage\members\press_cards_recto",
    "$deployDir\api\storage\members\press_cards_verso",
    "$deployDir\api\storage\members\old_member_cards"
)
foreach ($d in $storageDirs) {
    New-Item -ItemType Directory -Path $d -Force | Out-Null
}

# ── 6. .htaccess racine avec cache-busting ──
Write-Host "[6/6] Ecriture du .htaccess racine avec anti-cache..." -ForegroundColor Green
$rootHtaccess = @'
Options -Indexes

<IfModule mod_headers.c>
    # ── index.html : JAMAIS mis en cache ──
    # Après chaque rebuild Angular, les noms de fichiers JS/CSS changent.
    # Le navigateur doit toujours recharger index.html pour récupérer
    # les nouvelles références. Sans cette règle, l'ancien index.html reste
    # en cache et pointe vers des fichiers JS/CSS qui n'existent plus.
    <FilesMatch "^index\.html$">
        Header set Cache-Control "no-cache, no-store, must-revalidate"
        Header set Pragma "no-cache"
        Header set Expires "0"
    </FilesMatch>

    # ── Fichiers JS/CSS avec hash : cache longue durée (1 an) ──
    # Angular génère des noms uniques (ex: main-KWTWGUB7.js).
    # Quand le code change, le hash change automatiquement.
    <FilesMatch "\.(js|css)$">
        Header set Cache-Control "public, max-age=31536000, immutable"
    </FilesMatch>

    # ── Images et polices : cache 30 jours ──
    <FilesMatch "\.(ico|jpg|jpeg|png|gif|svg|webp|woff2?)$">
        Header set Cache-Control "public, max-age=2592000"
    </FilesMatch>
</IfModule>

<IfModule mod_rewrite.c>
    RewriteEngine On

    # L'API Laravel conserve ses propres règles de réécriture sous /api.
    RewriteRule ^api(?:/|$) - [L]

    # Le code Laravel et son fichier .env ne sont jamais accessibles directement.
    RewriteRule ^unjci-api(?:/|$) - [F,L]

    # Routage Angular à la racine, y compris lors d'un accès direct à une page.
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.html [L]
</IfModule>
'@
Set-Content -Path "$deployDir\.htaccess" -Value $rootHtaccess

# ── 7. Documentation ──
$deployDoc = @'
PAQUET COMPLET UNJCI — MISE À JOUR SANS TERMINAL

Ce paquet remplace l'ancien deploy-root pour cette mise à jour.

Avant toute manipulation, lire intégralement :
DEPLOIEMENT-SANS-TERMINAL.txt

Ne jamais vider public_html. Les fichiers doivent être fusionnés avec ceux du
serveur en préservant obligatoirement .env et les dossiers storage existants.
'@
Set-Content -Path "$deployDir\DEPLOIEMENT-RACINE.txt" -Value $deployDoc

# Copier la doc de déploiement complète si elle existe
$docSource = "$projectRoot\DEPLOIEMENT-SANS-TERMINAL.txt"
if (Test-Path $docSource) {
    Copy-Item $docSource -Destination "$deployDir\DEPLOIEMENT-SANS-TERMINAL.txt"
}

# ── Résumé ──
Write-Host ""
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host "  DEPLOIEMENT PRET !" -ForegroundColor Green
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host ""

# Lister les fichiers à la racine
Write-Host "Contenu de la racine :" -ForegroundColor White
Get-ChildItem $deployDir | ForEach-Object {
    $icon = if ($_.PSIsContainer) { "[DIR]" } else { "     " }
    $size = if (-not $_.PSIsContainer) { "({0:N0} Ko)" -f ($_.Length / 1KB) } else { "" }
    Write-Host "  $icon $($_.Name) $size"
}

Write-Host ""
Write-Host "Ameliorations appliquees :" -ForegroundColor Yellow
Write-Host "  [OK] index.html : no-cache (les utilisateurs verront toujours la derniere version)"
Write-Host "  [OK] JS/CSS : cache 1 an (grace aux hashes Angular, pas de conflit)"
Write-Host "  [OK] Images : cache 30 jours"
Write-Host "  [OK] bootstrap/cache/*.php : exclus (regeneres automatiquement sur le serveur)"
Write-Host "  [OK] .env : exclu (preserve les credentials du serveur)"
Write-Host ""
Write-Host "Uploadez le CONTENU de ce dossier dans public_html/ en mode fusion." -ForegroundColor Cyan
Write-Host ""
