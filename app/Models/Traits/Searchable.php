<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

trait Searchable
{
    /**
     * Scope a query to search across specified columns with SQL LIKE character escaping.
     */
    public function scopeSearch(Builder $query, ?string $term, array $customColumns = []): Builder
    {
        if (empty($term) || trim($term) === '') {
            return $query;
        }

        $cleanTerm = trim($term);
        // Escape SQL LIKE wildcard special characters to prevent LIKE injection
        $escapedTerm = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $cleanTerm);
        $columns = ! empty($customColumns) ? $customColumns : ($this->searchable ?? []);

        if (empty($columns)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($columns, $escapedTerm) {
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    $q->where($column, 'like', '%'.$escapedTerm.'%');
                } else {
                    $q->orWhere($column, 'like', '%'.$escapedTerm.'%');
                }
            }
        });
    }
}
