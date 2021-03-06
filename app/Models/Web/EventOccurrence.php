<?php

namespace App\Models\Web;

use App\Models\WebModel;

/**
 * An occurrence of an event on the website
 */
class EventOccurrence extends WebModel
{

    public $incrementing = true;

    protected $casts = [
        'is_private' => 'boolean',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function event()
    {

        return $this->belongsTo('App\Models\Membership\Event');

    }

}
