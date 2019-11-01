#!/usr/bin/php
<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Admin\Import\Photos\MigrateToStorage;
use Schoolbox\Application\CliTool;
use Schoolbox\Resource\Storage\Mapper;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Migrate portraits on the local filesystem into Storage
 */
$app = new class extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';


    /**
     * Backfill only users who don't have a portrait_hash yet
     */
    private const OPT_BACKFILL = 'backfill';


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_BACKFILL,
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

The backfill flag allows you to only assign images to users who don't already
have one. Omitting it allows you to update anyone's picture.

Usage: migrate.php [--backfill]
        --backfill  Only assign portrait to users who don't already have one


HELP;
            exit;
        }

        /* Get a list of available files...and not care about their filenames
         */
        $stockDir = __DIR__ . '/stock_portrait';
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
        $db = Database::getInstance();
        $assignation = [];

        $statement = $db->query('
            SELECT DISTINCT synergy_id
            FROM `user`
            WHERE portrait_hash IS NULL
            AND LENGTH(synergy_id) > 0
        ');

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $externalId = $row['synergy_id'];

            /* Select a random image
             */
            $assignation[$externalId] = $files[random_int(0, \count($files)-1)];
        }

        /* If there's nothing to do, say so and leave
         */
        if (!\count($assignation)) {
            $this->getLogger()->info('No users found; everyone has pictures already.');
            return;
        }

        /* Copy it to portrait_dir
         */
        $portraitDir = $this->getConfig()->get('portrait_dir');

        foreach ($assignation as $externalId => $stockFile) {
            $source = "{$stockDir}/{$stockFile}";
            $destination = "{$portraitDir}/{$externalId}.jpg";

            /* Rename it after their synergetic_id
             * Note that this preserves the original assumption that everything is a jpg
             */
            $success = copy($source, $destination);

            if ($success) {
                $this->getLogger()->info("Copied {$source} to {$destination}");
            } else {
                $this->getLogger()->warning("Could not copy {$source} to {$destination}; skipped");
            }
        }

        /* Migrate all images with --backfill arg
         *
         * This should avoid a race condition where a user is manually assigned a picture
         * while this script is running
         */
        $migrator = new MigrateToStorage(
            $portraitDir,
            $db,
            Mapper::getInstance(),
            $this->getLogger(),
            $this->perform()
        );

        if ($this->getOpt(self::OPT_BACKFILL)) {
            $migrator->setBackfill(true);
        }

        $migrator->migrate();

        /* Clean up any remainder files
         */
        foreach ($assignation as $externalId => $stockFile) {
            $destination = "{$portraitDir}/{$externalId}.jpg";

            if (file_exists($destination)) {
                unlink($destination);
            }
        }

        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
        }
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
