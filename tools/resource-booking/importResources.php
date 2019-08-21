#!/usr/bin/php
<?php
/**
 * Script for importing CSV of resource bookings into our resource booking system.
 */

use Schoolbox\Application\CliTool;
use Schoolbox\Collection;
use Schoolbox\ResourceBooking\Category;
use Schoolbox\ResourceBooking\Item;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App
{

    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return ['dry-run'];
    }


    /** @inheritDoc */
    public function run(): void
    {
        global $argv;

        if (!isset($argv[1])) {
            $filename = basename(__FILE__);
            echo
            <<<HELP
Imports resources from a CSV with a given format.

Usage: $filename [--dry-run] [FILE]

    --dry-run: if set, don't update anything, but show what would be updated.

HELP
            ;
            exit;
        }

        $dryRun  = $this->getOpt('dry-run');

        // Logging
        $log = $this->getLogger();

        // File - from last positional parameter
        $file = array_pop($argv);
        if (!(is_file($file) && is_readable($file))) {
            fwrite(STDERR, "File \"$file\" does not exist, or is not readable.\n");
            exit(1);
        }
        $csv = new Alaress\Csv\Reader($file);
        $csv->setLoadFromFirst(true);

        $resourceMapper = new Item\Mapper();
        $categoryMapper = new Category\Mapper();
        $userMapper     = new UserMapper();
        foreach ($csv as $row) {
            $log->notice("Attempting to import resource \"{$row['Name']}\"");

            // Moderation type: default to moderated
            $modType = new Item\ModerationType\Model();
            switch (strtolower($row['Moderation Type'])) {
                case 'managed':
                    $modType->setId($modType::TYPE_MANAGED);
                    break;
                case 'open':
                    $modType->setId($modType::TYPE_OPEN);
                    break;
                case 'moderated':
                default:
                    $modType->setId($modType::TYPE_MODERATED);
                    break;
            }

            // Create the resource to import booking for
            $resource = new Item\Model();

            $resource
                ->setName($row['Name'])
                ->setDescription($row['Description'])
                ->setRoomCode($row['Room Codes'])
                ->setModerationType($modType)
                // Guess at timespans
                ->setMinLeadTime(0)
                ->setMinReservationTime(0)
                ->setMaxReservationTime(0)
            ;

            // Category
            try {
                $category = $categoryMapper->findOneByColumn('name', $row['Category']);
                $resource->setCategory($category);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $log->warning("Category \"{$row['Category']}\" not found - skipping");
                continue;
            }

            // Set its moderators
            if($row['ManagedBy_SynergeticID']) {
                $resource->setModeratorList(new Collection());
                try {
                    $moderator = $userMapper->findByExternalId($row['ManagedBy_SynergeticID']);
                    $resource->addModerator($moderator);
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $log->warning("Moderator \"{$row['ManagedBy_SynergeticID']}\" not found - skipping");
                    continue;
                }
            }

            if ($dryRun) {
                $log->notice("No problems found");
            } else {
                try {
                    if (!$resourceMapper->save($resource)) {
                        $log->error("Item failed to save!");
                    } else {
                        $log->notice("Item saved [id={$resource->getId()}]");
                    }
                } catch (Exception $e) {
                    $log->error("Exception while importing: ".$e->getMessage());
                }
            }
        }
        exit;
    }
};


$kernel = new CliTool\Kernel($app);
$kernel->runApp();
