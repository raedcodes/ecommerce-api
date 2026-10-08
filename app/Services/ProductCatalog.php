<?php

namespace App\Services;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Public product listing, cached per filter combination.
 *
 * Every cache key embeds a catalog "version". Any product write (including stock changes at
 * checkout/cancellation) replaces the version after the transaction commits, so stale listings
 * are never served; old entries simply expire.
 */
class ProductCatalog
{
    public const DEFAULT_SORT = '-created_at';

    private const VERSION_KEY = 'products:catalog-version';

    private const TTL_SECONDS = 600;

    /**
     * @param  array{filter?: array<string, string>, sort?: string}  $query  Validated `filter` and `sort` query parameters.
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(array $query, int $perPage, int $page): LengthAwarePaginator
    {
        $key = sprintf(
            'products:list:%s:%s',
            $this->version(),
            md5(json_encode([$query, $perPage, $page], JSON_THROW_ON_ERROR)),
        );

        // Only plain arrays are cached: Laravel refuses to unserialize objects from the cache.
        $cached = Cache::remember($key, self::TTL_SECONDS, function () use ($query, $perPage, $page): array {
            $products = $this->buildQuery($query)->paginate($perPage, page: $page);

            return [
                'total' => $products->total(),
                'rows' => $products->getCollection()->map(fn (Product $product) => $product->getAttributes())->all(),
            ];
        });

        return new LengthAwarePaginator(
            Product::hydrate($cached['rows']),
            $cached['total'],
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    /**
     * Invalidate every cached listing once the current transaction (if any) commits.
     */
    public static function invalidate(): void
    {
        DB::afterCommit(fn () => Cache::forever(self::VERSION_KEY, (string) Str::uuid()));
    }

    /**
     * Built from the validated input only, so the cache key always matches the query that runs.
     *
     * @param  array{filter?: array<string, string>, sort?: string}  $query
     * @return QueryBuilder<Product>
     */
    private function buildQuery(array $query): QueryBuilder
    {
        $sort = $query['sort'] ?? self::DEFAULT_SORT;

        return QueryBuilder::for(Product::query()->active(), new Request($query))
            ->allowedFilters(
                // An empty delimiter keeps commas in a value literal instead of splitting it into an OR list.
                AllowedFilter::partial('name')->delimiter(''),
                AllowedFilter::callback('min_price', fn (Builder $builder, mixed $value) => $builder->where('price', '>=', Money::toCents($value)))->delimiter(''),
                AllowedFilter::callback('max_price', fn (Builder $builder, mixed $value) => $builder->where('price', '<=', Money::toCents($value)))->delimiter(''),
                AllowedFilter::callback('in_stock', fn (Builder $builder, mixed $value) => $builder->inStock(filter_var($value, FILTER_VALIDATE_BOOLEAN)))->delimiter(''),
            )
            ->allowedSorts(...Product::SORTABLE_COLUMNS)
            ->defaultSort(self::DEFAULT_SORT)
            // Tie-breaker so rows with equal sort values keep a stable order across pages.
            ->orderBy('id', str_starts_with($sort, '-') ? 'desc' : 'asc');
    }

    private function version(): string
    {
        return Cache::rememberForever(self::VERSION_KEY, fn () => (string) Str::uuid());
    }
}
