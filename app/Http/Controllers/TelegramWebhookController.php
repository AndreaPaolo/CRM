<?php

namespace App\Http\Controllers;

use App\Models\TelegramUpdate;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramCommandService;
use App\Services\Telegram\TelegramSecurityService;
use App\Support\Telegram\TelegramMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $secret,
        TelegramSecurityService $security,
        TelegramBotService $bot,
        TelegramCommandService $commands,
    ): JsonResponse {
        if (! $security->isEnabled()) {
            return response()->json(['ok' => false, 'message' => 'Telegram disabled'], 403);
        }

        if (! $security->secretIsValid($secret)) {
            return response()->json(['ok' => false, 'message' => 'Invalid secret'], 403);
        }

        $payload = $request->all();

        $messagePayload = $payload['message'] ?? $payload['edited_message'] ?? null;

        $message = new TelegramMessage(
            updateId: $payload['update_id'] ?? null,
            chatId: isset($messagePayload['chat']['id']) ? (string) $messagePayload['chat']['id'] : null,
            userId: isset($messagePayload['from']['id']) ? (string) $messagePayload['from']['id'] : null,
            messageId: isset($messagePayload['message_id']) ? (string) $messagePayload['message_id'] : null,
            text: $messagePayload['text'] ?? null,
            payload: $payload,
        );

        TelegramUpdate::create([
            'telegram_update_id' => $message->updateId,
            'direction' => 'inbound',
            'kind' => 'message',
            'chat_id' => $message->chatId,
            'telegram_user_id' => $message->userId,
            'telegram_message_id' => $message->messageId,
            'text' => $message->text,
            'payload' => $payload,
            'success' => true,
            'handled_at' => now(),
        ]);

        if (! $security->userIsAllowed($message->userId)) {
            if ($message->chatId) {
                $bot->sendMessage($message->chatId, '⛔ Utente non autorizzato.');
            }

            return response()->json(['ok' => true]);
        }

        if ($message->chatId) {
            $reply = $commands->buildReply($message);
            $bot->sendMessage($message->chatId, $reply);
        }

        return response()->json(['ok' => true]);
    }
}