<?php

namespace App\Http\Transformers;

class DscTransformer extends ApiTransformer
{

    protected function transformIdsAndTitle($item)
    {

        if ($this->excludeIdsAndTitle)
        {

            return [];

        }

        return [
            'id' => $item->getAttributeValue($item->getKeyName()),
            'title' => $item->title,
        ];

    }

    protected function transformDates($item)
    {

        if ($this->excludeDates)
        {

            return [];

        }

        return [
            'last_updated_source' => $item->api_modified_at->toDateTimeString(),
            'last_updated' => $item->updated_at->toDateTimeString(),
        ];

    }   

}