<?php

namespace App\Models;

use App\Models\BaseModel;

class CollectionsModel extends BaseModel
{

    protected static $source = 'Collections';

    protected $fakeIdsStartAt = 999000000;

    protected $isInCiti = true;

    public function getDates()
    {

        $dates = parent::getDates();

        if (!$this->hasSourceDates)
        {
            return $dates;
        }

        // This accounts for Assets, which are in LAKE, but not in CITI
        if ($this->isInCiti)
        {
            $dates = array_merge( $dates, [
                'citi_created_at',
                'citi_modified_at',
            ]);
        }

        return array_merge( $dates, [
            'source_indexed_at',
        ]);

    }

    /**
     * Fill in this model's IDs from the given resource, or fill it in with fake data.
     * This method is used primarily when the given resource is provided by the source
     * system.
     *
     * @param  object  $source
     * @return $this
     */
    protected function fillIdsFrom($source)
    {

        $fill = [];

        if ($this->getKeyName() == 'citi_id')
        {

            $fill['citi_id'] = $source->id;
            $fill['lake_guid'] = $source->lake_guid;

        } else {

            $fill['lake_guid'] = $source->id;

        }

        $this->fill($fill);

        return $this;

    }


    /**
     * Fill in this model's dates from the given resource, or fill it in with fake data.
     * This method is used primarily when the given resource is provided by the source
     * system.
     *
     * @param  object  $source
     * @return $this
     */
    protected function fillDatesFrom($source)
    {

        $fill = [];

        $fill['source_created_at'] = strtotime($source->created_at);
        $fill['source_modified_at'] = strtotime($source->modified_at);
        $fill['source_indexed_at'] = strtotime($source->indexed_at);

        $this->fill($fill);

        return $this;

    }

    protected function getMappingForIds()
    {

        $ret = parent::getMappingForIds();

        return array_merge( $ret, [
            [
               "name" => 'lake_guid',
               'doc' => "Unique UUID of this resource in LAKE, our digital asset management system",
               "type" => "uuid",
               'elasticsearch_type' => 'keyword',
               'value' => function() { return $this->lake_guid; },
            ]
        ]);

    }

    // TODO: Change this to more specificity, i.e. last_updated_lake rather than last_updated_source
    protected function getMappingForDates()
    {

        if (!$this->hasSourceDates)
        {
            return [];
        }

        $ret = parent::getMappingForDates();

        $ret[] = [
           "name" => 'last_updated_fedora',
           'doc' => "Date and time the resource was updated in LAKE, our digital asset management system",
           "type" => "ISO 8601 date and time",
           'value' => function() { return $this->source_modified_at ? $this->source_modified_at->toIso8601String() : NULL; },

        ];

        // We need to replace the `doc` and `value of an item already in the array
        // This is tricky since we don't key by the field name
        // We should consider doing so once this logic lives in outbound transformers
        foreach ($ret as &$field) {
            if($field['name'] == 'last_updated_source') {
                $field['doc'] = "Date and time the resource was updated in the LAKE LPM Solr index, which is our direct source of data";
                $field['value'] = function() { return $this->source_indexed_at ? $this->source_indexed_at->toIso8601String() : NULL; };
            }
        }

        if (!$this->isInCiti)
        {
            return $ret;
        }

        $ret[] = [
           "name" => 'last_updated_citi',
           'doc' => "Date and time the resource was updated in CITI, our collections management system",
           "type" => "ISO 8601 date and time",
           'value' => function() { return $this->citi_modified_at->toIso8601String(); },
        ];

        return $ret;

    }

    // Not actually used anywhere, but saving it here for posterity
    public function getLakeUriAttribute()
    {

        $lake_id = $this->lake_guid;

        return env('LAKE_URL', 'https://localhost')
            . '/' . substr($lake_id, 0, 2)
            . '/' . substr($lake_id, 2, 2)
            . '/' . substr($lake_id, 4, 2)
            . '/' .substr($lake_id, 6, 2)
            . '/' .$lake_id;

    }

}
