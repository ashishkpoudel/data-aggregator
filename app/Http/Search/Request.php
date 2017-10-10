<?php

namespace App\Http\Search;

use Illuminate\Support\Facades\Input;
use App\Models\Collections\Artwork;

class Request
{

    /**
     * The name of the index we will be querying.
     *
     * @var string
     */
    protected $index;

    /**
     * The Elasticsearch type (model) we will be querying.
     *
     * @var string
     */
    protected $type = null;

    /**
     * List of allowed Input params for querying.
     *
     * @var array
     */
    private static $allowed = [

        // Type can be passed via route, or via query params
        'type',

        // Required: we must know the core search string
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
        '_source',

        // Fields to use for aggregations
        'facets',

        // TODO: Hide implementation by combining _source w/ other fields?
        // Note that _source supports wildcards, while the others do not
        // 'fields', // old convention
        // 'stored_fields',
        // 'docvalue_fields',

        // Determines which shards to use, ensures consistent result order
        'preference',

    ];


    /**
     * Default fields to return.
     *
     * @var array
     */
    private static $defaultFields = [
        'id',
        'api_id',
        'api_model',
        'api_link',
        'title',
        'timestamp',
    ];


    /**
     * Create a new request instance.
     *
     * @return void
     */
    public function __construct( $type = null )
    {
        $this->index = env('ELASTICSEARCH_INDEX', 'data_aggregator_test');
        $this->type = $type;
    }


    /**
     * Get params that should be applied to all queries.
     *
     * @return array
     */
    public function getBaseParams( array $input ) {

        return [
            'index' => $this->index,
            'type' => array_get( $input, 'type' ) ?: $this->type,
            'preference' => array_get( $input, 'preference' ),
        ];

    }


    /**
     * Build full param set (request body) for autocomplete queries.
     *
     * @return array
     */
    public function getAutocompleteParams( ) {

        // Strip down the (top-level) params to what our thin client supports
        $input = self::getValidInput();

        // TODO: Handle case where no `q` param is present?
        if( is_null( array_get( $input, 'q' ) ) ) {
            return [];
        }

        // Suggest also returns `_source`, which we can disable to reduce server load
        $params = array_merge(
            $this->getBaseParams( $input ) ,
            $this->getFieldParams( $input, false )
        );

        // `q` is required here, but we won't send an actual `query`
        $params = $this->addSuggestParams( $params, $input );

        return $params;

    }


