<?php

namespace App\Support\DataTable;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Immutable table column definition. Every fluent call returns a modified clone.
 */
final class Column
{
    private const ALIGNMENTS = ['left', 'center', 'right'];

    private function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $alignment = 'left',
        private readonly bool $sortable = false,
        private readonly ?Closure $sortUsing = null,
        private readonly bool $searchable = false,
        private readonly ?Closure $searchUsing = null,
        private readonly bool $mono = false,
        private readonly bool $exportable = false,
        private readonly ?string $exportHeading = null,
        private readonly ?Closure $exportValue = null,
        private readonly bool $exportOnly = false,
        public readonly ?string $width = null,
    ) {}

    public static function make(string $key, string $label): self
    {
        return new self($key, $label);
    }

    /** @param  ?Closure(Builder, string): mixed  $using  fn($q, $direction) */
    public function sortable(?Closure $using = null): self
    {
        return $this->with(sortable: true, sortUsing: $using);
    }

    /** @param  ?Closure(Builder, string): mixed  $using  fn($q, $term) */
    public function searchable(?Closure $using = null): self
    {
        return $this->with(searchable: true, searchUsing: $using);
    }

    public function align(string $align): self
    {
        if (! in_array($align, self::ALIGNMENTS, true)) {
            throw new InvalidArgumentException("Invalid alignment [{$align}]; expected left, center or right.");
        }

        return $this->with(alignment: $align);
    }

    /** Developer-supplied class string added to this column's header cell (e.g. `w-[18%]`). */
    public function width(string $classes): self
    {
        return $this->with(width: $classes);
    }

    public function mono(): self
    {
        return $this->with(mono: true);
    }

    /** @param  ?Closure(Model): mixed  $value */
    public function exportable(?string $heading = null, ?Closure $value = null): self
    {
        return $this->with(exportable: true, exportHeading: $heading, exportValue: $value);
    }

    /** Column appears in exports only, never in the on-screen table. */
    public function exportOnly(): self
    {
        return $this->with(exportable: true, exportOnly: true);
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function isExportable(): bool
    {
        return $this->exportable;
    }

    public function isExportOnly(): bool
    {
        return $this->exportOnly;
    }

    public function isMono(): bool
    {
        return $this->mono;
    }

    public function sortUsing(): ?Closure
    {
        return $this->sortUsing;
    }

    public function searchUsing(): ?Closure
    {
        return $this->searchUsing;
    }

    public function exportHeading(): string
    {
        return $this->exportHeading ?? $this->label;
    }

    public function exportValue(Model $row): mixed
    {
        return $this->exportValue ? ($this->exportValue)($row) : data_get($row, $this->key);
    }

    private function with(mixed ...$changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }
}
