<?php

namespace App\Console\Commands;

use App\Mail\WhatsAppRemindersMail;
use App\Models\Appuntamento;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendWhatsAppReminderLinks extends Command
{
    protected $signature = 'reminders:whatsapp-links';
    protected $description = 'Invia una email con tutti i link WhatsApp per gli appuntamenti di domani';

    public function handle(): int
    {
        $inizio = Carbon::tomorrow()->startOfDay();
        $fine = Carbon::tomorrow()->endOfDay();

        $appuntamenti = Appuntamento::with(['cliente', 'abbonamento.servizio', 'pt'])
            ->whereBetween('data_ora', [$inizio, $fine])
            ->whereNull('whatsapp_reminder_email_sent_at')
            ->get()
            ->filter(fn ($a) => ! empty($a->cliente?->telefono))
            ->map(function ($appuntamento) {
                $telefono = preg_replace('/\D+/', '', $appuntamento->cliente->telefono);

                if (Str::startsWith($telefono, '0')) {
                    $telefono = '39' . ltrim($telefono, '0');
                }

                $totale = $appuntamento->abbonamento?->servizio?->incontri ?? 0;
                $pt = $appuntamento->pt?->name ?? 'Andrea';

                $messaggio = "Ciao {$appuntamento->cliente->nome}, ti ricordo l'appuntamento di domani alle {$appuntamento->data_ora->format('H:i')} con PT {$pt}. Lezione {$appuntamento->numerazione}/{$totale}.";

                $appuntamento->whatsapp_link = 'https://wa.me/' . $telefono . '?text=' . urlencode($messaggio);

                return $appuntamento;
            });

        if ($appuntamenti->isEmpty()) {
            $this->info('Nessun appuntamento da inviare.');
            return self::SUCCESS;
        }

        Mail::to(config('mail.reminder_to'))->send(
            new WhatsAppRemindersMail($appuntamenti)
        );

        Appuntamento::whereIn('id', $appuntamenti->pluck('id'))
            ->update(['whatsapp_reminder_email_sent_at' => now()]);

        $this->info('Email inviata correttamente.');

        return self::SUCCESS;
    }
}