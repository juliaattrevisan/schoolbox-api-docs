<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\Attachment;

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
        $this->hasTable('pastoral_attachment');
        $this->hasModel('\\Schoolbox\\Pastoral\\Attachment\\Model');

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
                'column'   => 'hash',
                'property' => 'hash',
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

        $this->hasSoftDeleteDateField('datetime_deleted');
    }

    /**
     * Retrieves all records relating to a particular pastoral record
     *
     * @param  int $pastoralId
     *
     * @return \Schoolbox\Collection
     */
    public function findByPastoralId($pastoralId) {
        $collection = new Collection();
        $userMapper = new \Schoolbox\UserMapper;

        $query = "
			SELECT
				{$this->getColumnString('p')}
			FROM
				`{$this->getTable()}` `p`
			WHERE
				`p`.`datetime_deleted` IS NULL AND
				`p`.`pastoral_id` = :pastoralId
			";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':pastoralId', $pastoralId);
        $statement->execute();

        foreach ($statement as $row) {
            $pastoral = $this->hydrate($row, 'p');
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

        if ($mainModel instanceof \Schoolbox\Pastoral\Attachment\Model) {
            return $mainModel;
        }

        throw new \Schoolbox\Mapper\Exception("Instance of object not found");
    }
}
