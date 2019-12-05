<?php
/**
 * Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Usage\All;

use Alaress_Date_Format;
use Database;

class UsageMapper
{
    /**
     * @var Database
     */
    protected $database;

    /**
     * Constructor - sets up database
     */
    public function __construct()
    {
        $this->database = Database::getInstance();
    }

    /**
     * The total number of users
     *
     * @return mixed
     */
    public function numUsers()
    {
        $query = "
            SELECT COUNT(`user`.id) AS num
            FROM `user`
        ";

        $statement = $this->database->prepare($query);
        $statement->execute();

        $result = $statement->fetch(\PDO::FETCH_ASSOC);

        return $result['num'];
    }

    /**
     * Find total usage
     *
     * This summates the filesizes of all files in the specified location.
     * This includes files which may have been marked as softdeleted in the db
     */
    public function findTotalUsage()
    {
        $total = disk_total_space(\Config::get('file_dir'));
        $free = disk_free_space(\Config::get('file_dir'));
        $used = $total - $free;

        return array(
            'Used' => $used,
            'Available' => $free,
        );
    }

    /**
     * Find total category usage
     *
     * This does NOT summate files which have been marked as softdeleted in the db
     *
     * @return array
     */
    public function findGroupUsage()
    {
        $query = "
            SELECT `groups`.name, COALESCE(SUM(bytes), 0) AS bytes
            FROM groups
              LEFT JOIN
              (
                (SELECT `groups`.id, `groups`.name, SUM(`storage`.`filesize`) AS bytes
                 FROM `file`
                   INNER JOIN `user` ON `user`.id = `file`.owner
                   INNER JOIN `groups` ON `user`.group_id = `groups`.id
                   INNER JOIN `storage` ON `file`.`hash` = `storage`.`hash`
                 WHERE `file`.`deleted_at` IS NULL
                 GROUP BY `groups`.id)
                UNION
                (SELECT `groups`.id, `groups`.name, SUM(`storage`.`filesize`) AS bytes
                 FROM `myFile`
                   INNER JOIN `user` ON `user`.id = `myFile`.owner
                   INNER JOIN `groups` ON `user`.group_id = `groups`.id
                   INNER JOIN `storage` ON `myFile`.`hash` = `storage`.`hash`
                 WHERE `myFile`.`deleted_at` IS NULL
                 GROUP BY `groups`.id)
                UNION
                (SELECT `groups`.id, `groups`.name, SUM(`storage`.`filesize`) AS bytes
                 FROM `submission_box`
                   INNER JOIN `user` ON `user`.id = `submission_box`.owner
                   INNER JOIN `groups` ON `user`.group_id = `groups`.id
                   INNER JOIN `submission_box_file` ON `submission_box_file`.box_id = `submission_box`.id
                   INNER JOIN `storage` ON `submission_box_file`.`storage_id` = `storage`.`storage_id`
                 GROUP BY `groups`.id)
                UNION
                (SELECT `groups`.id, `groups`.name, SUM(`storage`.`filesize`) AS bytes
                 FROM `submission_return`
                   INNER JOIN `user` ON `user`.id = `submission_return`.owner
                   INNER JOIN `groups` ON `user`.group_id = `groups`.id
                   INNER JOIN `submission_return_file` ON `submission_return_file`.`submission_return_id` = `submission_return`.`id`
                   INNER JOIN `storage` ON `submission_return_file`.`hash` = `storage`.`hash`
                 WHERE `submission_return`.`deleted_at` IS NULL
                 GROUP BY `groups`.id)
              ) allTotals ON `groups`.id = allTotals.id
            GROUP BY `groups`.name
            ORDER BY `groups`.id
        ";

        $statement = $this->database->prepare($query);
        $statement->execute();

        $result = array();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Find total type usage
     *
     * This summates the filesizes of all files in the specified location.
     * This includes files which may have been marked as softdeleted in the db
     *
     * @return array
     */
    public function findTypeUsage()
    {
        $cmd = 'du -sb ';

        /* "categories" of usage
         */
        $collection = new \Schoolbox\Collection();
        $collection->add(new \Schoolbox\Usage\Item('Cache'));
        $collection->add(new \Schoolbox\Usage\Item('Dropbox'));
        $collection->add(new \Schoolbox\Usage\Item('My Files'));
        $collection->add(new \Schoolbox\Usage\Item('Resources'));
        $collection->add(new \Schoolbox\Usage\Item('Returns'));
        $collection->add(new \Schoolbox\Usage\Item('Storage'));
        $collection->add(new \Schoolbox\Usage\Item('Submissions'));
        $collection->add(new \Schoolbox\Usage\Item('Videos'));
        $collection->add(new \Schoolbox\Usage\Item('Other'));

        /* Map keys to locks
         */
        $mapping = array(
            'dropbox_dir' => 'Dropbox',
            'file_dir' => 'Resources',
            'logs_dir' => 'Cache',
            'minify_dir' => 'Cache',
            'myfiles_cache_dir' => 'Cache',
            'myfile_dir' => 'My Files',
            'portrait_dir' => 'Cache',
            'returns_dir' => 'Returns',
            'stream_dir' => 'Cache',
            'submission_dir' => 'Submissions',
            'thumbnail_dir' => 'Cache',
            'video_dir' => 'Videos',
            'weather_dir' => 'Cache',
            'xml_dir' => 'Cache',
            'zip_dir' => 'Cache',
        );

        /* Quizzle it
         */
        $query = "
            SELECT `name`,`value`
            FROM `config`
            WHERE `name` LIKE '%dir'
            AND `name` NOT IN ('template_dir', 'storage_dir')
            ORDER BY `name`
        ";

        $statement = $this->database->prepare($query);
        $statement->execute();

        /* Iterate
         */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /* Gather results for directories which exist
             */
            if (is_dir($row['value'])) {
                $path = escapeshellarg($row['value']);

                $output = shell_exec($cmd . $path);

                $bits = explode(' ', $output);

                /* Get to that Item object and byte it
                 *
                 * If there's a directory that isn't mapped, throw it into Other
                 *
                 * That is a good way to see that something is occupying space that we don't
                 * necessarily know about...like another _dir folder in config table
                 */
                if (array_key_exists($row['name'], $mapping)) {
                    $key = $mapping[$row['name']];
                } else {
                    $key = 'Other';
                }

                $collection->getBy('name', $key)->add($bits[0]);
            }
        }

        // Special handling for storage, as it's too big to `du` sensibly
        // Assume it's kept up to date with OTFS
        $storageStatement = $this->database->prepare("
            SELECT SUM(filesize) AS filesize
            FROM storage
            WHERE expired_at IS NULL OR expired_at >= :now
        ");
        $storageStatement->execute([
            'now' => Alaress_Date_Format::now()->getUsingFormat(Alaress_Date_Format::DATABASE_FORMAT),
        ]);
        $collection->getBy('name', 'Storage')->add($storageStatement->fetchColumn());

        /* Big slices go first
         */
        $collection->sortBy('bytes', 'asc');

        return $collection;
    }

    /**
     * Find total user usage
     *
     * This does NOT summate files which have been marked as softdeleted in the db
     *
     * @param string $orderBy
     * @param string $order
     * @param int $index
     * @param $usersPerPage
     * @return array
     */
    public function findUserUsage($orderBy, $order, $index, $usersPerPage)
    {
        $query = "
            SELECT
              `user`.id AS userId, `user`.firstname, `user`.lastname,
              CONCAT(`user`.firstname, ' ', `user`.lastname) AS fullname,
              `groups`.name,
              COALESCE(SUM(bytes), 0) AS bytes
            FROM `user`
              INNER JOIN `groups` ON `user`.group_id = `groups`.id
              LEFT JOIN
              (
                (SELECT owner AS userId, SUM(`s`.`filesize`) AS bytes
                 FROM `file` `f`
                   INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
                 WHERE `f`.`deleted_at` IS NULL
                 GROUP BY `f`.`owner`)
                UNION
                (SELECT owner AS userId, SUM(`s`.`filesize`) AS bytes
                 FROM `myFile` `f`
                   INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
                 WHERE `f`.`deleted_at` IS NULL
                 GROUP BY `f`.`owner`)
                UNION
                (SELECT owner AS userId, SUM(`s`.`filesize`) AS bytes
                 FROM `student_submission` `f`
                   INNER JOIN `student_submission_file` ON `student_submission_file`.student_submission_id = `f`.id
                   INNER JOIN `storage` `s` ON `student_submission_file`.`hash` = `s`.`hash`
                   WHERE `student_submission_file`.deleted_at IS NULL
                 GROUP BY `f`.`owner`)
                UNION
                (SELECT owner AS userId, SUM(`s`.`filesize`) AS bytes
                 FROM `submission_return` `f`
                   INNER JOIN `submission_return_file` ON `submission_return_file`.submission_return_id = `f`.id
                   INNER JOIN `storage` `s` ON `submission_return_file`.`hash` = `s`.`hash`
                 WHERE `submission_return_file`.`deleted_at` IS NULL
                 GROUP BY `f`.`owner`)
              ) allTotals ON `user`.id = allTotals.userId
            GROUP BY `user`.id
        ";

        /* Select order by
         */
        if (!in_array($orderBy, array('firstname', 'lastname', 'name', 'bytes'))) {
            $orderBy = 'lastname';
        }

        if (!in_array($order, array('DESC', 'ASC'))) {
            $order = 'ASC';
        }

        /* Cast Away
         */
        $index = (int)$index;
        $usersPerPage = (int)$usersPerPage;

        /* Pagination
         */
        $start = $index * $usersPerPage;

        $query .= "
            ORDER BY $orderBy $order
            LIMIT $start, $usersPerPage
        ";

        /* Spit it out
         */
        $statement = $this->database->prepare($query);
        $statement->execute();

        $result = array();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $result[] = $row;
        }

        return $result;
    }
}
