<?php

namespace App\Models\Collections;

// @TODO: Make Images not Assets, both in the CDS and the DA?
class Image extends Asset
{

    protected $appends = ['iiif_url'];

    // Note: Not all Images are meant to be associated w/ Artworks
    public function artworks()
    {

        return $this->belongsToMany('App\Models\Collections\Artwork');

    }

    /**
     * Turn this model object into a generic array.
     *
     * @TODO Image currently extends Asset, which means it contains IR fields.
     * However, only 1547 of 103518 images are interpretive resources.
     * We need to think more about abstracting away these shared fields.
     * Maybe think of Interpretive Resource as a container..?
     *
     * @return array
     */
    public function transformAsset()
    {

        return [

            // 'type' => $item->type,
            'iiif_url' =>  $this->iiif_url,
            'is_preferred' => (bool) $this->preferred,
            'artwork_ids' => $this->artworks->pluck('citi_id')->all(),
            'artwork_titles' => $this->artworks()->pluck('title'),

        ];

    }

    /**
     * Get the IIIF URL. Corresponds to the `@id` attribute in the image's `/info.json`
     *
     * @TODO Currently, this redirects to a non-existent `info.json'
     *
     * @return string
     */
    public function getIiifUrlAttribute()
    {

        // return env('IIIF_URL', 'https://localhost/iiif') . '/' . $this->lake_guid . '/info.json';
        return env('IIIF_URL', 'https://localhost/iiif') . '/' . $this->lake_guid;

    }

}