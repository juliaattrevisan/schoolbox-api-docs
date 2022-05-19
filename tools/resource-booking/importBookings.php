#!/usr/bin/php
<?php

/**
 * Script for importing resource bookings from CSV into ours.
 */

use Schoolbox\Application\CliTool;
use Schoolbox\ResourceBooking\Booking;
use Schoolbox\ResourceBooking\Item;
use Schoolbox\Calendar;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App{

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
Imports resource bookings from a CSV with a given format.

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
            $log->error("File \"$file\" does not exist, or is not readable.");
            exit(1);
        }
        $csv = new Alaress\Csv\Reader($file);
        $csv->setLoadFromFirst(true);

        $bookingMapper   = new Booking\Mapper();
        $eventMapper     = new Schoolbox\Calendar\EventMapper();
        $resourceMapper  = new Item\Mapper();
        $userMapper      = new UserMapper();
        $essentialFields = ['shortname', 'bookingcreatoruserid', 'bookingstart', 'bookingEnd'];
        foreach ($csv as $row) {
            // Don't import if essential fields not set
            foreach ($essentialFields as $essentialField) {
                if (!isset($row[$essentialField])) {
                    $log->warning(sprintf('Essential field "%s" not set: skipping', $essentialField));
                    continue 2;
                }
            }
            // Get the resource to import booking for
            $log->notice("Attempting to import booking for \"{$row['shortname']}\"");
            try {
                $resourceName = preg_replace('/\\[.*?\\]$/', '', $row['shortname']);
                $resource     = $resourceMapper->findOneByColumn('name', $resourceName);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $log->warning("Resource \"{$row['shortname']}\" not found - skipping");
                continue;
            }

            // Set up the creator
            try {
                $creator = $userMapper->findOneByColumn('username', $row['bookingcreatoruserid']);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $log->warning("User \"{$row['bookingcreatoruserid']}\" not found - skipping");
                continue;
            }

            // Set up start and end times
            $startTime = DateTime::createFromFormat('d/m/Y G:i', $row['bookingstart']);
            if (!($startTime instanceof DateTime)) {
                $log->warning("Start time \"{$row['bookingstart']}\" not recognized time format - skipping");
                continue;
            }
            $endTime = DateTime::createFromFormat('d/m/Y G:i', $row['bookingEnd']);
            if (!($endTime instanceof DateTime)) {
                $log->warning("End time \"{$row['bookingEnd']}\" not recognized time format - skipping");
                continue;
            }
            $createTime =  DateTime::createFromFormat('d/m/Y G:i', $row['creationdate']);
            if (!($createTime instanceof DateTime)) {
                $log->notice("Creation time \"{$row['creationdate']}\" not recognized time format - default to now");
            }

            // Set up the event
            $event = new Calendar\Event();
            $event->setAccess(Calendar\Event::ACCESS_PERSONAL);
            $event->setOwner($creator);
            $event->setAuthor($creator);
            $event->setTitle($row['Bookingshortname'] ?? '');
            $event->setDetail(nl2br($row['bookingdescription'] ?? ''));
            $event->setType(Calendar\EventTypeMapper::getInstance()->createModel()->setId(1));
            $event->setStartDateTime(new Alaress_Date_Format($startTime));
            $event->setEndDateTime(new Alaress_Date_Format($endTime));
            // Blargh
            $event->setRoleCollection(new \Schoolbox\Collection());
            $event->setCampusCollection(new \Schoolbox\Collection());

            // Set up the booking
            $booking = new Booking\Model();
            if ($createTime instanceof Datetime) {
                $booking->setCreatedAt(new Alaress_Date_Format($createTime));
            }
            $booking
                ->setEvent($event)
                ->setResource($resource)
                ->setName($row['Bookingshortname'] ?? '')
                ->setStatus((new Booking\Status\Model())->setId(Booking\Status\Model::STATUS_APPROVED));

            if ($dryRun) {
                $log->notice("No problems found");
            } else {
                try {
                    if (!$eventMapper->save($event)) {
                        $log->error("Event failed to save!");
                    }
                    if (!$bookingMapper->save($booking)) {
                        $log->error("Booking failed to save!");
                    } else {
                        $log->notice("Booking saved [id={$booking->getId()}]");
                    }
                } catch (Exception $e) {
                    $log->error("Exception while importing: " . $e->getMessage());
                }
            }
        }
        exit;
    }
};

$kernel = new CliTool\Kernel($app);
$kernel->runApp();
