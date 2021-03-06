<?php

namespace App\Http\Search;

use Illuminate\Support\Facades\Input;

class Request
{

    /**
     * Resource targeted by this search request. Derived from API endpoint, or from `resources` param.
     * Accepted as comma-separated string, or as array. Converted to array shortly after `__construct()`.
     *
     * @var array|string
     */
    protected $resources = null;

    /**
     * Identifier, e.g. for `_explain` queries
     *
     * @var string
     */
    protected $id = null;

    /**
     * Array of queries needed to isolate any "scoped" resources in this request.
     *
     * @var array
     */
    protected $scopes = null;

    /**
     * Array of queries needed to boost resources in this request.
     *
     * @var array
     */
    protected $boosts = [];

    /**
     * Array of queries used in a `function_score` wrapper.
     *
     * @var array
     */
    protected $functionScores = null;

    /**
     * List of allowed Input params for querying.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/5.3/search-request-body.html
     *
     * @var array
     */
    private static $allowed = [

        // Resources can be passed via route, or via query params
        'resources',

        // Required for "Did You Mean"-style suggestions: we need to know the core search string
        // We use `q` b/c it won't cause UnexpectedValueException, if the user uses an official ES Client
        'q',

        // Complex query mode
        'query',
        'sort',

        // Pagination via Elasticsearch conventions
        'from',
        'size',

        // Pagination via Laravel conventions
        'page',
        'limit',

        // Currently unsupported by the official ES PHP Client
        // 'search_after',

        // Choose which fields to return
        'fields',

        // Contexts for autosuggest
        'contexts',

        // Fields to use for aggregations
        'aggs',
        'aggregations',

        // Determines which shards to use, ensures consistent result order
        'preference',

        // Allow clients to turn fuzzy off
        'fuzzy',

        // Allow clients to turn boost off
        'boost',

        // Allow clients to pass custom `function_score` functions
        'functions',
    ];

    /**
     * Default fields to return.
     *
     * @var array
     */
    private static $defaultFields = [
        'id',
        'api_model',
        'api_link',
        'is_boosted',
        'title',
        'thumbnail',
        'timestamp',
    ];

    /**
     * Maximum request `size` for pagination.
     *
     * @TODO Sync this to max size as defined in controllers.
     *
     * @var integer
     */
    private static $maxSize = 1000;


    /**
     * Create a new request instance.
     *
     * @param $resource string
     *
     * @return void
     */
    public function __construct( $resource = null, $id = null )
    {
        // TODO: Add $input here too..?
        $this->resources = $resource;
        $this->id = $id;
    }


    /**
     * Get params that should be applied to all queries.
     *
     * @TODO: Remove type-related logic when we upgrade to ES 6.0
     *
     * @return array
     */
    public function getBaseParams( array $input ) {

        // Grab resource target from resource endpoint or `resources` param
        $resources = $this->resources ?? $input['resources'] ?? null;

        // Ensure that resources is an array, not string
        if( is_string( $resources ) )
        {
            $resources = explode(',', $resources);
        }

        // Save unfiltered $resources for e.g. getting default fields
        $this->resources = $resources;

        if( is_null( $resources ) )
        {

            $indexes = env('ELASTICSEARCH_ALIAS');

        } else {

            // Filter out any resources that have a parent resource requested as well
            // So e.g. if places and galleries are requested, we'll show places only
            $resources = array_filter( $resources, function($resource) use ($resources) {

                $parent = app('Resources')->getParent( $resource );

                return !in_array( $parent, $resources );

            });

            // Make resources into a Laravel collection
            $resources = collect( $resources );

            // Grab settings from our models via the service provider
            $settings = $resources->map( function($resource) {

                return [
                    $resource => app('Search')->getSearchScopeForEndpoint( $resource ),
                ];

            })->collapse();

            // Collate our indexes and types
            $indexes = $settings->pluck('index')->unique()->all();
            $types = $settings->pluck('type')->unique()->all();

            // These will be injected into the must clause
            $this->scopes = $settings->pluck('scope')->filter()->values()->all();

            // These will be injected into the should clause
            if (!isset($input['q']))
            {
                $this->boosts = $settings->pluck('boost')->filter()->values()->all();
            }

            // These will be used to wrap the query in `function_score`
            $this->functionScores = $settings->filter( function( $value, $key ) {
                return isset($value['function_score']);
            })->map( function( $item, $key ) {
                return $item['function_score'];
            })->all();

            if (isset($input['functions']))
            {
                $customScoreFunctions = collect($input['functions'])->filter(function($value, $key) use ($resources) {
                    return $resources->contains($key);
                });

                foreach ($customScoreFunctions as $resource => $function) {
                    $this->functionScores[$resource]['custom'][] = $function;
                }
            }

            // Looks like we don't need to implode $indexes and $types
            // PHP Elasticsearch seems to do so for us

        }

        return [
            'index' => $indexes,
            'type' => $types ?? null,
            'preference' => array_get( $input, 'preference' ),
        ];

    }


