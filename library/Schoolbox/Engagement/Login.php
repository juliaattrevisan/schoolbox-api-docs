<?php

/**
 * Created by PhpStorm.
 * User: jleckie
 * Date: 20/08/15
 * Time: 2:51 PM
 */

namespace Schoolbox\Engagement;

use Alaress\Database\Connector\IConnector;
use Schoolbox\IConfigLoader;

class Login
{
    /** @var IConnector */
    private $database;

    /** @var IConfigLoader */
    private $config;

    /**
     * Constructor - sets up database
     * @param IConnector    $database
     * @param IConfigLoader $config
     */
    public function __construct(IConnector $database, IConfigLoader $config)
    {
        $this->database = $database;
        $this->config   = $config;
    }

    public function loginByGroup(\Alaress_Date_Format $start, \Alaress_Date_Format $end) {
        $query = "SELECT groups.name,  groups.id,  COUNT(*) AS total FROM login_log JOIN  `user` ON login_log.username = `user`.username JOIN groups ON user.group_id = groups.id
                  WHERE login=1 AND date >= :start AND date <= :end
                  GROUP BY group_id";

        $statement = $this->database->prepareQuery($query);
        $statement->execute([
            'start' => $start->getDatabaseDate(),
            'end'   => $end->getDatabaseDate(),
        ]);
        return $statement->fetchAll();
    }

    public function uniqueLoginByGroup(\Alaress_Date_Format $start, \Alaress_Date_Format $end) {
        $query = "SELECT groups.`name`,
                    (
                        SELECT COUNT(DISTINCT(`user`.username)) FROM `user`
                        INNER JOIN  login_log ON `user`.username = login_log.username
                        WHERE group_id = groups.id AND login=1 AND `date` >= :start AND `date` <= :end
                    ) as total
                  FROM groups
                    ";

        $statement = $this->database->prepareQuery($query);
        $statement->execute([
            'start' => $start->getDatabaseDate(),
            'end'   => $end->getDatabaseDate(),
        ]);
        return $statement->fetchAll();
    }


    /**
     * Returns a summary of total users in system, and number of active sessions
     *
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function userSummary(): array
    {
        $query = '
                SELECT groups.id, groups.`name`, COUNT(*) total
                FROM groups
                JOIN `user` ON groups.id=`user`.group_id AND `user`.deleted_at IS NULL
                GROUP BY groups.id
                ORDER BY groups.`name`';

        $statement = $this->database->prepareQuery($query);
        $statement->execute();
        $summary = [];

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($group = $statement->fetch()) {
            $summary[$group['id']] = [
                'id'        => $group['id'],
                'name'      => $group['name'],
                'total'     => $group['total'],
                'logged_in' => 0,
            ];
        }

        $interval = $this->config->get('session_length') * 2;
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
            $summary[$row['group_id']]['logged_in'] = $row['logged_in'];
        }

        return $summary;
    }
}
