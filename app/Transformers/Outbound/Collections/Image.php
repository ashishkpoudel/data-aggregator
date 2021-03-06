<?php

namespace App\Transformers\Outbound\Collections;

use App\Transformers\Outbound\Collections\Asset as BaseTransformer;

class Image extends BaseTransformer
{

    protected function getAssetFields()
    {
        return [
            'iiif_url' => [
                'doc' => 'IIIF URL of this image',
                'type' => 'url',
                'elasticsearch' => 'keyword',
            ],
            'width' => [
                'doc' => 'Native width of the image',
                'type' => 'number',
                'elasticsearch' => 'integer',
                'value' => function ($item) {
                    return $item->metadata->width ?? null;
                },
            ],
            'height' => [
                'doc' => 'Native height of the image',
                'type' => 'number',
                'elasticsearch' => 'integer',
                'value' => function ($item) {
                    return $item->metadata->height ?? null;
                },
            ],
            'lqip' => [
                'doc' => 'Low-quality image placeholder (LQIP). Currently a 5x5-constrained, base64-encoded GIF.',
                'type' => 'text',
                'elasticsearch' => [
                    'mapping' => [
                        'enabled' => false, // Exclude from indexing, retrievable via _source
                    ]
                ],
                'value' => function ($item) {
                    return $item->metadata->lqip ?? null;
                },
            ],
            'colorfulness' => [
                'doc' => 'Unbounded positive float representing an abstract measure of colorfulness.',
                'type' => 'float',
                'elasticsearch' => [
                    'mapping' => [
                        'type' => 'scaled_float',
                        'scaling_factor' => 10000,
                    ]
                ],
                'value' => function ($item) {
                    return $item->metadata->colorfulness ?? null;
                },
            ],
            'color' => [
                'doc' => 'Dominant color of this image in HSL',
                'type' => 'object',
                'elasticsearch' => [
                    'mapping' => [
                        'type' => 'object',
                        'properties' => [
                            'population' => [ 'type' => 'integer' ],
                            'percentage' => [ 'type' => 'float' ],
                            'h' => [ 'type' => 'integer' ],
                            's' => [ 'type' => 'integer' ],
                            'l' => [ 'type' => 'integer' ],
                        ],
                    ],
                ],
                'value' => function ($item) {
                    return $item->metadata->color ?? null;
                },
            ],
            'fingerprint' => [
                'doc' => 'Image hashes: aHash, dHash, pHash, wHash',
                'type' => 'object',
                // TODO: Elasticsearch is bad at string distance
                'elasticsearch' => [
                    'mapping' => [
                        'type' => 'object',
                        'properties' => [
                            'ahash' => [ 'type' => 'keyword' ],
                            'dhash' => [ 'type' => 'keyword' ],
                            'phash' => [ 'type' => 'keyword' ],
                            'whash' => [ 'type' => 'keyword' ],
                        ],
                    ],
                ],
                'value' => function ($item) {
                    return $item->metadata->fingerprint ?? null;
                },
            ],
        ];
    }

}