    /**
     * Build full param set (request body) for autocomplete queries.
     *
     * @return array
     */
    public function getAutocompleteParams( $requestArgs = null ) {

        // Strip down the (top-level) params to what our thin client supports
        $input = self::getValidInput($requestArgs);

        // TODO: Handle case where no `q` param is present?
        if( is_null( array_get( $input, 'q' ) ) ) {
            return [];
        }

        // Hardcode $input to only return the fields we want
        $input['fields'] = [
            'id',
            'title',
            'main_reference_number',
            'api_model',
            'subtype', // TODO: Allow each model to specify exposed autocomplete fields?
        ];

        // Suggest also returns `_source`, which we can parse to get the cannonical title
        $params = array_merge(
            $this->getBaseParams( $input ) ,
            $this->getFieldParams( $input, false )
        );

        // `q` is required here, but we won't send an actual `query`
        $params = $this->addSuggestParams( $params, $input, $requestArgs );

        return $params;

    }


    /**
     * Build full param set (request body) for search queries.
     *
     * @return array
     */
    public function getSearchParams( $input = null, $withAggregations = true ) {

        // Strip down the (top-level) params to what our thin client supports
        $input = self::getValidInput( $input );

        // Normalize the `boost` param to bool (default: true)
        if (!isset($input['boost'])) {
            $input['boost'] = true;
        } elseif (is_string($input['boost'])) {
            $input['boost'] = filter_var($input['boost'], FILTER_VALIDATE_BOOLEAN);
        }

        $params = array_merge(
            $this->getBaseParams( $input ),
            $this->getFieldParams( $input ),
            $this->getPaginationParams( $input )
        );

        // This is the canonical body structure. It is required.
        // Various
        $params['body'] = [

            'query' => [
                'bool' => [
                    'must' => [],
                    'should' => [],
                ],
            ],

        ];

        // Add sort into the body, not the request
        $params = $this->addSortParams( $params, $input );

        // Add our custom relevancy tweaks into `should`
        if ( $input['boost'] ) {

            $params = $this->addRelevancyParams( $params, $input );

        }

        // Add params to isolate "scoped" resources into `must`
        $params = $this->addScopeParams( $params, $input );

        /**
         * 1. If `query` is present, append it to the `must` clause.
         * 2. If `q` is present, add full-text search to the `must` clause.
         * 3. If `q` is absent, show all results.
         */
        if( isset( $input['query'] ) ) {

            $params = $this->addFullSearchParams( $params, $input );

        }

        if( isset( $input['q'] ) ) {

            $params = $this->addSimpleSearchParams( $params, $input );

        } else {

            $params = $this->addEmptySearchParams( $params );

        }

        // Add Aggregations (facets)
        if( $withAggregations ) {

            $params = $this->addAggregationParams( $params, $input );

        }

        // Apply `function_score` (if any)
        $params = $this->addFunctionScore( $params, $input );

        return $params;

    }


    /**
     * Gather params for an expalin query. Explain queries are identical to search,
     * but they need an id and lack pagination, aggregations, and suggestions.
     *
     * @return array
     */
    public function getExplainParams( $input = [] ) {

        $params = $this->getSearchParams( $input, false );

        $params['id'] = $this->id;

        unset( $params['from'] );
        unset( $params['size'] );

        return $params;

    }


