<?php

namespace App\Http\Transformers;

use App\Collections\ArtworkDate;

class ArtworkDateTransformer extends CollectionsTransformer
{

    public $excludeDates = true;
    public $excludeIdsAndTitle = true;

    /**
     * Turn this item object into a generic array.
     *
     * @param  \App\Agent  $item
     * @return array
     */
    public function transformFields($item)
    {
        return [
            'date' => $item->date->toDateString(),
            'qualifier' => $item->qualifier,
            'preferred' => (bool) $item->preferred,
        ];
    }
}