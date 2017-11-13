<?php

use App\Models\Collections\Gallery;
use App\Models\Collections\Category;

class GalleryCategoriesTableSeeder extends AbstractSeeder
{

    protected function seed()
    {

        $galleries = Gallery::fake()->get();
        $categoryIds = Category::fake()->pluck('citi_id')->all();

        foreach ($galleries as $gallery) {

            for ($i = 0; $i < rand(2,4); $i++) {
                $gallery->categories()->attach($categoryIds[array_rand($categoryIds)]);
            }

        }

    }

}
