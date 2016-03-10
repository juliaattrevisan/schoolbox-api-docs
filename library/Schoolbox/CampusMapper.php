<?php
/**
 * Description of the file and what its purpose is
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox;
use Schoolbox;
use \PDO;

class CampusMapper extends Mapper {

    protected function mapping () {

        $this->hasTable('luCampus');
        $this->hasModel('\Schoolbox\Campus');

		$this->hasMap(array(
			'column' => 'id',
			'property' => 'id',
			'type' => Mapper::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column' => 'name',
			'property' => 'name',
			'type' => Mapper::TYPE_STRING,
		));
		$this->hasMap(array(
			'column' => 'ext_timetable_code',
			'property' => 'extTimetableCode',
			'type' => Mapper::TYPE_STRING,
		));
		$this->hasMap(array(
			'column' => 'timetable_day',
			'property' => 'timetableDay',
			'type' => Mapper::TYPE_STRING,
		));
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
	 * @return array
	 */
	public function getAvailableCampusesForUser(\Schoolbox\User $user, $accessType = 1) {
		$calendarService = CALENDAR_SERVICE;

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
				AND type > :accessType
			";

			$statement = $this->database->prepare($query);

			$statement->bindParam(':calendarService', $calendarService);
			$statement->bindParam(':userId', $user->getId());
			$statement->bindParam(':accessType', $accessType);
			$statement->execute();

			$collection = new Collection();

			foreach ($statement as $row) {
				$campus = $this->hydrate($row);

				$collection->add($campus);
			}
		}

		return $collection;
	}


    /**
     * @param Campus $campus
     * @return Collection|User[]
     */
    public function findUsersInCampus(Campus $campus)
    {
        $uMap = UserMapper::getInstance();

        $stmt = $this->database->prepare("
            SELECT {$uMap->getColumnString('u')}
            FROM userCampus c
            RIGHT JOIN `user` u on c.uid = u.id
            WHERE c.campus = :campus
        ");

        $stmt->execute(array(
            'campus' => $campus->getId()
        ));

        $c = new Collection();

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $c->add($uMap->hydrate($r, 'u'));
        }

        return $c;
    }

	/**
	 * Find all campuses matching a certain name
	 *
	 * @param $name
	 * @return \Schoolbox\Collection
	 */
	public function findCampuses($name = '') {
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
	 * @return Collection
	 */
	public function findCampusInvolvement(User $user)
	{
		$query = "
            SELECT
            	userCampus.*,
            	{$this->getColumnString('c')}
            FROM userCampus
            INNER JOIN luCampus c ON userCampus.campus = c.id
            WHERE userCampus.uid = :userId
        ";

		$statement = $this->database->prepare($query);
		$statement->execute([
			'userId' => $user->getId()
		]);

		$collection = new Collection();

		while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
			$campus = $this->hydrate($row, 'c');

			$collection->setOffset($campus->getId(), [
				'defaultCampus' => $row['defaultCampus'],
				'campus' => $campus,
			]);
		}

		return $collection;
	}
}