    /**
     * Strip down the (top-level) user-input to what our thin client supports.
     * Allowed-but-omitted params are added as `null`
     *
     * @param $input array
     *
     * @return array
     */
    public static function getValidInput( array $input = null ) {

        // Grab all user input (query string params or json)
        $input = $input ?: Input::all();

        // List of allowed user-specified params
        $allowed = self::$allowed;

        // `null` will be the default value for all params
        $defaults = array_fill_keys( $allowed, null );

        // Reduce the input set to the params we allow
        $input = array_intersect_key( $input, array_flip( $allowed ) );

        // Combine $defaults and $input: we won't have to use is_set, only is_null
        $input = array_merge( $defaults, $input );

        return $input;

    }


    /**
     * Get pagination params.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/search-request-from-size.html
     *
     * @param $input array
     *
     * @return array
     */
    private function getPaginationParams( array $input ) {

        // Elasticsearch params take precedence
        // If that doesn't work, attempt to convert Laravel's pagination into ES params
        $size = $input['size'] ?? $input['limit'] ?? 10;
        $from = $input['from'] ?? null;

        // `from` takes precedence over `page`
        if (!$from && isset($input['page']) && $input['page'] > 0) {
            $from = ($input['page'] - 1) * $size;
        }

        // ES is robust: it can accept `size` or `from` independently

        // If not null, cast these params to int
        // We are using isset() instead of normal ternary to avoid catching `0` as falsey
        if( isset( $size ) ) { $size = (int) $size; }
        if( isset( $from ) ) { $from = (int) $from; }

        // TODO: Throw an exception if `size` is too big
        // This will have to wait until we refactor controller exceptions
        if( $size > self::$maxSize ) {
            //
        }

        return [

            // TODO: Determine if this interferes w/ an autocomplete-only search
            'from' => $from,
            'size' => $size,

            // TODO: Re-enable this once the official ES PHP Client supports it
            // 'search_after' => $input['search_after'],

        ];

    }


    /**
     * Determine which fields to return. Set `fields` to `true` to return all.
     * Set `fields` to `false` to return nothing.
     *
     * We currently use `fields` to return `_source` from Elasticsearch, but this
     * may change in the future. The user shouldn't care about how we are storing
     * these fields internally, only what the API outputs.
     *
     * @param $input array
     * @param $default mixed Valid `_source` is array, string, null, or bool
     *
     * @return array
     */
    private function getFieldParams( array $input, $default = null ) {

        return [
            '_source' => $input['fields'] ?? ( $default ?? self::$defaultFields ),
        ];

    }


    /**
     * Determine sort order. Sort must go into the request body, and it cannot be null.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/5.3/search-request-sort.html
     * @link https://github.com/elastic/elasticsearch-php/issues/179
     *
     * @param $params array
     * @param $input array
     *
     * @return array
     */
    private function addSortParams( array $params, array $input ) {

        if( isset( $input['sort'] ) )
        {
            $params['body']['sort'] = $input['sort'];
        }

        return $params;

    }


    /**
     * Append our own custom queries to tweak relevancy.
     *
     * @param $params array
     * @param $input array
     *
     * @return array
     */
    public function addRelevancyParams( array $params, array $input )
    {

        // Don't tweak relevancy if sort is passed
        if( isset( $input['sort'] ) )
        {
            return $params;
        }

        if (!isset($input['q']))
        {
            // Boost anything with `is_boosted` true
            $params['body']['query']['bool']['should'][] = [
                'term' => [
                    'is_boosted' => [
                        'value' => true,
                        'boost' => 1.5,
                    ]
                ]
            ];

            // Add any resource-specific boosts
            foreach( $this->boosts as $boost ) {

                $params['body']['query']['bool']['should'][] = $boost;

            }

        }

        return $params;

    }

