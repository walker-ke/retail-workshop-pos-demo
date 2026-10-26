<?php
namespace App\Support;

use Illuminate\Database\Query\Builder;

class HistoryFilters
{
    public static function apply(Builder $query, string $table, array $columns): int
    {
        $data = request()->validate(['q'=>'nullable|string|max:100', 'per_page'=>'nullable|integer|in:10,25,50,100']);
        $term = trim($data['q'] ?? '');
        if ($term !== '') {
            // Group the alternatives so searching never bypasses the shop restriction.
            $query->where(function ($q) use ($term, $table, $columns) {
                foreach ($columns as $column) $q->orWhere($table.'.'.$column, 'like', '%'.$term.'%');
            });
        }
        return (int) ($data['per_page'] ?? 50);
    }
}
