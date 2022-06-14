<?php

/**
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox;

use PDO;
use Schoolbox\Mapper\Exception\CallbackException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Sync\Effect\Type\Mapper as UPSEffectMapper;

/**
 * Class CampusMapper
 *
 * @method Campus hydrate(array $row, $alias = '', $callback = null)
 * @method GenericCollection|Campus[] findAll($options = [])
 * @method GenericCollection|Campus[] findByColumn($columnName, $columnValue, $options = [])
 * @method Campus findOneByColumn($columnName, $columnValue, $options = [])
 */
class CampusMapper extends Mapper
{

    /**
     * @inheritDoc
     *
     * @throws \InvalidArgumentException
     */
    protected function mapping()
    {
        $this->hasTable('luCampus');
        $this->hasModel(Campus::class);

        $this->uses('id', Mapper::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', Mapper::TYPE_STRING);
        $this->uses('ext_timetable_code', Mapper::TYPE_STRING);
        $this->uses('timetable_day', Mapper::TYPE_STRING);
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->uses('updated_by', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'campusHasUsers']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'removeDeletedCampusFromUPSRules']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'validateName']);
    }

    /**
     * This callback is to ensure the campus does not have
     * any user assigned to it before soft deletion
     *
     * @param Campus $campus
     * @return bool
     * @throws Mapper\Exception
     */
    public function campusHasUsers(Campus $campus): bool
    {
        if ($this->findUsersInCampus($campus)->count() > 0) {
            return false;
        }
        return true;
    }

    /**
     * Removes deleted campus from any UPS rule
     *
     * @param Campus $campus
     * @return bool
     *
     */
    public function removeDeletedCampusFromUPSRules(Campus $campus): bool
    {
        (new UPSEffectMapper())->removeRules('campusCollection', $campus->getId());
        (new UPSEffectMapper())->removeRules('campusCollectionRemove', $campus->getId());
        return true;
    }


    /**
     * Get a list of campuses that is accessible by $user
     *
     * According to the pipe:
     * "I know what this is. This allows you to control which campuses you can post events to.
     * No idea what userAccess.sid = 0 means.
     * $type actually means Read/Write/Moderate, but contextually could mean anything."
     *
     * @param User $user
     * @param int  $accessType
     * @return GenericCollection|Campus[]
     * @throws Mapper\Exception
     */
    public function getAvailableCampusesForUser(User $user, $accessType = UserAccess::ACCESS_READ)
    {
        $calendarService = UserAccess::SERVICE_CALENDAR;

        $query = "
				SELECT *
				FROM luCampus
				INNER JOIN userAccess ON luCampus.id = userAccess.sid
				WHERE userAccess.service = :calendarService
				AND userAccess.uid = :userId
				AND type >= :accessType
				AND luCampus.deleted_at IS NULL
                ORDER BY luCampus.sequence
			";

        $statement = $this->database->prepare($query);

        $statement->execute(
            [
                'calendarService' => $calendarService,
                'userId'          => $user->getId(),
                'accessType'      => $accessType,
            ]
        );

        $collection = $this->createCollection();

        foreach ($statement as $row) {
            $campus = $this->hydrate($row);
            $collection->add($campus);
        }

        return $collection;
    }


    /**
     * @param Campus $campus
     * @return GenericCollection|User[]
     * @throws Mapper\Exception
     */
    public function findUsersInCampus(Campus $campus)
    {
        $uMap = UserMapper::getInstance();

        $stmt = $this->database->prepare(
            "
            SELECT {$uMap->getColumnString('u')}
            FROM userCampus c
            RIGHT JOIN `user` u on c.uid = u.id
            WHERE c.campus = :campus
            AND u.deleted_at IS NULL
        "
        );

        $stmt->execute(
            [
                'campus' => $campus->getId(),
            ]
        );

        $c = $uMap->createCollection();

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $c->add($uMap->hydrate($r, 'u'));
        }

        return $c;
    }

    /**
     * Find all campuses matching a certain name
     *
     * @param string $name
     * @return GenericCollection|Campus[]
     * @throws Mapper\Exception
     */
    public function findCampuses($name = '')
    {
        $allCampuses = $this->createCollection();

        /* if $name is empty, the LIKE returns everything anyway
         */
        $query = "
            SELECT
                {$this->getColumnString('c')}
            FROM
                `{$this->getTable()}` c
            WHERE c.name LIKE :name AND c.deleted_at IS NULL
            ORDER BY c.name
        ";

        $statement = $this->database->prepare($query);
        $statement->execute(
            [
                'name' => "%{$name}%",
            ]
        );

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $allCampuses->add($this->hydrate($row, 'c'));
        }

        return $allCampuses;
    }

    /**
     * Find the campuses with which this user is involved
     *
     * @param User $user
     * @return GenericCollection|Campus[]
     * @throws Mapper\Exception
     */
    public function findCampusInvolvement(User $user)
    {
        $pb = $this->getPredicateBuilder();
        $pb->equals('userCampus.uid', $user->getId());

        $query = "
            SELECT
            	userCampus.*,
            	{$this->getColumnString('c')}
            FROM userCampus
            INNER JOIN luCampus c ON userCampus.campus = c.id AND c.deleted_at IS NULL
        ";
        $query .= ' WHERE ' . implode(' AND ', $pb->getWhere());

        $statement = $this->getDatabase()->query($query, $pb->getParams());

        $campusCollection = $this->createCollection();
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $campus = $this->hydrate($row, 'c');
            $campusCollection->setOffset($campus->getId(), $campus);
        }
        return $campusCollection;
    }

    /** @inheritDoc */
    protected function getDefaultOrderByParams(): array
    {
        return ['sequence' => 'ASC'];
    }

    /**
     * Validates that the name is not empty and is unique
     *
     * @param Campus $model
     * @return bool
     * @throws CallbackException
     */
    protected function validateName(Campus $model): bool
    {
        if (trim($model->getName() ?? '') === '') {
            throw new CallbackException("Campus name cannot be empty");
        }
        if (!$this->isUnique(['name' => $model->getName()], $model->getId())) {
            throw new CallbackException(
                sprintf(
                    'A Campus with the title "%s" already exists, you must have a unique title',
                    $model->getName()
                )
            );
        }

        return true;
    }
}
