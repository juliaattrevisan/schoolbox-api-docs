<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\Pastoral;

use PDO;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage;
use Schoolbox\User;

class Mapper extends BaseMapper
{
    protected function mapping()
    {
        $this->hasTable('pastoral');
        $this->hasModel('\\Schoolbox\\Pastoral\\Pastoral\\Model');

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
                'column'     => 'type_id',
                'property'   => 'typeId',
                'type'       => Mapper::TYPE_INTEGER,
            )
        );

        $this->hasMap(
            array(
                'column'     => 'severity_id',
                'property'   => 'severityId',
                'type'       => Mapper::TYPE_INTEGER,
            )
        );

        $this->hasMap(
            array(
                'column'     => 'external_id',
                'property'   => 'externalId',
                'type'       => Mapper::TYPE_INTEGER,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'heading',
                'property' => 'heading',
                'type'     => Mapper::TYPE_STRING,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'private',
                'property' => 'private',
                'type'     => Mapper::TYPE_BOOLEAN,
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

        $this->hasMap(
            array(
                'column'   => 'body',
                'property' => 'body',
                'type'     => Mapper::TYPE_STRING,
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

        $this->hasMap(
            array(
                'column'     => 'student_id',
                'property'   => 'studentId',
                'type'       => Mapper::TYPE_INTEGER,
            )
        );

        $this->hasJoin(
            'owner', array(
                'mapper'    => '\\Schoolbox\\UserMapper',
                'this_key'  => 'owner_id',
                'other_key' => 'id',
            )
        );

        $this->hasSoftDeleteDateField('datetime_deleted');
    }

    /**
     * Retrieves all records relating to a particular student
     *
     * @param  int $studentId
     * @return \Schoolbox\Collection
     */
    public function findByStudentId($studentId) {
        $collection = new Collection();
        $userMapper = new \Schoolbox\UserMapper;

        $query = "
			SELECT
				{$this->getColumnString('p')},
				{$this->getJoinColumnString('owner', 'u')}
			FROM
				`{$this->getTable()}` `p`
				LEFT JOIN {$this->getJoinSql('owner', 'p', 'u')}
			WHERE
				`p`.`datetime_deleted` IS NULL AND
				`p`.`student_id` = :studentId
		    ORDER BY
		    	`p`.`datetime_posted` DESC
        ";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':studentId', $studentId);
        $statement->execute();

        foreach ($statement as $row) {
            $record = $this->hydrate($row, 'p');
            $owner = $userMapper->hydrate($row, 'u');

            $record->setOwner($owner);
            $collection->add($record);
        }

        return $collection;
    }


    /**
     * Retrieves record by ID with owner information
     *
     * @param  int $id
     * @return \Schoolbox\Pastoral\Pastoral\Model
     */
    public function findByIdWithOwner($id) {

        $userMapper = new \Schoolbox\UserMapper;

        $query = "
			SELECT
				{$this->getColumnString('p')},
				{$this->getJoinColumnString('owner', 'u')}
			FROM
				`{$this->getTable()}` `p`
				LEFT JOIN {$this->getJoinSql('owner', 'p', 'u')}
			WHERE
				`p`.`datetime_deleted` IS NULL AND
				`p`.`id` = :id
		    ORDER BY
		    	`p`.`datetime_posted` DESC
        ";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':id', $id);
        $statement->execute();

        foreach ($statement as $row) {
            $record = $this->hydrate($row, 'p');
            $owner = $userMapper->hydrate($row, 'u');

            $record->setOwner($owner);
        }

        return $record;
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

        $stmt = $this->database->prepare(
            "
			SELECT $columns FROM `{$this->getTable()}` t0 {$joins} WHERE `t0`.`{$identifierColumn}` = :id_value
		"
        );

        $stmt->execute(array('id_value' => $identifierValue));

        // there will be more than one record returned by this query
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (empty($mainModel)) {
                /** @var Article $mainModel */
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

        if ($mainModel instanceof \Schoolbox\Pastoral\Pastoral\Model) {
            return $mainModel;
        }

        throw new \Schoolbox\Mapper\Exception("Instance of object not found");
    }
}
