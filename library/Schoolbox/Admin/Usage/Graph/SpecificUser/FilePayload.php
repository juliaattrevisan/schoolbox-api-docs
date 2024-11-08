<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Usage\Graph\SpecificUser;

use Alaress\Database\Connector\IConnector;
use Schoolbox\User;

/**
 * Class FilePayload
 *
 * Calculate each user's storage usage
 *
 * @package Schoolbox\Admin\Usage\Graph
 */
class FilePayload
{
    /**
     * @var IConnector
     */
    private IConnector $database;


    /**
     * System constructor.
     *
     * @param IConnector $database
     */
    public function __construct(IConnector $database)
    {
        $this->database = $database;
    }


    /**
     * Generate a list of files and their locations
     * Each file is owned by $user
     *
     * @param User $user
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function generate(User $user): array
    {
        $query = "
            SELECT *
            FROM
            (
                (SELECT
                    `f`.`id`,
                    COALESCE(`f`.`realname`, `s`.`filename`) AS `name`,
                    'CMS' AS location,
                    `s`.`filesize` AS size,
                    `t`.`mime` AS mimeType,
                    `s`.`hash`,
                    `c`.`id` AS folderId,
                    `c`.`name` AS folder
                FROM `file` `f`
                LEFT JOIN folder `c` ON `f`.`category` = `c`.`id`
                INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
                LEFT JOIN `allowedFileExtension` `e` ON `s`.`ext` = `e`.`extension`
                LEFT JOIN `allowedFileTypes` `t` ON `e`.`fileTypeId` = `t`.`fileTypeId`
                WHERE `f`.`owner` = :userId
                AND `f`.`deleted_at` IS NULL)
                UNION
                (SELECT
                    `f`.`id`,
                    COALESCE(`f`.`realname`, `s`.`filename`) AS `name`,
                    'My File' AS location,
                    `s`.`filesize` AS size,
                    `t`.`mime` AS mimeType,
                    `s`.`hash`,
                    '' AS folderId,
                    '' AS folder
                FROM `myFile` `f`
                INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
                LEFT JOIN `allowedFileExtension` `e` ON `s`.`ext` = `e`.`extension`
                LEFT JOIN `allowedFileTypes` `t` ON `e`.`fileTypeId` = `t`.`fileTypeId`
                WHERE `f`.`owner` = :userId
                AND `f`.`deleted_at` IS NULL)
                UNION
                (SELECT
                    `f`.`id` AS id,
                    COALESCE(`student_submission_file`.`realname`, `s`.`filename`) AS `name`,
                    'Submission' AS location,
                    `s`.`filesize` AS size,
                    `t`.`mime` AS mimeType,
                    `s`.`hash`,
                    `c`.`id` AS folderId,
                    `c`.`name` AS folder
                FROM `student_submission` `f`
                INNER JOIN `student_submission_file` ON `student_submission_file`.`student_submission_id` = `f`.id
                INNER JOIN `storage` `s` ON `s`.hash = `student_submission_file`.hash
                LEFT JOIN `allowedFileExtension` `e` ON `s`.`ext` = `e`.`extension`
                LEFT JOIN `allowedFileTypes` `t` ON `e`.`fileTypeId` = `t`.`fileTypeId`
                INNER JOIN `submission_box` `b` ON `f`.`box_id` = `b`.`id`
                LEFT JOIN assessment_folder af ON af.assessment_id = b.assessment_id
                LEFT JOIN folder c ON af.folder_id = c.id
                WHERE `f`.`owner` = :userId
                AND `student_submission_file`.`deleted_at` IS NULL)
                UNION
                (SELECT
                    `srf`.`id` AS id,
                    COALESCE(srf.`realname`, `s`.`filename`) AS `name`,
                    'Return' AS location,
                    `s`.`filesize` AS size,
                    `t`.`mime` AS mimeType,
                    `s`.`hash`,
                    `c`.`id` AS folderId,
                    `c`.`name` AS folder
                FROM `submission_return` `f`
                INNER JOIN `submission_return_file` srf ON srf.submission_return_id = `f`.id
                INNER JOIN `storage` `s` ON `s`.hash = srf.hash
                LEFT JOIN `allowedFileExtension` `e` ON `s`.`ext` = `e`.`extension`
                LEFT JOIN `allowedFileTypes` `t` ON `e`.`fileTypeId` = `t`.`fileTypeId`
                INNER JOIN `submission_box` `b` ON `f`.`box_id` = `b`.`id`
                LEFT JOIN assessment_folder af ON af.assessment_id = b.assessment_id
                LEFT JOIN folder c ON af.folder_id = c.id
                WHERE `f`.`owner` = :userId
                AND srf.`deleted_at` IS NULL)
            ) AS allTotals
        ";

        /* Spit it out
         */
        $statement = $this->database->executeQuery($query, [
            'userId' => $user->getId()
        ]);

        $stack = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /* Augment urls depending on $row['location']
             * Each place has its own send and deletion endpoints
             */
            switch ($row['location']) {
                case 'CMS':
                    $sendUrl = "/send.php?id={$row['id']}";
                    $deleteUrl = "/cms/fileForm.php?delete={$row['id']}";
                    break;

                case 'My File':
                    $sendUrl = "/cms/sendMyFile.php?id={$row['id']}";
                    $deleteUrl = "/cms/myFileForm.php?delete={$row['id']}";
                    break;

                case 'Submission':
                    $sendUrl = "/storage/fetch.php?hash={$row['hash']}";
                    $deleteUrl = null;
                    break;

                case 'Return':
                    $sendUrl = "/storage/fetch.php?hash={$row['hash']}";
                    $deleteUrl = "/learning/feedback/file/{$row['id']}/delete";
                    break;

                default:
                    $sendUrl = '';
                    $deleteUrl = '';
                    break;
            }

            /* Shank it in
             */
            $row['_links'] = [
                'send' => $sendUrl,
                'delete' => $deleteUrl
            ];

            $stack[] = $row;
        }

        return $stack;
    }
}
