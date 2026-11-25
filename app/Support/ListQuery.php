<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Applies the standard list-endpoint query string to an Eloquent query.
 *
 * Supported parameters (all optional):
 *   search=foo                 case-insensitive match across the searchable columns
 *   sort=name | sort=-price    sort by a whitelisted column, "-" prefix = descending
 *   filter[status]=active      exact match; comma-separated values become WHERE IN
 *   page=2&per_page=25         pagination (per_page capped at MAX_PER_PAGE)
 */
class ListQuery
{
    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 100;

    /**
     * @param  list<string>  $searchable  columns matched by ?search=
     * @param  list<string>  $sortable  columns allowed in ?sort=
     * @param  array<int|string, string|Closure>  $filters  allowed ?filter[] keys; a list
     *                                                      entry is an exact-match column, a
     *                                                      key => Closure is a custom filter
     */
    public function __construct(
        protected Request $request,
        protected array $searchable = [],
        protected array $sortable = [],
        protected array $filters = [],
        protected string $defaultSort = '-id',
    ) {}

    public static function for(Request $request): static
    {
        return new static($request);
    }

    public function searchable(array $columns): static
    {
        $this->searchable = $columns;

        return $this;
    }

    public function sortable(array $columns): static
    {
        $this->sortable = $columns;

        return $this;
    }

    public function filters(array $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    public function defaultSort(string $sort): static
    {
        $this->defaultSort = $sort;

        return $this;
    }

    public function paginate(Builder $query): LengthAwarePaginator
    {
        $this->applySearch($query);
        $this->applyFilters($query);
        $this->applySort($query);

        $perPage = (int) $this->request->integer('per_page', self::DEFAULT_PER_PAGE);
        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));

        return $query->paginate($perPage)->withQueryString();
    }

    protected function applySearch(Builder $query): void
    {
        $term = trim((string) $this->request->query('search', ''));

        if ($term === '' || $this->searchable === []) {
            return;
        }

        $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(function (Builder $q) use ($operator, $like) {
            foreach ($this->searchable as $column) {
                if (str_contains($column, '.')) {
                    [$relation, $field] = explode('.', $column, 2);
                    $q->orWhereHas($relation, fn (Builder $r) => $r->where($field, $operator, $like));
                } else {
                    $q->orWhere($column, $operator, $like);
                }
            }
        });
    }

    protected function applyFilters(Builder $query): void
    {
        $input = $this->request->query('filter', []);

        if (! is_array($input)) {
            return;
        }

        foreach ($this->filters as $key => $filter) {
            $name = is_int($key) ? $filter : $key;

            if (! array_key_exists($name, $input) || $input[$name] === '' || $input[$name] === null) {
                continue;
            }

            $value = $input[$name];

            if ($filter instanceof Closure) {
                $filter($query, $value);

                continue;
            }

            $values = is_array($value) ? $value : explode(',', (string) $value);
            $values = array_map(fn ($v) => match ($v) {
                'true' => true,
                'false' => false,
                default => $v,
            }, $values);

            count($values) > 1
                ? $query->whereIn($name, $values)
                : $query->where($name, $values[0]);
        }
    }

    protected function applySort(Builder $query): void
    {
        $sort = (string) $this->request->query('sort', $this->defaultSort);
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $this->sortable, true)) {
            $direction = str_starts_with($this->defaultSort, '-') ? 'desc' : 'asc';
            $column = ltrim($this->defaultSort, '-');
        }

        $query->orderBy($column, $direction);

        if ($column !== 'id') {
            $query->orderBy('id', $direction); // stable ordering across pages
        }
    }
}
