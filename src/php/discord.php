<?php

/**
 * Discord Webhook Sender
 * This class provides methods to send messages and embeds to a Discord channel via a webhook URL.
 */

class DiscordWebhook
{
    private string $webhookUrl;

    public function __construct(string $webhookUrl)
    {
        $this->webhookUrl = $webhookUrl;
    }

    /**
     * Sends a message to the Discord webhook.
     * @param string $message
     * @return bool|string
     */
    public function sendMessage(string $message): string|false
    {
        $payload = json_encode(['content' => $message]);
        $ch = curl_init($this->webhookUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("cURL error: $error");
        }

        curl_close($ch);
        return $response;
    }


    /**
     * Sends an embed message to the Discord webhook.
     * @param array $embed
     * @throws RuntimeException
     * @return bool|string
     */
    public function sendEmbed(array $embed): string|false
    {
        $payload = json_encode(['embeds' => [$embed]]);
        $ch = curl_init($this->webhookUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("cURL error: $error");
        }
        curl_close($ch);
        return $response;
    }

    /**
     * Sends a log message to the disord webhook with a specific log level.
     * @param string $level
     * @param string $message
     * @param array $fields
     * @param bool $embed
     * @throws RuntimeException
     * @return string|false
     */
    public function log(string $level, string $message, array $fields = [], bool $embed = false): string|false
    {
        $meta = match ($level) {
            'success' => ['✅ Success', 0x57F287],
            'warning' => ['⚠️ Warning', 0xFEE75C],
            'error' => ['🔴 Error', 0xED4245],
            default => ['ℹ️ Info', 0x5865F2],
        };
        $emoji = match ($level) {
            'success' => '✅',
            'warning' => '⚠️',
            'error' => '🔴',
            default => 'ℹ️',
        };

        if ($embed) {
            $response = $this->sendEmbed([
                'title' => $meta[0],
                'description' => $message,
                'color' => $meta[1],
                'timestamp' => date('c'),
                'fields' => $fields,
                'footer' => ['text' => 'plrsys Logger', 'icon_url' => 'https://plrsys.xyz/assets/icons/icon-512.png'],
            ]);
        } else {
            $response = $this->sendMessage("$emoji [$level] $message");
        }
        if ($response === false) {
            throw new \RuntimeException("Failed to send log message to Discord.");
        } else {
            return $response;
        }
    }
}
