<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Google\Client;
use Google\Service\Calendar;

class GoogleAuth extends Command
{
    protected $signature = 'google:auth';
    protected $description = 'Autenticazione Google Calendar';

    public function handle()
    {
        $client = new Client();
        $client->setApplicationName('CRM');
        $client->setScopes([Calendar::CALENDAR]);
        $client->setAuthConfig(storage_path('app/credentials.json'));
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        $authUrl = $client->createAuthUrl();

        $this->info("Apri questo link nel browser:");
        $this->line($authUrl);

        $code = $this->ask('Inserisci il codice di autorizzazione');

        $accessToken = $client->fetchAccessTokenWithAuthCode($code);

        file_put_contents(
            storage_path('app/token.json'),
            json_encode($accessToken)
        );

        $this->info('Token salvato correttamente!');
    }
}