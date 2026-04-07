<?php

declare(strict_types=1);

namespace MyAds\Plugins\SupportChat\Services;

use Illuminate\Support\Facades\Schema;

class SupportChatSchema
{
    private array $tableCache = [];

    public function hasTable(string $table): bool
    {
        if (array_key_exists($table, $this->tableCache)) {
            return $this->tableCache[$table];
        }

        try {
            return $this->tableCache[$table] = Schema::hasTable($table);
        } catch (\Throwable) {
            return $this->tableCache[$table] = false;
        }
    }

    public function supportsStorage(): bool
    {
        return $this->hasTable('support_chat_threads')
            && $this->hasTable('support_chat_messages');
    }

    public function missingTables(): array
    {
        return array_values(array_filter([
            $this->hasTable('support_chat_threads') ? null : 'support_chat_threads',
            $this->hasTable('support_chat_messages') ? null : 'support_chat_messages',
        ]));
    }

    public function notice(): ?array
    {
        $missing = $this->missingTables();
        if ($missing === []) {
            return null;
        }

        return [
            'title' => __('support_chat::messages.setup_required_title'),
            'message' => __('support_chat::messages.setup_required_message', [
                'tables' => implode(', ', $missing),
            ]),
            'tables' => $missing,
        ];
    }
}
