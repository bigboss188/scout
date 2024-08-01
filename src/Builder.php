<?php

declare(strict_types=1);
/**
 * This file is part of hyperf-ext/scout.
 *
 * @link     https://github.com/hyperf-ext/scout
 * @contact  eric@zhu.email
 * @license  https://github.com/hyperf-ext/scout/blob/master/LICENSE
 */
namespace HyperfExt\Scout;

use Hyperf\Collection\Collection;
use Hyperf\Contract\LengthAwarePaginatorInterface;
use Hyperf\Database\Model\Model;
use Hyperf\Macroable\Macroable;
use Hyperf\Paginator\LengthAwarePaginator;
use Hyperf\Paginator\Paginator;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\ExistsQuery;
use ONGR\ElasticsearchDSL\Search;
use ONGR\ElasticsearchDSL\Sort\FieldSort;
use function Hyperf\Tappable\tap;
/**
 * @mixin QueryBuilder
 */
class Builder
{
    use Macroable {
        Macroable::__call as macroCall;
    }

    /**
     * The model instance.
     */
    public Model $model;

    /**
     * The query expression.
     */
    public ?string $query;

    /**
     * Optional callback before search execution.
     */
    public string|\Closure|null $callback;

    /**
     * Optional callback before model query execution.
     *
     * @var null|\Closure
     */
    public ?\Closure $queryCallback;

    /**
     * The custom index specified for the search.
     *
     * @var string
     */
    public ?string $index = null;

    /**
     * @var array
     */
    public array $wheres = [];

    /**
     * @var array
     */
    public array $raw = [];

    /**
     * The with parameter.
     *
     * @var array
     */
    protected array $with = [];

    /**
     * @var \ONGR\ElasticsearchDSL\Search
     */
    protected Search $search;

    /**
     * @var QueryBuilder
     */
    protected QueryBuilder $queryBuilder;

    /**
     * Create a new search builder instance.
     */
    public function __construct(Model $model, ?string $query, ?\Closure $callback = null, bool $softDelete = false)
    {
        $this->model = $model;
        $this->query = $query;
        $this->callback = $callback;

        $this->search = new Search();

        $this->queryBuilder = new QueryBuilder($this->search);

        if (! empty($query)) {
            $this->queryBuilder->mustWhereQueryString($query);
        }

        if ($softDelete) {
            $this->wheres['__soft_deleted'] = 0;
        }
    }

    public function __call(string $method, array $parameters)
    {
        try {
            call_user_func_array([$this->queryBuilder, $method], $parameters);
        } catch (\BadMethodCallException $e) {
            return $this->macroCall($method, $parameters);
        }
        return $this;
    }

    public function toArray(): array
    {
        if (empty($this->raw)) {
            if (isset($this->wheres['__soft_deleted'])) {
                $search = clone $this->search;
                $search->addQuery(
                    new ExistsQuery($this->model->getDeletedAtColumn()),
                    $this->wheres['__soft_deleted'] === 0 ? BoolQuery::MUST_NOT : BoolQuery::FILTER
                );
                return $search->toArray();
            }

            return $this->search->toArray();
        }

        return $this->raw;
    }

    public function getSearch(): Search
    {
        return $this->search;
    }

    /**
     * @return $this
     */
    public function dsl(callable $callable): static
    {
        $callable($this->search);

        return $this;
    }

    /**
     * @return $this
     */
    public function raw(array $value): static
    {
        $this->raw = $value;

        return $this;
    }

    /**
     * Specify a custom index to perform this search on.
     *
     * @return $this
     */
    public function within(string $index): static
    {
        $this->index = $index;

        return $this;
    }

    /**
     * Include soft deleted records in the results.
     *
     * @return $this
     */
    public function withTrashed(): static
    {
        unset($this->wheres['__soft_deleted']);

        return $this;
    }

    /**
     * Include only soft deleted records in the results.
     *
     * @return $this
     */
    public function onlyTrashed(): static
    {
        return tap($this->withTrashed(), function () {
            $this->wheres['__soft_deleted'] = 1;
        });
    }

    /**
     * Alias to set the "from" value of the query.
     *
     * @see \HyperfExt\Scout\Builder::from()
     * @return $this
     */
    public function skip(int $value): static
    {
        return $this->from($value);
    }

    /**
     * Alias to set the "from" value of the query.
     *
     * @see \HyperfExt\Scout\Builder::from()
     * @return $this
     */
    public function offset(int $value): static
    {
        return $this->from($value);
    }

    /**
     * Set the "from" value of the query.
     *
     * @return $this
     */
    public function from(int $value): static
    {
        $this->search->setFrom(max(0, $value));

        return $this;
    }

    /**
     * Alias to set the "size" for the search query.
     *
     * @see \HyperfExt\Scout\Builder::size()
     * @return $this
     */
    public function take(int $value): static
    {
        return $this->limit($value);
    }

