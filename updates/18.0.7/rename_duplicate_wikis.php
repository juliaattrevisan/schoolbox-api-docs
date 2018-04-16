<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

use Alaress\Database\Connector\IConnector;
use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;

require_once __DIR__ . '/../../conf/core/require.php';

spl_autoload_call('\\Schoolbox\\Application\\Daemon');
$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Re-sequence Rubric Levels',
    [
        'verbose' => [false, false, false],
        'errors'  => E_ALL,
    ]
);

/** @noinspection PhpUnhandledExceptionInspection */
$kernel->run(
    /**
     * @param Di\Container  $di
     * @param LoopInterface $loop
     * @param Logger        $log
     * @param               $opts
     *
     * @throws Di\Exception\ServiceNotFound
     */
    function (Di\Container $di, LoopInterface $loop, Logger $log, $opts) {
        /** @var IConnector $db */
        $db = $di->get('databaseInternal');

        // Get wiki category ID that has duplicates
        $selectQuery = $db->prepareQuery("
            SELECT DISTINCT categoryId
            FROM wiki
            GROUP by categoryID, slug
            HAVING COUNT(slug) > 1;
        ");

        $selectQuery->execute();

        $selectQuery2 = $db->prepareQuery("
            SELECT *
            FROM wiki
            WHERE categoryID = :category
        ");


        // Append the slugs with '-'.$increment
        $updateQuery = $db->prepareQuery("
            UPDATE wiki 
            SET slug = CONCAT(slug, CONCAT('-',:incrementer))
            WHERE wikiID = :wikiID
        ");

        $selectQuery->execute();

        $lastSlug = null;
        $lastCategoryID = null;
        $incrementer = 1;

        foreach ($selectQuery->fetchAll() as $row) {

            // Retrieve all wikis for a category that has duplicates
            $selectQuery2->execute(['category' => $row['categoryId']]);

            foreach($selectQuery2->fetchAll() as $row2) {

                // Update wikis that have the same slug as the previous row (skips first item)
                if($row2['slug'] == $lastSlug && $row2['categoryID'] == $lastCategoryID) {
                    
                    $updateQuery->execute(['wikiID' => $row2['wikiID'], 'incrementer' => $incrementer]);
                } else {
                    // Reset Incrementer
                    $incrementer = 1;
                }

                $lastSlug = $row2['slug'];
                $lastCategoryID = $row2['categoryID'];
            }
        }
    }
);