    /**
     * Wrap the current query in a `function_score` query. Typically, this should be the last method called.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/6.0/query-dsl-function-score-query.html
     *
     * @param $params array
     *
     * @return array
     */
    public function addFunctionScore( $params, $input )
    {

        if( empty($this->functionScores) || !isset( $this->resources ) )
        {
            return $params;
        }

        // We'll duplicate this, nesting it in `function_score` queries
        $baseQuery = $params['body']['query'];

        // Keep track of this to create a "left over" non-scored query
        $resourcesWithoutFunctions = collect([]);

        $scopedQueries = collect([]);

        foreach( $this->resources as $resource ) {

            // Grab the functions for this resource
            $rawFunctions = $this->functionScores[$resource] ?? null;

            // Move on if there are no functions declared for this model
            if (empty($rawFunctions))
            {
                $resourcesWithoutFunctions->push($resource);
                continue;
            }

            // Start building the outbound function score array
            $outFunctions = [];

            if ($input['boost'])
            {
                $outFunctions = array_merge($outFunctions, $rawFunctions['all']);
            }

            if ($input['boost'] && !isset($input['q']) && isset($rawFunctions['except_full_text']))
            {
                $outFunctions = array_merge($outFunctions, $rawFunctions['except_full_text'] );
            }

            if (isset($rawFunctions['custom']))
            {
                $outFunctions = array_merge($outFunctions, $rawFunctions['custom']);
            }

            if (empty($outFunctions))
            {
                $resourcesWithoutFunctions->push($resource);
                continue;
            }

            // Build our function score query
            $resourceQuery = [
                'function_score' => [
                    'query' => $baseQuery,
                    'functions' => $outFunctions,
                    'score_mode' => 'max', // TODO: Consider making this an option?
                    'boost_mode' => 'multiply',
                ]
            ];

            // Wrap the query in a scope
            $scopedQuery = app('Search')->getScopedQuery( $resource, $resourceQuery );

            $scopedQueries->push( $scopedQuery );

        }

        // Add a query for all the leftover resources
        $scopedQuery = app('Search')->getScopedQuery( $resourcesWithoutFunctions->all(), $baseQuery );

        $scopedQueries->push( $scopedQuery );

        // Override the existing query with our queries
        $params['body']['query'] = [
            'bool' => [
                'must' => $scopedQueries->all(),
            ]
        ];

        return $params;

    }


    /**
     * Append any search clauses that are needed to isolate scoped resources.
     *
     * @param $params array
     * @param $input array
     *
     * @return array
     */
    public function addScopeParams( array $params, array $input )
    {

        if( !isset( $this->scopes ) || count( $this->scopes ) < 1 ) {

            return $params;

        }

        // Assumes that `scopes` has no null members
        $params['body']['query']['bool']['must'][] = [
            'bool' => [
                'should' => $this->scopes,
            ]
        ];

        return $params;

    }


    /**
     * Get the search params for an empty string search.
     * Empy search requires special handling, e.g. no suggestions.
     *
     * @param $params array
     *
     * @return array
     */
    private function addEmptySearchParams( array $params ) {

        // PHP JSON-encodes empty array as [], not {}
        $params['body']['query']['bool']['must'][] = [
            'match_all' => new \stdClass()
        ];

        return $params;

    }


    /**
     * Append the query params for a simple search. Assumes that `$input['q']` is not null.
     *
     * @TODO Determine which fields to query w/ is_numeric()? See also `lenient` param.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/5.3/common-options.html#fuzziness
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-fuzzy-query.htm
     *
     * @param $params array
     * @param $input array
     *
     * @return array
     */
    private function addSimpleSearchParams( array $params, array $input ) {

        // Only pull default fields for the resources targeted by this request
        $fields = app('Search')->getDefaultFieldsForEndpoints( $this->resources );

        // Determing if fuzzy searching should be used on this query
        $fuzziness = $this->getFuzzy($input);

        // Pull all docs that match fuzzily into the results
        $params['body']['query']['bool']['must'][] = [
            'multi_match' => [
                'query' => $input['q'],
                'fuzziness' => $fuzziness,
                'prefix_length' => 1,
                'fields' => $fields,
            ],
        ];

        // Queries below depend on `q`, but act as relevany tweaks
        // Don't tweak relevancy further if sort is passed
        if( isset( $input['sort'] ) )
        {
            return $params;
        }

        // This acts as a boost for docs that match precisely, if fuzzy search is enabled
        if ($fuzziness)
        {
            $params['body']['query']['bool']['should'][] = [
                'multi_match' => [
                    'query' => $input['q'],
                    'fields' => $fields,
                ]
            ];
        }

        // This boosts docs that have multiple terms in close proximity
        // `phrase` queries are relatively expensive, so check for spaces first
        // https://www.elastic.co/guide/en/elasticsearch/guide/current/_improving_performance.html
        if( strpos( $input['q'], ' ' ) )
        {
            $params['body']['query']['bool']['should'][] = [
                'multi_match' => [
                    'query' => $input['q'],
                    'type' => 'phrase',
                    'slop' => 3, // account for e.g. middle names
                    'fields' => $fields,
                    'boost' => 10, // See WEB-22
                ]
            ];
        }

        return $params;

    }


