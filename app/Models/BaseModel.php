<?php

namespace App\Models;

use Aic\Hub\Foundation\AbstractModel;

class BaseModel extends AbstractModel
{

    use Transformable, Fillable, Instancable;

    /**
     * String that indicates the sub-namespace of the child models. Used for dynamic model retrieval.
     *
     * @var string
     */
    protected static $source;


    /**
     * The smallest number that fake IDs start at for this model
     *
     * @var integer
     */
    protected $fakeIdsStartAt = 999000;


    /**
     * Scope a query to only include fake records.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFake($query)
    {
        if ($this->getKeyType() == 'int')
        {

            return $query->where($this->getKeyName(), '>=', $this->fakeIdsStartAt);

        }
        else
        {

            return $query->where($this->getKeyName(), 'like', '99999999-9999-9999-9999-%');

        }

    }


    /**
     * Get the class name for a given API endpoint
     *
     * @param  string  $endpoint
     * @return string
     */
    public static function classFor($endpoint)
    {

        return '\App\Models\\' . static::$source . '\\' . studly_case(str_singular($endpoint));

    }

    /**
     * Find the record matching the given id or create it.
     *
     * @TODO Remove this in favor of Laravel's built-in findOrCreate.
     *
     * @param  int    $id
     * @return \Illuminate\Database\Eloquent\Model
     */
    public static function findOrCreate($id)
    {

        $model = static::find($id);
        return $model ?: static::create([static::instance()->getKeyName() => $id]);

    }


    /**
     * The smallest number that fake IDs start at for this model
     *
     * @return integer
     */
    public static function fakeIdsStartAt()
    {

        return $this->instance()->fakeIdsStartAt;

    }


    /**
     * Define how the fields in the API are mapped to model properties.
     *
     * Acts as a wrapper method to common attributes across a range of resources. Each method should
     * override `transformMappingInternal()` with their specific field definitions.
     *
     * The keys in the returned array represent the property name as it appears in the API. The value of
     * each pair is an array that includes the following:
     *
     * - "doc" => The documentation for this API field
     * - "value" => An anoymous function that returns the value for this field
     *
     * @return array
     */
    protected function transformMapping()
    {

        return array_merge(
            $this->getMappingForIds(),
            $this->getMappingForTitles(),
            [
                [
                    'name' => 'is_boosted',
                    'doc' => "Whether this document should be boosted in search",
                    "type" => "boolean",
                    'value' => function() { return $this->isBoosted(); },
                ]
            ],
            $this->transformMappingInternal(),
            $this->getMappingForDates()
        );

    }

    protected function getMappingForIds()
    {
        return [
            [
                'name' => 'id',
                'doc' => 'Unique identifier of this resource. Taken from the source system.',
                'type' => 'number',
                'elasticsearch' => [
                    'type' => 'integer',
                ],
                'value' => function() { return $this->getAttributeValue($this->getKeyName()); },
            ]
        ];
    }

    protected function getMappingForTitles()
    {
        return [
            [
                'name' => 'title',
                'doc' => 'Name of this resource',
                'type' => 'string',
                'elasticsearch' => [
                    'type' => 'text',
                    'default' => true,
                    'boost' => 2,
                ],
                'value' => function() { return $this->title; },
            ]
        ];
    }

    protected function getMappingForDates()
    {

        if ($this->excludeDates)
        {
            return [];
        }

        return [
            [
                'name' => 'last_updated_source',
                'doc' => 'Date and time the resource was updated in the source system',
                'type' => 'string',
                'value' => function() { return $this->source_indexed_at ? $this->source_indexed_at->toIso8601String() : NULL; },
            ],
            [
                'name' => 'last_updated',
                'doc' => 'Date and time the resource was updated in the Data Aggregator',
                'type' => 'string',
                'value' => function() { return $this->updated_at ? $this->updated_at->toIso8601String() : NULL; },
            ],
        ];
    }

    public function isBoosted()
    {

        return false;

    }

    /**
     * Generate a unique ID based on a combination of two numbers.
     * @param  int   $x
     * @param  int   $y
     * @return int
     */
    public function cantorPair($x, $y)
    {

        return (($x + $y) * ($x + $y + 1)) / 2 + $y;

    }

    /**
     * Get the two numbers that a cantor ID was based on
     * @param  int   $z
     * @return array
     */
    public function reverseCantorPair($z)
    {

        $t = floor((-1 + sqrt(1 + 8 * $z))/2);
        $x = $t * ($t + 3) / 2 - $z;
        $y = $z - $t * ($t + 1) / 2;
        return [$x, $y];

    }

    public function has($trait)
    {

        $traits = class_uses_deep($this);
        foreach ($traits as $t)
        {

            if ($t == $trait)
            {

                return true;

            }

        }

        return false;

    }

}
