<?php

namespace App\Console\Commands;

use Google\Client;
use Google\Service\Calendar;
use Illuminate\Console\Command;

class GoogleCalendarAuth extends Command
{
    protected $signature = 'google:calendar-auth';

    protected $description = 'Autentica Google Calendar e genera token.json con refresh token';

    public function handle(): int
    {
        $credentialsPath = storage_path('app/credentials.json');
        $tokenPath = storage_path('app/token.json');

        if (! file_exists($credentialsPath)) {
            $this->error('File credentials.json non trovato in storage/app/credentials.json');
            return self::FAILURE;
        }

        $client = new Client();
        $client->setApplicationName(config('app.name'));
        $client->setScopes([Calendar::CALENDAR]);
        $client->setAuthConfig($credentialsPath);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $authUrl = $client->createAuthUrl();

        $this->info('Apri questo link nel browser e autorizza l’account Google:');
        $this->newLine();
        $this->line($authUrl);
        $this->newLine();

        $code = $this->ask('Incolla qui il codice di autorizzazione');

        $accessToken = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($accessToken['error'])) {
            $this->error('Errore durante il recupero del token.');
            $this->line(json_encode($accessToken, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::FAILURE;
        }

        file_put_contents(
            $tokenPath,
            json_encode($accessToken, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->info("Token salvato correttamente in {$tokenPath}");

        if (empty($accessToken['refresh_token'])) {
            $this->warn('Attenzione: refresh_token mancante. Controlla OAuth consent screen e ripeti il consenso.');
        } else {
            $this->info('Refresh token presente: il sistema potrà rigenerare automaticamente l’access token.');
        }

        return self::SUCCESS;
    }
}