<?php

/**
 * Created by PhpStorm.
 * User: jleckie
 * Date: 20/08/15
 * Time: 2:51 PM
 */

namespace Schoolbox\Engagement;

use Database;

class CMS
{
    /**
     * @var Database
     */
    private $database;

    /**
     * Constructor - sets up database
     */
    public function __construct()
    {
        $this->database = Database::getInstance();
    }

    public function log(\Alaress_Date_Format $start, \Alaress_Date_Format $end, $action, $object)
    {
        $query = "
                SELECT `groups`.name, COUNT(*) as total FROM cmsLog JOIN `user` ON cmsLog.user = `user`.id JOIN `groups` ON user.group_id = `groups`.id
                WHERE object = :object AND action = :action AND
                `time` >= :start AND `time` <= :end
                GROUP BY group_id";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':start', $start->getDatabaseDate());
        $statement->bindParam(':end', $end->getDatabaseDate());
        $statement->bindParam(':action', $action);
        $statement->bindParam(':object', $object);


        $statement->execute();
        return $statement->fetchAll();
    }

    /**
     * Returns a set of arrays, each with a group name and count of specified access between
     * given dates for the specified action and mimeType
     *
     * @param \Alaress_Date_Format $start
     * @param \Alaress_Date_Format $end
     * @param string $action
     * @param string $mimeType  a mime prefix, eg 'image' that will match eg, mime of 'image/gif'
     * @return array
     */
    public function logFileType(\Alaress_Date_Format $start, \Alaress_Date_Format $end, $action, $mimeType)
    {
        $query = "
                SELECT `groups`.`name`, COUNT(*) AS total
                FROM `cmsLog`
                JOIN `user` ON `cmsLog`.`user` = `user`.`id`
                JOIN `groups` ON `user`.`group_id` = `groups`.`id`
                JOIN `file` ON `cmsLog`.`objectId` = `file`.`id`
                JOIN `storage` ON `file`.`hash` = `storage`.`hash`
                JOIN `allowedFileExtension` `afe` ON `storage`.`ext` = `afe`.`extension`
                JOIN `allowedFileTypes` `aft` ON `afe`.`fileTypeID` = `aft`.`fileTypeID`
                WHERE aft.mime LIKE :type
                AND `cmsLog`.`object` = 'file'
                AND `cmsLog`.`action` = :action
                AND `time` >= :start AND `time` <= :end
                GROUP BY `user`.`group_id`";

        $mimeType = $mimeType . '%';
        $statement = $this->database->prepare($query);
        $statement->bindParam(':start', $start->getDatabaseDate());
        $statement->bindParam(':end', $end->getDatabaseDate());
        $statement->bindParam(':action', $action);
        $statement->bindParam(':type', $mimeType);
        $statement->execute();
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /* TODO This appears to be dead code as of 2016/06/28. Deliberately commented out for now.
    public function totalFiles($role) {
        $query = "SELECT COUNT(*) FROM file JOIN `user` ON file.owner=user.id
                  WHERE group_id = :role AND deleted=0";
        $statement = $this->database->prepare($query);
        $statement->bindParam(':role', $action);

        $statement->execute();
        return $statement->fetchColumn(0);
    }

    public function totalLinks() {

    }
    */
}
