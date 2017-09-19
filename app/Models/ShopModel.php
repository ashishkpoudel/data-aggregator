<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\ElasticSearchable;

class ShopModel extends BaseModel
{

    use ElasticSearchable;

    protected $source = 'Shop';

    protected $primaryKey = 'shop_id';

    protected $dates = ['source_created_at', 'source_modified_at'];

}