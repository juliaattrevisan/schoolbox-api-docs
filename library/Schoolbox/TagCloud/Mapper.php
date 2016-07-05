<?php
/**
 * Tag Cloud Mapper
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\TagCloud;

use Schoolbox\Mapper As BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use Schoolbox\Collection;

class Mapper extends BaseMapper
{

    /** @var string $tableName */
    protected $tableName;

    /** @var string $fieldId */
    protected $fieldId;

    /** @var string $fieldValue */
    protected $fieldValue;

    /**
     * Mapper constructor.
     *
     * @param string $tableName        Table name
     * @param string $fieldId          Column name of tag ID
     * @param string $fieldValue       Column name of tag value
     */
    public function __construct($tableName, $fieldId, $fieldValue)
    {
        $this->tableName = $tableName;
        $this->fieldId = $fieldId;
        $this->fieldValue = $fieldValue;

        parent::__construct();
    }

    /**
     * Retrieves the singleton instance of this class for the specified tag cloud table/fields
     *
     * @param $tableName
     * @param $fieldId
     * @param $fieldValue
     *
     * @return static
     */
    public static function getInstance($tableName, $fieldId, $fieldValue)
    {
        $instance_name = get_called_class()."_".$tableName."_".$fieldId."_".$fieldValue;

        if (!isset(self::$instance[$instance_name])) {
            self::$instance[$instance_name] = new static($tableName, $fieldId, $fieldValue);
            self::$instance[$instance_name]->postSetup();
        }

        return self::$instance[$instance_name];
    }

    /**
     * Setup mapping using the table/field names specified during construction
     */
    protected function mapping()
    {
        $this->hasTable($this->tableName);
        $this->hasModel(Model::class);
        $this->uses($this->fieldId, self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->uses($this->fieldValue, self::TYPE_STRING, null, 'value');
    }

    /**
     * Retrieve all tags containing the specified string
     * @param $string
     * @return Collection
     */
    public function getTagLike($string) {
        $query = "
            SELECT {$this->getColumnString('t')}
            FROM {$this->getTable()} `t`
            WHERE t.{$this->fieldValue} LIKE ?
            ORDER BY t.{$this->fieldValue} ASC
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(array("%{$string}%"));

        $records = new Collection();

        foreach ($statement as $row)
            $records->add($row);

        return $records;
    }

    /**
     * Retrieve value of tag given the ID number
     * @param $id
     * @return null|string
     */
    public function getTagValue($id) {

        try {
            /** @var Model $row */
            $row = self::findOneByColumn($this->fieldId, $id);
            return $row->getValue();
        } catch (MapperException $e) {
            return null;
        }
    }

    /**
     * Retrieve tag ID creating new tag if it does not already exist
     * @param $string
     * @return int
     */
    public function getTagId($string) {

        try {
            /** @var Model $row */
            $row = self::findOneByColumn($this->fieldValue, $string);
            return $row->getId();
        } catch (MapperException $e) {
            // Tag did not already exist, create a new one

            $tag = new Model();
            $tag->setValue($string);
            $this->save($tag);

            return $tag->getId();
        }
    }
}