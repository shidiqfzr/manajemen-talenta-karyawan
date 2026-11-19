<?php

namespace App\Services\Structure;

/**
 * Selisih antara struktur organisasi saat ini dan struktur pada lampiran SK (DOMAIN.md §3.7).
 *
 * Jenis perubahan: unit.add, unit.reactivate, unit.rename, unit.close, position.rename, position.rm_change,
 * seat.add, seat.update, seat.remove, dan seat.move (hanya informasi: pasangan seat.remove dan seat.add).
 */
final readonly class StructureDiff
{
    /**
     * @param  list<array<string, mixed>>  $changes
     * @param  array{before: array<string, mixed>, after: array<string, mixed>}  $totals
     */
    public function __construct(public array $changes, public array $totals) {}

    /** @param array{changes?: list<array<string, mixed>>, totals?: array{before: array<string, mixed>, after: array<string, mixed>}} $data */
    public static function fromArray(array $data): self
    {
        return new self($data['changes'] ?? [], $data['totals'] ?? ['before' => [], 'after' => []]);
    }

    public function isEmpty(): bool
    {
        return $this->changes === [];
    }

    /** @return list<array<string, mixed>> */
    public function ofType(string $type): array
    {
        return array_values(array_filter($this->changes, fn (array $change): bool => $change['type'] === $type));
    }

    /** Sidik perubahan: berubah bila isi selisih berubah, sehingga SK yang kedaluwarsa terdeteksi. */
    public function digest(): string
    {
        return hash('sha256', (string) json_encode(self::canonical($this->changes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @return array{changes: list<array<string, mixed>>, totals: array<string, mixed>} */
    public function toArray(): array
    {
        return ['changes' => $this->changes, 'totals' => $this->totals];
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(self::canonical(...), $value);

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
