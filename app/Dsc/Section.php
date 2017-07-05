<?php

namespace App\Dsc;

class Section extends DscModel
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['dsc_id', 'title'];

        public function publication()
    {

        return $this->belongsTo('App\Dsc\Publication');

    }

}
