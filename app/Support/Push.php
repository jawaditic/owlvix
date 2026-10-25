<?php

namespace App\Support;

use App\Models\AdminToken;
use Illuminate\Support\Facades\Http;

/** Phone notifications for the Owlvix admin app, sent through Expo's push service. */
class Push
{
    public static function toAdmins(string $title, string $body, array $data = []): void
    {
        try {
            $tokens = AdminToken::whereNotNull('push_token')->pluck('push_token')->unique()->values();
        } catch (\Throwable $e) {
            return; // table not migrated yet
        }
        if ($tokens->isEmpty()) {
            return;
        }

        $messages = $tokens->map(fn ($to) => [
            'to' => $to,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'sound' => 'default',
            'priority' => 'high',
            'channelId' => 'enquiries',
        ])->all();

        try {
            $result = Http::timeout(5)->acceptJson()->post('https://exp.host/--/api/v2/push/send', $messages)->json('data') ?? [];

            // Forget phones where the app was removed, so we stop sending to them
            foreach ($result as $i => $ticket) {
                if (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered') {
                    AdminToken::where('push_token', $messages[$i]['to'])->update(['push_token' => null]);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
