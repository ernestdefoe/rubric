<?php

namespace Ernestdefoe\Rubric\Search;

use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;
use Flarum\Search\ValidateFilterTrait;

/**
 * filter[prefix]=rumor on the discussion list, and the prefix:rumor gambit in
 * the search box. Several slugs (rumor,official) match any of them.
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
class PrefixFilter implements FilterInterface
{
    use ValidateFilterTrait;

    public function getFilterKey(): string
    {
        return 'prefix';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        $slugs = array_values(array_filter(array_map(
            fn ($s) => strtolower(trim((string) $s)),
            $this->asStringArray($value)
        )));

        // The subquery goes through the query builder, so the table prefix is
        // applied to rubric_prefixes like any other table.
        $state->getQuery()->whereIn(
            'discussions.rubric_prefix_id',
            fn ($query) => $query->select('id')->from('rubric_prefixes')->whereIn('slug', $slugs),
            'and',
            $negate
        );
    }
}
