<?php

namespace App\Models\Membership;

use App\Models\MembershipModel;
use App\Models\ElasticSearchable;

class Event extends MembershipModel
{

    use ElasticSearchable;

    protected $dates = [
        'start_at',
        'end_at',
        'source_created_at',
        'source_modified_at',
    ];

    public function resource2gallery( $resource_id )
    {

        $locations = [
            '3' => 26131, // Rubloff Auditorium
            '4' => 28277, // Price Auditorium
            '5' => 28276, // Morton Auditorium
            '10' => 24000, // Griffin Court
            '11' => 2147475902, // Regenstein Hall
            '22' => 23998, // Abbott Galleries (Galleries 182-184, this is Gallery 183)
            '23' => 2147483599, // Fullerton Hall
            '49' => 23965, // Cafe Moderno
            '50' => 25563, // Terzo Piano
            '77' => 2147475902, // Regenstein Hall Thursday
            '80' => 25237, // Pritzker Garden
            '81' => 346, // Stock Exchange Trading Room
            '82' => 2147477257, // Gallery 11
            '83' => 2147472011, // Grand Staircase
            '86' => 27946, // South Garden
            '87' => 2147477076, // North Garden
        ];

        $resource_id = (string) $resource_id;

        return $locations[ $resource_id ] ?? null;
    }

    protected function getFillFieldsFrom($source)
    {

        return [

            'type_id' => $source->type_id,

            'start_at' => strtotime($source->start_at),
            'end_at' => strtotime($source->end_at),

            'resource_id' => $source->resource_id,
            'resource_title' => $source->resource_title,

            'is_after_hours' => $source->is_after_hours,
            'is_private_event' => $source->is_private_event,
            'is_admission_required' => $source->is_admission_required,

            'available' => $source->available,
            'total_capacity' => $source->total_capacity,

        ];

    }

    /**
     * Turn this model object into a generic array.
     *
     * @param boolean  $withTitles
     * @return array
     */
    public function transformFields()
    {

        return [

            'type_id' => $this->type_id,

            'start_at' => $this->start_at->toIso8601String(),
            'end_at' => $this->end_at->toIso8601String(),

            'resource_id' => $this->resource_id,
            'resource_title' => $this->resource_title,

            // Caution: (bool) null = false
            // TODO: Use $casts throughout the codebase
            'is_after_hours' => (bool) $this->is_after_hours,
            'is_private_event' => (bool) $this->is_private_event,
            'is_admission_required' => (bool) $this->is_admission_required,

            'available' => $this->available,
            'total_capacity' => $this->total_capacity,

        ];

    }


    /**
     * Generate model-specific fields for an array representing the schema for this object.
     *
     * @return array
     */
    public function elasticsearchMappingFields()
    {

        return [

            'type_id' => [
                'type' => 'keyword',
            ],
            'start_at' => [
                'type' => 'date',
            ],
            'end_at' => [
                'type' => 'date',
            ],
            'resource_id' => [
                'type' => 'integer',
            ],
            'resource_title' => [
                'type' => 'keyword',
            ],
            'is_after_hours' => [
                'type' => 'boolean',
            ],
            'is_private_event' => [
                'type' => 'boolean',
            ],
            'is_admission_required' => [
                'type' => 'boolean',
            ],
            'available' => [
                'type' => 'integer',
            ],
            'total_capacity' => [
                'type' => 'integer',
            ],

        ];

    }

}