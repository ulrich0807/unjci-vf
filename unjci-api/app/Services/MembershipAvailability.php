<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class MembershipAvailability
{
    private const PATH = 'settings/membership.json';

    public function isClosed(): bool
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::PATH)) {
            return (bool) config('membership.requests_closed', true);
        }

        $settings = json_decode($disk->get(self::PATH), true);

        return is_bool($settings['requests_closed'] ?? null) ? $settings['requests_closed'] : true;
    }

    public function status(): array
    {
        return [
            'requests_closed' => $this->isClosed(),
            'closure_message' => config('membership.closure_message'),
        ];
    }

    public function setClosed(bool $closed): array
    {
        // Ce fichier privé est conservé par les déploiements. Le remplacement
        // atomique évite qu'une requête lise un réglage partiellement écrit.
        $path = Storage::disk('local')->path(self::PATH);
        File::ensureDirectoryExists(dirname($path));
        File::replace($path, json_encode(['requests_closed' => $closed], JSON_THROW_ON_ERROR), 0600);

        return $this->status();
    }
}
