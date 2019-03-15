<?php

namespace App\Models\Web;

use App\Models\WebModel;

/**
 * An occurrence of an event on the website
 */
class EventOccurrence extends WebModel
{

    public $incrementing = false;

    protected $casts = [
        'is_private' => 'boolean',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public static $sourceLastUpdateDateField = 'updated_at';

    public function event()
    {

        return $this->belongsTo('App\Models\Membership\Event');

    }

}