    /**
     * Alias to set the "size" value of the query.
     *
     * @see \HyperfExt\Scout\Builder::size()
     * @return $this
     */
    public function limit(int $value): static
    {
        return $this->size($value);
    }

    /**
     * Set the "size" value of the query.
     *
     * @return $this
     */
    public function size(int $value): static
    {
        if ($value >= 0) {
            $this->search->setSize($value);
        }

        return $this;
    }

    /**
     * Set the "min_score" value of the query.
     *
     * @return $this
     */
    public function minScore(float $value): static
    {
        if ($value >= 0) {
            $this->search->setMinScore($value);
        }

        return $this;
    }

    /**
     * Add an "sort" for the search query.
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-request-sort.html.
     *
     * @param null|string $direction 'asc'|'desc'|null
     * @param array $options nested,missing,unmapped_type,mode(min|max|sum|avg|median)
     *
     * @return $this
     */
    public function orderBy(string $column, ?string $direction = null, array $options = []): static
    {
        $this->search->addSort(new FieldSort($column, $direction, $options));

        return $this;
    }

    /**
     * Apply the callback's query changes if the given "value" is true.
     *
     * @param mixed $value
     * @param callable $callback
     * @param callable|null $default
     * @return mixed
     */
    public function when(mixed $value, callable $callback, ?callable $default = null): mixed
    {
        if ($value) {
            return $callback($this, $value) ?: $this;
        }
        if ($default) {
            return $default($this, $value) ?: $this;
        }

        return $this;
    }

    /**
     * Pass the query to a given callback.
     *
     * @return $this
     */
    public function tap(\Closure $callback): static
    {
        return $this->when(true, $callback);
    }

    /**
     * Set the callback that should have an opportunity to modify the database query.
     *
     * @return $this
     */
    public function query(callable $callback): static
    {
        $this->queryCallback = $callback;

        return $this;
    }

    /**
     * Eager load some relations.
     *
     * @param array|string $relations
     * @return $this
     */
    public function with(array|string $relations): static
    {
        if (is_string($relations)) {
            $this->with[] = $relations;
        } elseif (is_array($relations)) {
            $this->with = array_merge($this->with, $relations);
        }

        return $this;
    }

    /**
     * Get the raw results of the search.
     *
     * @return mixed
     */
    public function getRaw(): mixed
    {
        return $this->engine()->search($this);
    }

    /**
     * Get the keys of search results.
     *
     * @return Collection
     */
    public function keys(): Collection
    {
        return $this->engine()->keys($this);
    }

    /**
     * Get the first result from the search.
     *
     * @return Model
     */
    public function first(): Model
    {
        return $this->limit(1)->get()->first();
    }

    /**
     * Get the results of the search.
     *
     * @return \Hyperf\Database\Model\Collection
     */
    public function get(): \Hyperf\Database\Model\Collection
    {
        $results = $this->engine()->get($this);

        if (count($this->with) > 0 && $results->count() > 0) {
            $results->load($this->with);
        }

        return $results;
    }

    /**
     * Get the count from the search.
     */
    public function count(): int
    {
        return $this->engine()->count($this);
    }

    /**
     * Paginate the given query into a simple paginator.
     */
    public function paginate(?int $perPage = null, string $pageName = 'page', ?int $page = null): LengthAwarePaginatorInterface
    {
        $engine = $this->engine();

        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->model->getPerPage();

        $results = $this->model->newCollection($engine->map(
            $this,
            $rawResults = $engine->paginate($this, $perPage, $page),
            $this->model
        )->all());

        if (count($this->with) > 0 && $results->count() > 0) {
            $results->load($this->with);
        }

        $paginator = make(LengthAwarePaginator::class, [
            'items' => $results,
            'total' => $engine->getTotalCount($rawResults),
            'perPage' => $perPage,
            'currentPage' => $page,
            'options' => [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ],
        ]);

        return $paginator->appends('query', $this->query);
    }

    /**
     * Paginate the given query into a simple paginator with raw data.
     */
    public function paginateRaw(?int $perPage = null, string $pageName = 'page', ?int $page = null): LengthAwarePaginatorInterface
    {
        $engine = $this->engine();

        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->model->getPerPage();

        $results = $engine->paginate($this, $perPage, $page);

        $paginator = make(LengthAwarePaginator::class, [
            'items' => $results,
            'total' => $engine->getTotalCount($results),
            'perPage' => $perPage,
            'currentPage' => $page,
            'options' => [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ],
        ]);

        return $paginator->appends('query', $this->query);
    }

    /**
     * Get the engine that should handle the query.
     *
     * @return \HyperfExt\Scout\Engine
     */
    protected function engine(): Engine
    {
        return $this->model->searchableUsing();
    }
}
