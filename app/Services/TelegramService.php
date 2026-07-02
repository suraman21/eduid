<?php

namespace App\Services;

use App\Models\Guardian;
use Illuminate\Support\Facades\Http;

/**
 * Thin Telegram Bot API client + guardian deep-link tokens.
 *
 * One platform bot (Arkeon's) serves every school. A guardian links by
 * opening t.me/<bot>?start=<token>; the webhook resolves the HMAC-signed
 * token back to the guardian and stores their chat id. Without
 * TELEGRAM_BOT_TOKEN configured, everything degrades gracefully: links
 * aren't offered and outbox rows simply wait.
 */
class TelegramService
{
    public function isConfigured(): bool
    {
        return filled(config('services.telegram.bot_token'));
    }

    public function botUsername(): ?string
    {
        return config('services.telegram.bot_username') ?: null;
    }

    /**
     * Deep-link start token for a guardian: "g{id}.{sig}". Signed with the
     * app key so a forged id can never link someone else's chat.
     */
    public function linkToken(Guardian $guardian): string
    {
        return 'g' . $guardian->id . '.' . $this->signature($guardian->id);
    }

    /** t.me deep link the school shares with the parent (null if no bot). */
    public function deepLink(Guardian $guardian): ?string
    {
        $bot = $this->botUsername();

        return $bot ? "https://t.me/{$bot}?start=" . $this->linkToken($guardian) : null;
    }

    /** Resolve a /start token back to its guardian (null = invalid/forged). */
    public function guardianFromToken(string $token): ?Guardian
    {
        if (! preg_match('/^g(\d+)\.([0-9a-f]{16})$/', trim($token), $m)) {
            return null;
        }

        if (! hash_equals($this->signature((int) $m[1]), $m[2])) {
            return null;
        }

        return Guardian::withoutGlobalScope('school')->find((int) $m[1]);
    }

    private function signature(int $guardianId): string
    {
        return substr(hash_hmac('sha256', 'guardian-link|' . $guardianId, config('app.key')), 0, 16);
    }

    /**
     * Send one message. Returns null on success, an error string on failure.
     * Short timeout — this may run inline in a web request.
     */
    public function send(string $chatId, string $text): ?string
    {
        if (! $this->isConfigured()) {
            return 'Telegram bot not configured.';
        }

        try {
            $response = Http::timeout(5)->connectTimeout(3)->post(
                'https://api.telegram.org/bot' . config('services.telegram.bot_token') . '/sendMessage',
                ['chat_id' => $chatId, 'text' => $text]
            );

            if ($response->successful() && $response->json('ok') === true) {
                return null;
            }

            return 'Telegram error: ' . ($response->json('description') ?? $response->status());
        } catch (\Throwable $e) {
            return 'Network error: ' . $e->getMessage();
        }
    }
}
