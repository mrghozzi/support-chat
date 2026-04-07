<?php

declare(strict_types=1);

namespace MyAds\Plugins\SupportChat;

use App\Models\Option;

class SupportChatSettings
{
    public const OPTION_TYPE = 'plugin_support_chat_settings';
    public const OPTION_NAME = 'support-chat';
    public const DEFAULTS = [
        'channel_mode' => 'local',
        'widget_enabled' => 1,
        'welcome_message' => 'We usually reply within a few minutes.',
        'team_name' => 'MYADS Support',
        'whatsapp_number' => '',
        'whatsapp_prefill' => 'Hello, I need help with MYADS.',
        'messenger_url' => '',
        'visibility_rules' => '',
    ];

    public static function all(): array
    {
        $settings = self::DEFAULTS;

        try {
            $row = Option::query()
                ->where('o_type', self::OPTION_TYPE)
                ->where('name', self::OPTION_NAME)
                ->first();
        } catch (\Throwable) {
            return self::normalize($settings);
        }

        if (!$row) {
            return self::normalize($settings);
        }

        $decoded = json_decode((string) $row->o_valuer, true);
        if (!is_array($decoded)) {
            return self::normalize($settings);
        }

        return self::normalize(array_merge($settings, $decoded));
    }

    public static function get(string $key, mixed $fallback = null): mixed
    {
        $settings = self::all();

        return $settings[$key] ?? $fallback;
    }

    public static function save(array $values): void
    {
        $normalized = self::normalizeIncoming($values);

        Option::query()->updateOrCreate(
            ['o_type' => self::OPTION_TYPE, 'name' => self::OPTION_NAME],
            [
                'o_valuer' => json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'o_parent' => 0,
                'o_order' => 0,
                'o_mode' => '',
            ]
        );
    }

    public static function normalizeIncoming(array $values): array
    {
        return self::normalize([
            'channel_mode' => (string) ($values['channel_mode'] ?? self::DEFAULTS['channel_mode']),
            'widget_enabled' => !empty($values['widget_enabled']) ? 1 : 0,
            'welcome_message' => trim((string) ($values['welcome_message'] ?? self::DEFAULTS['welcome_message'])),
            'team_name' => trim((string) ($values['team_name'] ?? self::DEFAULTS['team_name'])),
            'whatsapp_number' => preg_replace('/\D+/', '', (string) ($values['whatsapp_number'] ?? '')) ?? '',
            'whatsapp_prefill' => trim((string) ($values['whatsapp_prefill'] ?? self::DEFAULTS['whatsapp_prefill'])),
            'messenger_url' => trim((string) ($values['messenger_url'] ?? '')),
            'visibility_rules' => self::sanitizeVisibilityRules((string) ($values['visibility_rules'] ?? '')),
        ]);
    }

    public static function visibilityRules(): array
    {
        $lines = preg_split('/[\r\n]+/', (string) self::get('visibility_rules', '')) ?: [];

        return array_values(array_unique(array_filter(array_map(
            static fn (string $line): string => trim($line),
            $lines
        ))));
    }

    private static function normalize(array $settings): array
    {
        $channelMode = in_array(($settings['channel_mode'] ?? 'local'), ['local', 'whatsapp', 'messenger'], true)
            ? (string) $settings['channel_mode']
            : 'local';

        return [
            'channel_mode' => $channelMode,
            'widget_enabled' => !empty($settings['widget_enabled']) ? 1 : 0,
            'welcome_message' => trim((string) ($settings['welcome_message'] ?? self::DEFAULTS['welcome_message'])),
            'team_name' => trim((string) ($settings['team_name'] ?? self::DEFAULTS['team_name'])),
            'whatsapp_number' => preg_replace('/\D+/', '', (string) ($settings['whatsapp_number'] ?? '')) ?? '',
            'whatsapp_prefill' => trim((string) ($settings['whatsapp_prefill'] ?? self::DEFAULTS['whatsapp_prefill'])),
            'messenger_url' => trim((string) ($settings['messenger_url'] ?? '')),
            'visibility_rules' => self::sanitizeVisibilityRules((string) ($settings['visibility_rules'] ?? '')),
        ];
    }

    private static function sanitizeVisibilityRules(string $rules): string
    {
        $lines = preg_split('/[\r\n]+/', $rules) ?: [];
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (string $line): string => trim($line),
            $lines
        ))));

        return implode(PHP_EOL, $normalized);
    }
}
