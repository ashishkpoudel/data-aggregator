<?php

/*
|--------------------------------------------------------------------------
| DSC Factory
|--------------------------------------------------------------------------
|
| Create models with stub data for all data coming from the DSC
| Data Service.
|
*/

if (!function_exists('dscIdsAndTitle'))
{
    function dscIdsAndTitle($faker, $id = '')
    {

        return [
            'dsc_id' => $id ?: $faker->unique()->randomNumber(4) + 999 * pow(10, 4),
            'title' => ucfirst($faker->words(3, true)),
        ];

    }

    function dscDates($faker)
    {

        return [
            'source_created_at' => $faker->dateTimeThisYear,
            'source_modified_at' => $faker->dateTimeThisYear,
        ];

    }

}


$factory->define(App\Models\Dsc\Publication::class, function (Faker\Generator $faker) {
    return array_merge(
        dscIdsAndTitle($faker),
        [
            'link' => $faker->url,
        ],
        dscDates($faker)
    );
});

$factory->define(App\Models\Dsc\Section::class, function (Faker\Generator $faker) {
    return array_merge(
        dscIdsAndTitle($faker),
        [
            'content' => $faker->paragraphs(10, true),
            'publication_dsc_id' => $faker->randomElement(App\Models\Dsc\Publication::fake()->pluck('dsc_id')->all()),
            'weight' => $faker->randomNumber(2),
            'depth' => $faker->randomDigit,
        ]
    );
});
