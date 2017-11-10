<?php

use Illuminate\Database\Seeder;

use App\Models\Collections\Artwork;
use App\Models\Collections\Artist;

class ArtistArtworksTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        $artworks = Artwork::fake()->get();
        $artistsIds = Artist::fake()->pluck('citi_id')->all();

        foreach ($artworks as $artwork) {

            $ids = [];

            for ($i = 0; $i < rand(2,4); $i++) {

                $id = $artistsIds[array_rand($artistsIds)];

                if (!in_array($id, $ids)) {
                    $artwork->artists()->attach($id);
                    $ids[] = $id;
                }

            }

        }

    }

}
