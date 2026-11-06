<?php

namespace App\Support\DataTable;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Immutable filter definition. Every fluent call returns a modified clone.
 */
final class Filter
{
    private function __construct(
        public readonly string $property,
        public readonly string $label,
        public readonly string $type,
        private readonly array $options = [],
        public readonly ?string $icon = null,
        public readonly ?string $allLabel = null,
        private readonly ?Closure $apply = null,
        private readonly ?string $column = null,
    ) {}

    /** @param  array<string, string>  $options  [value => label] */
    public static function select(string $property, string $label, array $options): self
    {
        return new self($property, $label, 'select', $options);
    }

    public static function boolean(string $property, string $label): self
    {
        return new self($property, $label, 'boolean', ['1' => $label]);
    }

    /** Lower bound of a day range on `$column`: includes the whole given day (from 00:00:00). */
    public static function dateFrom(string $property, string $label, string $column): self
    {
        return new self($property, $label, 'date_from', column: $column);
    }

    /** Upper bound of a day range on `$column`: includes the whole given day (until 23:59:59). */
    public static function dateTo(string $property, string $label, string $column): self
    {
        return new self($property, $label, 'date_to', column: $column);
    }

    public function icon(string $icon): self
    {
        return $this->with(icon: $icon);
    }

    public function allLabel(string $allLabel): self
    {
        return $this->with(allLabel: $allLabel);
    }

    /** @param  Closure(Builder, string): mixed  $fn */
    public function apply(Closure $fn): self
    {
        return $this->with(apply: $fn);
    }

    /** @return array<string, string> */
    public function options(): array
    {
        return $this->options;
    }

    /** Empty values mean "no filter"; anything else must be a known option key. */
    public function allows(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if ($this->isDate()) {
            return is_string($value) && $this->isRealDate($value);
        }

        if (! is_string($value) && ! is_int($value)) {
            return false;
        }

        return in_array((string) $value, array_map('strval', array_keys($this->options)), true);
    }

    public function applyTo(Builder $query, string $value): void
    {
        if ($this->apply) {
            ($this->apply)($query, $value);

            return;
        }

        if ($this->type === 'date_from') {
            $query->where($this->column, '>=', $value.' 00:00:00');

            return;
        }

        if ($this->type === 'date_to') {
            $query->where($this->column, '<=', $value.' 23:59:59');

            return;
        }

        $query->where($this->property, $value);
    }

    private function isDate(): bool
    {
        return $this->type === 'date_from' || $this->type === 'date_to';
    }

    /** Strict Y-m-d that is also a real calendar day (rejects 2026-02-30). */
    private function isRealDate(string $value): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) !== 1) {
            return false;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }

    private function with(mixed ...$changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }
}
