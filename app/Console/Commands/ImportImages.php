<?php

namespace App\Console\Commands;

use App\Models\Collections\Image;

class ImportImages extends AbstractImportCommand
{

    protected $signature = 'import:images';

    protected $description = "Import all image data from data-service-images";


    public function handle()
    {

        $this->api = env('IMAGES_DATA_SERVICE_URL');

        $this->import( Image::class, 'images' );

    }

    protected function save( $datum, $model )
    {

        $this->info("Importing #{$datum->id}: {$datum->title}");

        // TODO: When we make inbound transformers, provide a toggle between find() & findOrNew()
        $resource = $model::find( $datum->id );

        // For this one, we should ignore entities that don't exist here
        if( !$resource )
        {
            return;
        }

        // TODO: Move this to an inbound transformer
        $metadata = $image->metadata ?? (object) [];

        $metadata->lqip = $datum->lqip;
        $metadata->color = $datum->color;
        $metadata->width = $datum->width;
        $metadata->height = $datum->height;

        $resource->metadata = $metadata;

        $resource->save();

        return $resource;

    }

}
