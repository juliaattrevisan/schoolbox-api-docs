<?php
/**
 * Factory for Schoolbox\Column\IColumnType objects.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Column;

use Schoolbox\Mapper;

class Factory implements IFactory
{
    /**
    * @param $type
    *
    * @return IColumnType
    */
    public function build($type)
    {
        $column = null;
        switch ($type) {
            case Mapper::TYPE_BOOLEAN:
                $column = new Boolean();
                break;
            case Mapper::TYPE_BOOL_ENUM:
                $column = new BoolEnum();
                break;
            case Mapper::TYPE_STRING:
                $column = new Base();
                break;
            case Mapper::TYPE_LOB:
                $column = new Binary();
                break;
            case Mapper::TYPE_INTEGER:
                $column = new Integer();
                break;
            case Mapper::TYPE_DATE:
                $column = new Date();
                break;
            case Mapper::TYPE_DATETIME:
                $column = new Datetime();
                break;
            case Mapper::TYPE_TIME:
                $column = new Time();
                break;
            case Mapper::TYPE_TIMESTAMP:
                $column = new Timestamp();
                break;
            case Mapper::TYPE_TIMESPAN:
                $column = new Interval();
                break;
            case Mapper::TYPE_MODEL:
                $column = new Model();
                break;
            case Mapper::TYPE_COLLECTION:
                $column = new Collection();
                break;
            case Mapper::TYPE_SERIALIZED:
                $column = new Serialized();
                break;
            case Mapper::TYPE_JSON:
                $column = new JSON();
                break;
            case Mapper::TYPE_DECIMAL:
                $column = new Decimal();
                $column->setPlaces(2);
                break;
            case Mapper::TYPE_COLOR:
                $column = new Color();
                break;
            case Mapper::TYPE_SET:
                $column = new Set();
                break;
            default:
                break;
        }
        return $column;
    }
}
