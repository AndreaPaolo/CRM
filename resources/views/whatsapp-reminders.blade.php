<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Promemoria WhatsApp</title>
</head>
<body>
    <h2>Messaggi WhatsApp da inviare per domani</h2>

    @if($appuntamenti->isEmpty())
        <p>Nessun appuntamento trovato.</p>
    @else
        <ul>
            @foreach($appuntamenti as $appuntamento)
                <li style="margin-bottom: 16px;">
                    <strong>
                        {{ $appuntamento->cliente->nome }} {{ $appuntamento->cliente->cognome }}
                    </strong><br>

                    Data: {{ $appuntamento->data_ora->format('d/m/Y H:i') }}<br>
                    PT: {{ $appuntamento->pt?->name ?? '-' }}<br>

                    <a href="{{ $appuntamento->whatsapp_link }}" target="_blank">
                        Apri WhatsApp
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</body>
</html>