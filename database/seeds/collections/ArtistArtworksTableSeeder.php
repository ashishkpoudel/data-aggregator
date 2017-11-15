<?php

use App\Models\Collections\Artwork;
use App\Models\Collections\Artist;

class ArtistArtworksTableSeeder extends AbstractSeeder
{

    protected function seed()
    {

        $this->seedRelation( Artwork::class, Artist::class, 'artists' );

    }

}
