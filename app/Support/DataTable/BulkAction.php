<?php

namespace App\Support\DataTable;

use Illuminate\Support\Str;

/**
 * Immutable bulk action definition. Every fluent call returns a modified clone.
 */
final class BulkAction
{
    private function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly ?string $icon = null,
        public readonly ?string $requiredAbility = null,
        private readonly bool $danger = false,
        private readonly ?string $confirm = null,
        private readonly string $successTemplate = ':count data berhasil diproses.',
    ) {}

    public static function make(string $name, string $label): self
    {
        return new self($name, $label);
    }

    public function icon(string $icon): self
    {
        return $this->with(icon: $icon);
    }

    public function ability(string $ability): self
    {
        return $this->with(requiredAbility: $ability);
    }

    public function danger(?string $confirm = null): self
    {
        return $this->with(danger: true, confirm: $confirm);
    }

    public function successMessage(string $template): self
    {
        return $this->with(successTemplate: $template);
    }

    public function isDanger(): bool
    {
        return $this->danger;
    }

    public function confirmText(): ?string
    {
        return $this->confirm;
    }

    public function handlerMethod(): string
    {
        return 'bulk'.Str::studly($this->name);
    }

    public function successText(int $count): string
    {
        return str_replace(':count', (string) $count, $this->successTemplate);
    }

    private function with(mixed ...$changes): self
    {
        return clone ($this, $changes);
    }
}
