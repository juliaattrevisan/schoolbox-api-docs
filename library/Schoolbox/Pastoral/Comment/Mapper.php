<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\Comment;

use PDO;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Pastoral\Comment\Model as PastoralCommentModel;

class Mapper extends BaseMapper
{
    public function __construct()
    {
        $this->setErrorMapper(ErrorMapper::getInstance());
        Parent::__construct();
    }

    protected function mapping()
    {
        $this->hasTable('pastoral_comment');
        $this->hasPrototype(new PastoralCommentModel);

        $this->hasMap(
            array(
                'column'     => 'id',
                'property'   => 'id',
                'type'       => Mapper::TYPE_INTEGER,
                'identifier' => true
            )
        );

        $this->hasMap(
            array(
                'column'   => 'pastoral_id',
                'property' => 'pastoralId',
                'type'     => Mapper::TYPE_INTEGER,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'owner_id',
                'property' => 'owner',
                'type'     => Mapper::TYPE_MODEL,
                'join'     => 'owner'
            )
        );

        $this->hasJoin(
            'owner', array(
                'mapper'    => '\\Schoolbox\\UserMapper',
                'this_key'  => 'owner_id',
                'other_key' => 'id',
            )
        );

        $this->hasMap(
            array(
                'column'   => 'body',
                'property' => 'body',
                'type'     => Mapper::TYPE_STRING,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'datetime_posted',
                'property' => 'postDate',
                'type'     => Mapper::TYPE_DATETIME,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'datetime_modified',
                'property' => 'modifiedDate',
                'type'     => Mapper::TYPE_DATETIME,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'datetime_deleted',
                'property' => 'deletedDate',
                'type'     => Mapper::TYPE_DATETIME,
            )
        );

        $this->hasSoftDeleteDateField('datetime_deleted');
    }

    /**
     * Retrieves all access relating to a particular pastoral id
     *
     * @param  int $pastoralId
     *
     * @return \Schoolbox\Collection
     */
    public function findByPastoralId($pastoralId) {
        $collection = new Collection();
        $userMapper = UserMapper::getInstance();

        $query = "
			SELECT
				{$this->getColumnString('a')},
				{$this->getJoinColumnString('owner', 'u')}
			FROM
				`{$this->getTable()}` `a`
				LEFT JOIN {$this->getJoinSql('owner', 'a', 'u')}
			WHERE
				`a`.`datetime_deleted` IS NULL AND
				`a`.`pastoral_id` = :pastoralId
			";


        $statement = $this->database->prepare($query);
        $statement->bindParam(':pastoralId', $pastoralId);
        $statement->execute();

        foreach ($statement as $row) {
            $pastoral = $this->hydrate($row, 'a');
            $owner = $userMapper->hydrate($row, 'u');

            $pastoral->setOwner($owner);
            $collection->add($pastoral);
        }

        return $collection;
    }

    /**
     * Retrieves a model by id
     *
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     *
     * @param  int $identifierValue
     *
     * @return object
     */
    public function findById($identifierValue)
    {
        $t = 0;

        // the main model we'll ultimately be populating
        $mainModel = null;

        /** @var Mapper[] $joinMappers */
        $joinMappers   = array();
        $columnStrings = array($this->getColumnString("t$t"));
        $joinSqls      = array();
        $propertyMap   = array();
        $joinNames     = array();

        $identifier       = $this->getIdentifier();
        $identifierColumn = $identifier['column'];

        foreach ($this->map as $map) {
            if (empty($map['join'])) {
                continue;
            }
            // increment our little `t` counter
            ++$t;
            $name = $map['join'];

            $alias = "t$t";

            $propertyMap[$alias] = $map['property'];
            $joinNames[$alias]   = $name;
            $joinMappers[$alias] = $this->getJoinMapper($name);
            if (!$joinMappers[$alias] instanceof Mapper) {
                continue;
            }
            $columnStrings[$alias] = $joinMappers[$alias]->getColumnString($alias);
            $joinSqls[$alias]      = $this->getJoinSql($name, 't0', $alias);
        }

        $columns = implode(', ', $columnStrings);
        $joins   = implode(' LEFT JOIN ', $joinSqls);
        if (strlen($joins)) {
            $joins = "LEFT JOIN $joins";
        }

        $stmt = $this->database->prepare("
			SELECT $columns FROM `{$this->getTable()}` t0 {$joins} WHERE `t0`.`{$identifierColumn}` = :id_value
		");

        $stmt->execute(array('id_value' => $identifierValue));

        // there will be more than one record returned by this query
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (empty($mainModel)) {
                /** @var Model $mainModel */
                $mainModel = $this->hydrate($r, 't0');
                // set blank collections
            }

            foreach ($joinMappers as $alias => $mapper) {
                $subIdentifier = $mapper->getIdentifier();
                $subColumn     = $subIdentifier['column'];

                $field = "$alias.$subColumn";

                if (is_null($r[$field])) {
                    continue;
                }

                $entity = $mapper->hydrate($r, $alias);

                $property = $propertyMap[$alias];
                $joinName = $joinNames[$alias];

                // singular value
                if (empty($this->joins[$joinName]['multi'])) {
                    $setEntity = $this->getSetMethodName($property);

                    $mainModel->$setEntity($entity);
                } // multi-value...this is a bit circuitous...
                else {
                    $getEntity = $this->getGetMethodName($property);

                    if (!$mainModel->$getEntity() instanceof Collection) {
                        $setEntity = $this->getSetMethodName($property);
                        $mainModel->$setEntity(new Collection());
                    }
                    $mainModel->$getEntity()->add($entity);
                }
            }
        }

        if ($mainModel instanceof Model) {
            return $mainModel;
        }

        $this->getErrorMapper()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
    }

    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * @param ErrorMapper $errorMapper
     * @return $this
     */
    public function setErrorMapper($errorMapper) {
        $this->errorMapper = $errorMapper;
        return $this;
    }

    /**
     * @return ErrorMapper
     */
    public function getErrorMapper() {
        return $this->errorMapper;
    }
}