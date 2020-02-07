<?php
/**
 * Tag Cloud Mapper
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\TagCloud;

use Schoolbox\Mapper As BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use Schoolbox\Collection;

class Mapper extends BaseMapper implements BaseMapper\UsableAsTagSource
{

    const SCHOOL_PREFIX = 'school:';

    /** @var string $tableName */
    protected $tableName;

    /** @var string $fieldId */
    protected $fieldId;

    /** @var string $fieldName */
    protected $fieldName;

    /**
     * Mapper constructor.
     *
     * @param string $tableName        Table name
     * @param string $fieldId          Column name of tag ID
     * @param string $fieldName       Column name of tag name
     */
    public function __construct($tableName, $fieldId, $fieldName)
    {
        $this->tableName = $tableName;
        $this->fieldId = $fieldId;
        $this->fieldName = $fieldName;

        parent::__construct();
    }

    /**
     * Retrieves the singleton instance of this class for the specified tag cloud table/fields
     *
     * @param $tableName
     * @param $fieldId
     * @param $fieldName
     *
     * @return static
     */
    public static function getInstanceByName($tableName, $fieldId, $fieldName)
    {
        $instance_name = get_called_class()."_".$tableName."_".$fieldId."_".$fieldName;

        if (!isset(self::$instance[$instance_name])) {
            self::$instance[$instance_name] = new static($tableName, $fieldId, $fieldName);
            self::$instance[$instance_name]->postSetup();
        }

        return self::$instance[$instance_name];    }

    /**
     * Setup mapping using the table/field names specified during construction
     */
    protected function mapping()
    {
        $this->hasTable($this->tableName);
        $this->hasModel(Model::class);
        $this->uses($this->fieldId, self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->uses($this->fieldName, self::TYPE_STRING, null, 'name');
        $this->uses('created_at', self::TYPE_DATETIME);
        $this->uses('updated_at', self::TYPE_DATETIME);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * Retrieve all tags containing the specified string
     * @param string $string
     * @param int $offset
     * @param int $limit
     * @return Collection
     */
    public function getTagLike($string, $offset = 0, $limit = 0)
    {
        $query = "
            SELECT {$this->getColumnString()}
            FROM {$this->getTable()}
            WHERE {$this->fieldName} LIKE :search
            AND `deleted_at` IS NULL
            ORDER BY {$this->fieldName} ASC
        ";
        if ($offset > 0) {
            $query .= ' LIMIT :limit OFFSET :offset';
        } elseif ($limit > 0) {
            $query .= ' LIMIT :limit';
        }
        $statement = $this->getDatabase()->prepare($query);
        $statement->bindValue('search', '%'.$this->getDatabase()->escapeForLike($string).'%', \PDO::PARAM_STR);
        if ($offset > 0) {
            $statement->bindValue('offset', intval($offset), \PDO::PARAM_INT);
            $statement->bindValue('limit', intval($limit), \PDO::PARAM_INT);
        } elseif ($limit > 0) {
            $statement->bindValue('limit', intval($limit), \PDO::PARAM_INT);
        }
        $statement->execute();

        $records = new Collection();

        foreach ($statement as $row) {
            $records->add([
                'id' => $row[$this->fieldId],
                'name' => $row[$this->fieldName]
            ]);
        }

        return $records;
    }

    /**
     * Retrieve name of tag given the ID number
     * @param $id
     * @return null|string
     */
    public function getTagName($id) {

        try {
            /** @var Model $row */
            $row = self::findOneByColumn($this->fieldId, $id);
            return $row->getName();
        } catch (MapperException $e) {
            return null;
        }
    }

    /**
     * Retrieve tag ID creating new tag if it does not already exist
     * @param $string
     * @param bool $create
     * @return null|int
     */
    public function getTagId($string, $create = true) {

        try {
            /** @var Model $row */
            $row = self::findOneByColumnList(
                array(
                    $this->fieldName => $string,
                    'deleted_at' => NULL,
                ));
            return $row->getId();
        } catch (MapperException $e) {
            if ($create) {
                // Tag did not already exist, and we want to auto-create a new one
                $tag = new Model();
                $tag->setName($string);
                $tag->setCreatedAt(\Alaress_Date_Format::now());
                $tag->setUpdatedAt(\Alaress_Date_Format::now());
                $this->save($tag);

                return $tag->getId();
            } else {
                // Not creating one, just return null
                return null;
            }
        }
    }

    /**
     * @param User        $user
     * @param string      $keyword
     * @param int         $offset
     * @param int         $limit
     * @return Collection|Model[]
     */
    public function findForTag(User $user, $keyword, $offset = 0, $limit = 0)
    {
        return $this->getTagLike($keyword, $offset, $limit);
    }
}