    /**
     * Get the search params for a complex search
     *
     * @param $params array
     * @param $input array
     *
     * @return array
     */
    private function addFullSearchParams( array $params, array $input ) {

        // TODO: Validate `query` input to reduce shenanigans
        // TODO: Deep-find `fields` in certain queries + replace them w/ our custom field list
        $params['body']['query']['bool']['must'][] = [
            array_get( $input, 'query' ),
        ];

        return $params;

    }


    /**
     * Append suggest params to query.
     *
     * Both `query` and `q`-only searches support suggestions.
     * Empty searches do not support suggestions.
     *
     * @param $params array
     * @param $input array
     *
     * @return array
     */
    public function addSuggestParams( array $params, array $input, $requestArgs = null)
    {

        $params['body']['suggest'] = [
            'text' => array_get( $input, 'q' ),
        ];

        $params = $this->addAutocompleteSuggestParams( $params, $input, $requestArgs );

        return $params;

    }


    /**
     * Append autocomplete suggest params.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/5.3/search-suggesters-completion.html
     *
     * @param $params array
     * @param $input array
     *
     * @return array
     */
    private function addAutocompleteSuggestParams( array $params, array $input, $requestArgs = null)
    {

        $isThisAutosuggest = $requestArgs && is_array($requestArgs) && ($requestArgs['use_suggest_autocomplete_all'] ?? false);

        if ($isThisAutosuggest) {
            $field = 'suggest_autocomplete_all';
        } else {
            $field = 'suggest_autocomplete_boosted';
        }

        $params['body']['suggest']['autocomplete'] = [
            'prefix' =>  array_get( $input, 'q' ),
            'completion' => [
                'field' => $field,
                'fuzzy' => [
                    'fuzziness' => $this->getFuzzy($input),
                    'min_length' => 5,
                ],
            ],
        ];

        if ($isThisAutosuggest && isset($input['contexts'])) {
            $contexts = $input['contexts'];

            // Ensure that resources is an array, not string
            if( is_string( $contexts ) )
            {
                $contexts = explode(',', $contexts);
            }

            $params['body']['suggest']['autocomplete']['completion']['contexts'] = [
                'groupings' => $contexts,
            ];
        }

        return $params;

    }


    /**
     * Append aggregation parameters. This is a straight pass-through for more flexibility.
     * Elasticsearch accepts both `aggs` and `aggregations`, so we support both too.
     *
     * @param $params array
     * @param $input array
     *
     * @return array
     */
    public function addAggregationParams( array $params, array $input )
    {

        $aggregations = $input['aggregations'] ?? $input['aggs'] ?? null;

        if( $aggregations ) {

            $params['body']['aggregations'] = $aggregations;

        }

        return $params;

    }

    private function getFuzzy( array $input )
    {
        if (count(explode(' ', $input['q'] ?? '')) > 7)
        {
            return 0;
        }

        if (!isset($input['fuzzy']))
        {
            return 'AUTO';
        }

        if ($input['fuzzy'] === 'AUTO')
        {
            return 'AUTO';
        }

        return min([2, (int) $input['fuzzy']]);
    }

}
