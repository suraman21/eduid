<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\TelegramService;
use Illuminate\Http\Request;

/**
 * Telegram bot webhook — its ONLY job is linking guardians.
 *
 * A parent taps the school-provided deep link t.me/<bot>?start=<token>;
 * Telegram delivers "/start <token>" here; the HMAC-signed token resolves
 * to the guardian and we store the chat id. Every other message gets a
 * polite pointer back to the school.
 *
 * Authenticity: Telegram echoes the secret we registered with setWebhook
 * in the X-Telegram-Bot-Api-Secret-Token header — anything else is dropped.
 */
class TelegramWebhookController extends Controller
{
    public function __construct(private TelegramService $telegram)
    {
    }

    public function handle(Request $request)
    {
        $secret = config('services.telegram.webhook_secret');

        if (blank($secret)
            || ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))
        ) {
            // Always 200 to Telegram-shaped probes — never confirm/deny.
            return response()->json(['ok' => true]);
        }

        $message = $request->input('message');
        $chatId  = $message['chat']['id'] ?? null;
        $text    = trim((string) ($message['text'] ?? ''));

        if (! $chatId || $text === '') {
            return response()->json(['ok' => true]);
        }

        if (str_starts_with($text, '/start ')) {
            $guardian = $this->telegram->guardianFromToken(substr($text, 7));

            if ($guardian) {
                $guardian->update(['telegram_chat_id' => (string) $chatId]);

                AuditLog::safeRecord(
                    $guardian->school_id, null,
                    'guardian.telegram_linked', 'guardians', $guardian->id,
                    null, ['chat_id' => (string) $chatId]
                );

                $this->telegram->send((string) $chatId,
                    "✅ You're connected, {$guardian->full_name}! You'll now receive an alert here whenever your child is marked absent.");

                return response()->json(['ok' => true]);
            }
        }

        $this->telegram->send((string) $chatId,
            'To connect, please use the personal link your school gave you. Contact the school office if you need a new one.');

        return response()->json(['ok' => true]);
    }
}
