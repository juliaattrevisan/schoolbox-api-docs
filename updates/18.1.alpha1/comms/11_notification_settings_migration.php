<?php

use Alaress\IDatabase;
use Psr\Log\LoggerInterface;
use Schoolbox\Application\Daemon;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Gets merged setttings from current settings for posted and published notifications
 * @param $posted
 * @param $published
 * @param bool $isMask
 */
function mergeOptions($posted, $published, $isMask = false)
{
    $basicOptions = ['MU', 'DI', 'IN'];
    $additionalOptions = ['MO', 'EM'];

    $postOptions = explode(',', $posted);
    $publishedOptions = explode(',', $published);

    $newOptions = [];

    // for override settings, get most notifying option
    // for mask settings, get everything
    foreach ($basicOptions as $option) {
        if (in_array($option, $postOptions) || in_array($option, $publishedOptions)) {
            if ($isMask) {
                $newOptions[] = $option;
            } else {
                $newOptions = [$option];
            }
        }
    }

    foreach ($additionalOptions as $option) {
        if (in_array($option, $postOptions) || in_array($option, $publishedOptions)) {
            $newOptions[] = $option;
        }
    }

    return implode(',', $newOptions);
}


/**
 * Updates posted settings from publish and posted settings, as publish will no longer be used.
 *
 * @param IDatabase $db
 * @param LoggerInterface $log
 */
function updateMsgOptions(IDatabase $db, LoggerInterface $log)
{
    /**
     * +----+-----------+-----------+
     * | id | role_type | key       |
     * +----+-----------+-----------+
     * | 34 | student   | posted    |
     * | 35 | parent    | posted    |
     * | 36 | staff     | posted    |
     * | 73 | student   | published |
     * | 75 | parent    | published |
     * | 74 | staff     | published |
     * +----+-----------+-----------+
     */

    $rows = $db->query("
    SELECT id, role_type, `key` FROM msg_source WHERE namespace = 5 AND (`key` = 'posted' || `key` = 'published') 
    ");

    $sourceIdMap = [];
    foreach ($rows as $row) {
        $sourceIdMap[$row['role_type']][$row['key']] = $row['id'];
    }

    /**
     * to select current posted and publish settings for a role
     * +----------------+----------------+---------------------+----------------+------------------------+
     * | role_id_posted | mask_posted    | role_default_posted | mask_published | role_default_published |
     * +----------------+----------------+---------------------+----------------+------------------------+
     * |              1 | MU,DI,IN,MO,EM | DI                  | MU,DI,IN,MO,EM | DI                     |
     * +----------------+----------------+---------------------+----------------+------------------------+
     */
    $selectRoutePerm = "
SELECT posted.role_id as role_id_posted, posted.mask as mask_posted, posted.role_default as role_default_posted,
       published.mask as mask_published, published.role_default as role_default_published 
FROM msg_route_perm posted JOIN msg_route_perm published ON posted.role_id=published.role_id 
WHERE posted.msg_source_id = :posted and published.msg_source_id = :published;
        ";

    /**
     * to update mask for a role
     */
    $updateRoutePermMask = "
        UPDATE msg_route_perm SET mask = :mask WHERE msg_source_id = :msg_source_id AND role_id = :role_id
        ";

    /**
     * to update default for a role
     */
    $updateRoutePermDefault = "
        UPDATE msg_route_perm SET role_default = :role_default WHERE msg_source_id = :msg_source_id AND role_id = :role_id
        ";

    /**
     * to select current posted and published settings for an user
     * +----------------+-----------------+--------------------+
     * | user_id_posted | delivery_posted | delivery_published |
     * +----------------+-----------------+--------------------+
     * |            151 | DI              | IN,EM              |
     * +----------------+-----------------+--------------------+
     */
    $selectReroute = "
SELECT posted.user_id as user_id_posted, posted.delivery as delivery_posted,
       published.delivery as delivery_published 
FROM msg_reroute posted JOIN msg_reroute published ON posted.user_id=published.user_id 
WHERE posted.msg_source_id = :posted and published.msg_source_id = :published;
        ";

    /**
     * to update settings for an user
     */
    $updateReroute = "
        UPDATE msg_reroute SET delivery = :delivery WHERE msg_source_id = :msg_source_id AND user_id = :user_id
        ";


    /**
     * Search settings and update for each roletypes
     */
    foreach ($sourceIdMap as $roleType => $sourceIds) {
        // msg_route_perm
        $rows = $db->query(
            $selectRoutePerm,
            [
                'posted' => $sourceIds['posted'],
                'published' => $sourceIds['published']
            ]
        );

        foreach ($rows as $row) {
            $maskPosted = $row['mask_posted'];
            $maskPublished = $row['mask_published'];
            $newMask = mergeOptions($maskPosted, $maskPublished, true);
            if ($newMask !== $maskPosted) {
                $db->query(
                    $updateRoutePermMask,
                    [
                        'mask' => $newMask,
                        'msg_source_id' => $sourceIds['posted'],
                        'role_id' => $row['role_id_posted']
                    ]
                );
                $log->info("Updating mask for 'posted' from {$maskPosted} to {$newMask} for role: {$row['role_id_posted']}");
            }

            $defaultPosted = $row['role_default_posted'];
            $defaultPublished = $row['role_default_published'];
            $newDefault = mergeOptions($defaultPosted, $defaultPublished);
            if ($newDefault !== $defaultPosted) {
                $db->query(
                    $updateRoutePermDefault,
                    [
                        'role_default' => $newDefault,
                        'msg_source_id' => $sourceIds['posted'],
                        'role_id' => $row['role_id_posted']
                    ]
                );
            }
        }

        // msg_reroute
        $rows = $db->query(
            $selectReroute,
            [
                'posted' => $sourceIds['posted'],
                'published' => $sourceIds['published']
            ]
        );

        foreach ($rows as $row) {
            $deliveryPosted = $row['delivery_posted'];
            $deliveryPublished = $row['delivery_published'];
            $newDelivery = mergeOptions($deliveryPosted, $deliveryPublished);
            if ($newDelivery !== $deliveryPosted) {
                $db->query($updateReroute, ['delivery' => $newDelivery,
                        'msg_source_id' => $sourceIds['posted'],
                        'user_id' => $row['user_id_posted']
                    ]);
                $log->info("Updating delivery for 'posted' from {$deliveryPosted} to {$newDelivery} for user: {$row['user_id_posted']}");
            }
        }
    }

    /**
     * Now delete publish msg_source, so that it won't appear on the settings page
     */
    $db->query("
    DELETE FROM msg_source WHERE `key` = 'published' AND namespace = 5
    ");
}


$kernel = new Daemon\Kernel(
    'Merge notification settings for school news and homepage news',
    [
        Daemon\Kernel::OPTION_LEVEL  => Monolog\Logger::DEBUG,
        Daemon\Kernel::OPTION_ERRORS => E_ALL,
    ]
);

$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {
    $db = Database::getInstance();

    updateMsgOptions($db, $log);
});
