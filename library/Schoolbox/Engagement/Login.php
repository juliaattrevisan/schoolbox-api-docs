<?php

/**
 * Created by PhpStorm.
 * User: jleckie
 * Date: 20/08/15
 * Time: 2:51 PM
 */

namespace Schoolbox\Engagement;

use Alaress\Database\Connector\IConnector;
use Alaress_Date_Format;
use Schoolbox\Collection;

class Login
{
    // Fallback value if session length not configured in DB
    const SESSION_LENGTH_DEFAULT = 1800;

    /** @var IConnector */
    private $database;


    /**
     * Number of seconds that sessions exists for
     *
     * @var int
     */
    private $sessionLength;


    /**
     * Constructor - sets up database
     *
     * @param IConnector  $database
     * @param null|string $sessionLength
     */
    public function __construct(IConnector $database, ?string $sessionLength)
    {
        $this->database      = $database;
        $this->sessionLength = null === $sessionLength ? self::SESSION_LENGTH_DEFAULT : (int)$sessionLength;
    }

    public function loginByGroup(Alaress_Date_Format $start, Alaress_Date_Format $end)
    {
        $query = '
             SELECT
                `groups`.name,
                `groups`.id,
                (SELECT COUNT(*) FROM login_log JOIN user ON login_log.username=user.username AND  `user`.created_at <=  :end AND (`user`.deleted_at IS NULL OR `user`.deleted_at >= :start) WHERE login=1 AND date >= :start AND date <= :end AND user.group_id = `groups`.id ) AS total
                FROM `groups`
            WHERE   `groups`.deleted_at IS NULL';

        $statement = $this->database->prepareQuery($query);
        $statement->execute([
            'start' => $start->getDatabase(),
            'end'   => $end->getDatabase(),
        ]);
        return $statement->fetchAll();
    }


    /**
     * @param Alaress_Date_Format $start
     * @param Alaress_Date_Format $end
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function uniqueLoginByGroup(Alaress_Date_Format $start, Alaress_Date_Format $end): array
    {
        $query = '
            SELECT `groups`.`name`,
            (
                SELECT COUNT(DISTINCT(`user`.username)) FROM `user`
                INNER JOIN login_log ON `user`.username = login_log.username
                WHERE group_id = `groups`.id AND login=1 AND `date` >= :start AND `date` <= :end AND `user`.created_at <=  :end AND
                      (`user`.deleted_at IS NULL OR `user`.deleted_at > :start)
            ) AS total
            FROM `groups`
            WHERE `groups`.deleted_at IS NULL';

        $statement = $this->database->prepareQuery($query);
        $statement->execute([
            'start' => $start->getDatabase(),
            'end'   => $end->getDatabase(),
        ]);
        return $statement->fetchAll();
    }


    /**
     * Returns a summary of total users in system, and number of active sessions
     * @param null|Alaress_Date_Format $end
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function userSummary(?Alaress_Date_Format $end): array
    {
        if (null === $end) {
            $userWhere = '`user`.deleted_at IS NULL';
            $params    = [];
        } else {
            $start = clone $end;
            $start->startOfMonth();
            $userWhere = '`user`.created_at <= :end AND (`user`.deleted_at IS NULL OR `user`.deleted_at >= :start)';
            $params    = [
                'start' => $start->getDatabase(),
                'end'   => $end->getDatabase(),
            ];
        }

        $query = "
                SELECT `groups`.id, `groups`.`name`, COUNT(*) total
                FROM `groups`
                JOIN `user` ON `groups`.id=`user`.group_id
                WHERE {$userWhere} AND `groups`.deleted_at IS NULL
                GROUP BY `groups`.id
                ORDER BY `groups`.`name`";

        $statement = $this->database->prepareQuery($query);
        $statement->execute($params);
        $summary = new Collection();

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($group = $statement->fetch()) {
            $summary->offsetSet($group['id'], [
                'id'        => $group['id'],
                'name'      => $group['name'],
                'total'     => $group['total'],
                'logged_in' => 0,
            ]);
        }
        $summary->sortByCallback(function (array $a, array $b) {
            return strnatcasecmp($a['name'], $b['name']);
        }, true);

        $interval = $this->sessionLength * 2;
        $query = "
            SELECT user.group_id, COUNT(DISTINCT `user`.id) AS logged_in
            FROM login_log
            JOIN `user` ON login_log.username=`user`.username AND `user`.deleted_at IS NULL
            WHERE `date` > DATE_SUB(NOW(), INTERVAL {$interval} SECOND)
            AND login = 1
            GROUP BY user.group_id;";

        $statement = $this->database->prepareQuery($query);
        $statement->execute();

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch()) {
            $groupData = $summary->offsetGet($row['group_id']);
            $groupData['logged_in'] = $row['logged_in'];
            $summary->offsetSet($row['group_id'], $groupData);
        }

        return $summary->getAll();
    }
}
