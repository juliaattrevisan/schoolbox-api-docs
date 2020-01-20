<?php
/**
 * Description of the file and what its purpose is
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox;

use \PDO;
use Schoolbox\Permission\Access\UserAccess;

/**
 * @method Campus hydrate(array $row, $alias = '', $callback = null)
 * @method GenericCollection|Campus[] findAll($options = array())
 * @method GenericCollection|Campus[]findByColumn($columnName, $columnValue, $options = array())
 */
class CampusMapper extends Mapper {

    protected function mapping ()
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
	 * @param int $accessType
	 * @return GenericCollection|Campus[]
	 */
	public function getAvailableCampusesForUser(User $user, $accessType = UserAccess::ACCESS_READ)
    {
		$calendarService = UserAccess::SERVICE_CALENDAR;

		/* Superusers get access to all campuses
		 */
		if ($_SESSION['su']) {
			$collection = $this->findAll();
		}
		else {
			$query = "
				SELECT *
				FROM luCampus
				INNER JOIN userAccess ON luCampus.id = userAccess.sid
				WHERE userAccess.service = :calendarService
				AND userAccess.uid = :userId
				AND type >= :accessType
			";

			$statement = $this->database->prepare($query);

			$statement->bindParam(':calendarService', $calendarService);
			$statement->bindParam(':userId', $user->getId());
			$statement->bindParam(':accessType', $accessType);
			$statement->execute();

			$collection = $this->createCollection();

			foreach ($statement as $row) {
				$campus = $this->hydrate($row);

				$collection->add($campus);
			}
		}

		return $collection;
	}


    /**
     * @param Campus $campus
     * @return GenericCollection|User[]
     */
    public function findUsersInCampus(Campus $campus)
    {
        $uMap = UserMapper::getInstance();

        $stmt = $this->database->prepare("
            SELECT {$uMap->getColumnString('u')}
            FROM userCampus c
            RIGHT JOIN `user` u on c.uid = u.id
            WHERE c.campus = :campus
            AND u.deleted_at IS NULL
        ");

        $stmt->execute(array(
            'campus' => $campus->getId()
        ));

        $c = $uMap->createCollection();

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $c->add($uMap->hydrate($r, 'u'));
        }

        return $c;
    }

	/**
	 * Find all campuses matching a certain name
	 *
	 * @param $name
	 * @return GenericCollection|Campus[]
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
            WHERE c.name LIKE :name
            ORDER BY c.name
        ";

		$statement = $this->database->prepare($query);
		$statement->execute([
			'name' => "%{$name}%"
		]);

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
            INNER JOIN luCampus c ON userCampus.campus = c.id
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
}
