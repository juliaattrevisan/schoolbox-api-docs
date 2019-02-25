<?php
/**
 * Migrate portraits on the local filesystem into Storage
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Alaress\IDatabase;
use Psr\Log\LoggerInterface;
use Schoolbox\IConfigLoader;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Migrate portraits from filesystem into Storage', [
    'verbose' => [false, false, false],
    'errors'  => E_ALL,
]);

$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {
    $db = Database::getInstance();
    $storageMapper = \Schoolbox\Resource\Storage\Mapper::getInstance();

    /** @var IConfigLoader $conf */
    $conf  = $di->get('config');

    /* Prepared statements
     */
    $selectResult = $db->prepare('
        SELECT id, synergy_id, firstname, lastname
        FROM `user`
        WHERE synergy_id = :externalId
        LIMIT 1
    ');

    $updateResult = $db->prepare('
        UPDATE `user`
        SET portrait_hash = :hash
        WHERE id = :id
    ');

    /* Schools upload pictures to this directory periodically
     * This tool shoves them into storage
     * This tool can be run repeatedly in cron to preserve schools' existing workflows
     */
    $portraitPath = $conf->get('portrait_dir');

    $dirhandle = opendir($portraitPath);

    while ($file = readdir($dirhandle)) {
        if (('.' === $file) || ('..' === $file)) {
            continue;
        }

        /* If it's an actual file, send it in
         */
        $info = pathinfo($file);

        /* Naming convention in schoolbox instructions suggests this as the externalId
         */
        $externalId = $info['filename'];

        /* Find them classically
         */
        $selectResult->execute([
            'externalId' => $externalId,
        ]);

        $user = $selectResult->fetch(\PDO::FETCH_ASSOC);

        /* If a user was found, try to assign image
         */
        if ($user) {

            $path = "{$portraitPath}/{$file}";

            /* Save it into brownhole
             */
            $portrait = $storageMapper->acceptStream($externalId . '.jpg', file_get_contents($path));

            /* Update user with latest version
             */
            $updateResult->execute([
                'hash' => $portrait->getHash(),
                'id' => $user['id']
            ]);

            $log->info("{$user['firstname']} {$user['lastname']} now has portrait image {$file} {$portrait->getHash()} ({$portrait->getSize()})");

            /* Remove file when done
             */
//            unlink($path);

        } else {

            /* User not found; noop
             */
            $log->warning("External id {$externalId} not found; skipped");
        }
    }
});
