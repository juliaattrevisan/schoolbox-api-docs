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
    $userMapper = \Schoolbox\UserMapper::getInstance();
    $storageMapper = \Schoolbox\Resource\Storage\Mapper::getInstance();

    /** @var IConfigLoader $conf */
    $conf  = $di->get('config');

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

        try {
            $user = $userMapper->findByExternalId($externalId);

            $path = "{$portraitPath}/{$file}";

            /* Save it into brownhole
             */
            $portrait = $storageMapper->acceptStream($user->getExternalId() . '.jpg', file_get_contents($path));

            /* Update user with latest version
             */
            $user->setPortrait($portrait);

            $userMapper->save($user);

            $log->info("{$user->getFullName()} now has portrait image {$file} {$portrait->getHash()} ({$portrait->getSize()})");

            /* Remove file when done
             */
            unlink($path);
        } catch (\Schoolbox\Mapper\Exception $e) {

            /* User not found; noop
             */
            $log->warning("External id {$externalId} not found");
        }
    }
});
