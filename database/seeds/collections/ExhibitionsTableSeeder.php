<?php

use App\Models\Collections\Exhibition;
use App\Models\Collections\Artwork;
use App\Models\Collections\CorporateBody;

class ExhibitionsTableSeeder extends AbstractSeeder
{

    protected function seed()
    {

        factory( Exhibition::class, 25 )->create();

        $this->seedRelation( Exhibition::class, Artwork::class, 'artworks' );

        $this->seedRelation( Exhibition::class, CorporateBody::class, 'venues' );

    }

}
