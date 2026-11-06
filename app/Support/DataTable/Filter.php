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

        $query->where($this->property, $value);
    }

    private function with(mixed ...$changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }
}
