<?php
/**
 * @author danil danil.kabluk@gmail.com
 */

namespace tests\unit\data\dar;

use \my6uot9\dynamicAr\DynamicActiveQuery;
use tests\unit\data\BaseRecord;

class MissingDynColumn extends BaseRecord
{

    public $customColumn;

    public static function dynamicColumn() : string
    {
        return 'dynamic_columnsss';
    }
}
