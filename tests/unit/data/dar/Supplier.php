<?php

namespace tests\unit\data\dar;

use \my6uot9\dynamicAr\DynamicActiveQuery;
use tests\unit\data\BaseRecord;

class Supplier extends BaseRecord
{

    public static function dynamicColumn() : string
    {
        return 'dynamic_columns';
    }

    /**
     * @return DynamicActiveQuery
     */
    public function getProducts()
    {
        return $this->hasMany(Product::className(), ['supplier_id' => 'id']);
    }
}
