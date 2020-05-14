#!/usr/bin/php
<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Migrate portraits on the local filesystem into Storage
 */
$app = new class extends CliTool\App {
    // --help: print help
    private const OPT_LONG_HELP = 'help';


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Migrate portraits from filesystem portrait_dir into Storage and associate them
with schoolbox user accounts. Each profile picture is assumed to be a jpg and
you can dump as many as you want into the stock_portrait folder.

Profile pictures are only assigned to users who don't have a portrait already.

Usage: migrate.php [-v] [-p]
        -p Actually migrate portraits (otherwise, this will only show what would
           happen)
        -v Verbose mode (may be provided up to 3 times for increasing verbosity)

HELP;
            exit;
        }

        /* Get a list of available files...and not care about their filenames
         */
        $stockDir  = __DIR__ . '/stock_portrait';
        $dirhandle = opendir($stockDir);

        $files = [];

        while ($file = readdir($dirhandle)) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $files[] = $file;
        }

        /* For each user who doesn't have a portrait_hash...
         */
        $db            = Database::getInstance();
        $storageMapper = StorageMapper::getInstance();

        $updateQuery = $db->prepare('
            UPDATE  `user`
            SET portrait_hash = :hash
            WHERE id = :id
        ');
        $statement   = $db->query('
            SELECT id, firstname, lastname, synergy_id
            FROM `user`
            WHERE portrait_hash IS NULL
            AND LENGTH(synergy_id) > 0
        ');

        /* If there's nothing to do, say so and leave
         */
        if ($statement->rowCount() === 0) {
            $this->getLogger()->info('No users found; everyone has pictures already.');
            return;
        }


        while ($user = $statement->fetch(\PDO::FETCH_ASSOC)) {

            /* Select a random image
             */
            $stockFile = $files[random_int(0, \count($files) - 1)];
            $source    = "{$stockDir}/{$stockFile}";

            if ($this->perform()) {
                $portrait = $storageMapper->acceptStream($stockFile, $source, false);
                $updateQuery->execute([
                    'id'   => $user['id'],
                    'hash' => $portrait->getHash(),
                ]);
                $this->getLogger()->info(
                    sprintf(
                        "%s %s (id: %d, externalId: %s) now has portrait image %s %s (%d)",
                        $user['firstname'],
                        $user['lastname'],
                        $user['id'],
                        $user['synergy_id'],
                        $stockFile,
                        $portrait->getHash(),
                        $portrait->getSize()
                    )
                );
            } else {
                $this->getLogger()->info(
                    sprintf(
                        "%s %s (id: %d, externalId: %s) would have new portrait image %s",
                        $user['firstname'],
                        $user['lastname'],
                        $user['id'],
                        $user['synergy_id'],
                        $stockFile
                    )
                );
            }
        }

        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
        }
    }
};

$options = [CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