    /**
     * Build full param set (request body) for search queries.
     *
     * @return array
     */
    public function getSearchParams( $input = [] ) {

        if (!$input)
        {

            // Strip down the (top-level) params to what our thin client supports
            $input = self::getValidInput();

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

        // Add our custom relevancy tweaks into `should`
        $params = $this->addRelevancyParams( $params, $input );

        if( is_null( array_get( $input, 'q' ) ) ) {

            // Empy search requires special handling, e.g. no suggestions
            $params = $this->addEmptySearchParams( $params );

        } else {

            if( is_null( array_get( $input, 'query' ) ) ) {

                $params = $this->addSimpleSearchParams( $params, $input );

            } else {

                $params = $this->addFullSearchParams( $params, $input );

            }

            // Both `query` and `q`-only searches support suggestions
            $params = $this->addSuggestParams( $params, $input );

        }

        // Add Aggregations (facets)
        $params = $this->addAggregationParams( $params, $input );

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
     * @param $input array
     *
     * @return array
     */
    private function getPaginationParams( array $input ) {

        // Elasticsearch params take precedence
        $size = array_get( $input, 'size' ) ? $input['size'] : null;
        $from = array_get( $input, 'from' ) ? $input['from'] : null;

        // If that doesn't work, attempt to convert Laravel's pagination into ES params
        isset( $size ) ?: $size = array_get( $input, 'limit' ) ? $input['size'] : null;
        isset( $from ) ?: $from = array_get( $input, 'page' ) ? $input['page'] * $size : null;

        // If not null, cast these params to int
        if( isset( $size ) ) { $size = (int) $size; }
        if( isset( $from ) ) { $from = (int) $from; }

        // We are using isset() instead of normal ternary to avoid catching `0` as falsey
        // PHP 7 allows the "null coalescing operator"  - `??` - which serves the same role
        // https://stackoverflow.com/questions/18603250/php-shorthand-for-isset

        return [

            // TODO: Determine if this interferes w/ an autocomplete-only search
            'from' => $from,
            'size' => $size,

            // TODO: Re-enable this once the official ES PHP Client supports it
            // 'search_after' => $input['search_after'],

        ];

    }


    /**
     * Determine which fields to return. Set `_source` to `true` to return all.
     *
     * @param $input array
     * @param $default mixed Valid `_source` is array, string, null, or bool
     *
     * @return array
     */
    private function getFieldParams( array $input, $default = null ) {

        return [
            // '_source' => $input['_source'] ?? ( $default ?? self::$defaultFields ), // PHP 7
            '_source' => array_get( $input, '_source' ) ? $input['_source'] : ( isset( $default ) ? $default : self::$defaultFields ),
        ];

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
     * Append the query params for a simple search
     *
     * @param $params array
     * @param $input array
     *
     * @return array
     */
    private function addSimpleSearchParams( array $params, array $input ) {

        // TODO: Determine if defaults for `fuzziness` and `prefix_length` are sufficient
        // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-fuzzy-query.htm

        // TODO: Determine which fields to query w/ is_numeric()?
        // See also `lenient` param

        $params['body']['query']['bool']['must'][] = [
            'multi_match' => [
                'query' => array_get( $input, 'q' ),
                'fuzziness' => 3,
                'prefix_length' => 1,
                'fields' => [
                    '_all',
                ]
            ]
        ];

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
     * Append our own custom queries to tweak relevancy.
     *
     * @param $params array
     * @param $input array
     *
     * @return array
     */
    public function addRelevancyParams( array $params, array $input )
    {

        // Boost essential artworks
        $params['body']['query']['bool']['should'][] = [
            'terms' => [
                'api_id' => Artwork::getEssentialIds()
            ]
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
    public function addSuggestParams( array $params, array $input )
    {

        $params['body']['suggest'] = [
            'text' => array_get( $input, 'q' ),
        ];

        $params = $this->addAutocompleteSuggestParams( $params, $input );
        $params = $this->addPhraseSuggestParams( $params, $input );

        return $params;

    }


    /**
     * Append autocomplete suggest params.
     *
     * @param $params array
     * @param $input array
     *
     * @return array
     */
    private function addAutocompleteSuggestParams( array $params, array $input)
    {

        $params['body']['suggest']['autocomplete'] = [
            'prefix' =>  array_get( $input, 'q' ),
            'completion' => [
                'field' => 'suggest_autocomplete',
            ],
        ];

        return $params;

    }


    /**
     * Append phrase suggest parameters.
     *
     * @param $params array
     *
     * @return array
     */
    private function addPhraseSuggestParams( array $params )
    {

        $params['body']['suggest']['phrase-suggest'] = [
            'phrase' => [
                'field' => 'suggest_phrase.trigram',
                'gram_size' => 3,
                'direct_generator' => [
                    [
                        'field' => 'suggest_phrase.trigram',
                        'suggest_mode' => 'always'
                    ],
                    [
                        'field' => 'suggest_phrase.reverse',
                        'suggest_mode' => 'always',
                        'pre_filter' => 'reverse',
                        'post_filter' => 'reverse'
                    ],
                ],
                'highlight' => [
                    'pre_tag' => '<em>',
                    'post_tag' => '</em>'
                ],
            ],
        ];

        return $params;

    }


    /**
     * Append aggregation parameters.
     *
     * @param $params array
     * @param $input array
     *
     * @return array
     */
    public function addAggregationParams( array $params, array $input )
    {

        // If the user did not specify a facet parameter, facet by model
        $facets = array_get( $input, 'facets' ) ? explode(',', $input['facets']) : ['api_model'];

        $aggregations = [];

        foreach ($facets as $facet)
        {

            $aggregations['count_' . $facet] = [
                'terms' => [
                    'field' => $facet
                ]
            ];

        }

        $params['body']['aggregations'] = $aggregations;

        return $params;

    }


}
