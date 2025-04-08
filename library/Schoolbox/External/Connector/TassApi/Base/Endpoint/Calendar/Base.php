<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Calendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Api;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\DSN;
use Schoolbox\GenericCollection;

/**
 * Class Base
 *
 * All derivatives of this behave in the same manner, just with
 * different endpoints/configs. I'm not sure why they're all identical
 * in Tass, but there's a lot of things about them that I don't understand.
 *
 * Anyway, I thought of deploying these functionalities into a Helper class
 * but consciously opted for this instead.
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Calendar
 */
abstract class Base
{
    /**
     * Endpoint specific to this event import
     *
     * @var string
     */
    protected string $endpoint = '';


    /**
     * The RoleType to assign to each event in this import
     *
     * @var string
     */
    protected string $roleType = '';


    /**
     * Base constructor.
     *
     * @param LoggerInterface                           $log
     * @param bool                                      $perform
     * @param array                                     $api
     * @param GenericCollection                         $campuses
     * @param GenericCollection                         $eventTypes
     * @param \Schoolbox\External\Event\Mapper          $eventMapper
     * @param \Schoolbox\External\Event\Roletype\Mapper $roletypeRelationMapper
     * @param \Schoolbox\External\Event\Campus\Mapper   $campusRelationMapper
     * @param string                                    $source
     * @param Registry                                  $registry
     * @param \Alaress_Date_Format                      $from
     * @param \Alaress_Date_Format                      $to
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private array $api,
        private GenericCollection $campuses,
        private GenericCollection $eventTypes,
        private \Schoolbox\External\Event\Mapper $eventMapper,
        private \Schoolbox\External\Event\Roletype\Mapper $roletypeRelationMapper,
        private \Schoolbox\External\Event\Campus\Mapper $campusRelationMapper,
        private string $source,
        private Registry $registry,
        private \Alaress_Date_Format $from,
        private \Alaress_Date_Format $to
    ) {
        /* Obtain events between these two datetimes
         * $this->from = $from;
         * $this->to = $to;
         */

        /* Event source is an arbitrary string that helps identify the thing that imported
         * them into the local event table so you can do a mass remove if needed
         *
         * $this->source = $source;
         */

        /* Remember statistics generated procedurally from all the tass products
         * and dump them all at the very end, together - people weren't reading
         * the logs properly and assumed incorrectly when the last product
         * (typically getTeacherNotices) had 0 events in it. People presumed that
         * to be attributable to the entire import which isn't always true.
         *
         * Therefore collect all those notes and put them all together here
         *
         * $this->registry = $registry;
         */
    }


    /**
     * Perform the import of these events
     *
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \UnexpectedValueException
     */
    public function import(): void
    {
        /* A list of missing event types which the admin needs to configure in schoolbox
         */
        $missingEventTypes = [];

        /* This comes from an API request at TASS
         * We want events bracketed by 1 month in the past and 12 months in the future
         */
        $this->log->info(sprintf(
            'Accessing endpoint %s',
            $this->endpoint
        ));

        $tassEvents = $this->api[DSN::MODULE_SCHOOL_CALENDAR_AND_NOTICES]->retrieveDocument(
            $this->endpoint,
            [
                'start_date' => $this->from->getUsingFormat('d/m/Y'),
                'end_date' => $this->to->getUsingFormat('d/m/Y')
            ],
            Api::VERSION_2
        );

        /* This might be null, or buggy
         * Exit early in that scenario
         */
        if (null === $tassEvents) {
            $this->log->warning(sprintf(
                '%s (%s - %s) returned null; no events found. Skipping',
                $this->endpoint,
                $this->from->getUsingFormat('d/m/Y'),
                $this->to->getUsingFormat('d/m/Y')
            ));

            return;
        }

        /* Message to log to cli, and also to remember for the summary
         */
        $summaryTass = sprintf(
            'Found %s events at endpoint %s between %s and %s',
            \count($tassEvents['events']),
            $this->endpoint,
            $this->from->getUsingFormat('d/m/Y'),
            $this->to->getUsingFormat('d/m/Y')
        );

        $this->log->info($summaryTass);

        $n = 0;

        foreach ($tassEvents['events'] as $tassEvent) {
            /* Spawn a new Event instance and ensure that we tell it where it came from
             * Set the normal Event data fields from the $tassEvent structure
             */
            $title = (string)$tassEvent['title'];
            $location = (string)$tassEvent['location'];

            /* TODO There's another field, $tassEvent['summary'], but I can't see how it is different to
             *  $tassEvent['description'] from the api docs, I'd need real data to know which to use
             *  I suspect it's a shorter version of $tassEvent['description']
             */
            $detail = (string)$tassEvent['description'];

            $allDay = (int)$tassEvent['all_day'];

            /* Datetimes for the event and whether it's an all-day event
             */
            $startDatetime = \Alaress_Date_Format::newDatabaseDate((string)$tassEvent['start']);

            /* All day events need to go to the start of 1 day into the future.
             *
             * We generally use 23:59:59 to indicate "end of day" but some vendors like to incorporate
             * micro- and nano-seconds as part of their definition of "all day". An all day event that
             * spans multiple days - e.g. if ANZAC Day was two days long - you'd need to tell fullcalendar
             * to include the entirety of the 25th and the entirety of the 26th (including micro- and nano-seconds)
             * which means it has to go to the start of the 27th to display the way you'd want.
             *
             * (23:59:59 is not the mathematically-correct end of day)
             */
            if ($allDay) {
                $endDatetime = \Alaress_Date_Format::newDatabaseDate((string)$tassEvent['end'])->addDays(1)->startOfDay();
            } else {
                $endDatetime = \Alaress_Date_Format::newDatabaseDate((string)$tassEvent['end']);
            }

            /* Third-party source identifier for our import
             */
            $guid = (string)$tassEvent['id'];

            /* Event Type equivalent
             */
            $eventType = (string)$tassEvent['cat_desc'];

            /* Assign campus relations
             *
             * Link it to all the campuses in schoolbox unless you can specifically determine
             * a particular campus relation via "campus_code"
             */
            $campuses = $this->findCampuses((string)$tassEvent['campus_code']);

            /* Find an event instance that used guid
             */
            try {
                $event = $this->eventMapper->findOneByColumn('guid', $guid);

                $this->log->debug(sprintf(
                    'Found an existing %s event "%s" (schoolbox eventId %s) using guid %s',
                    $this->source,
                    $event->getTitle(),
                    $event->getId(),
                    $guid
                ));
            } catch (\Schoolbox\Mapper\Exception $e) {
                /* Couldn't find any event matching $guid so it's a new event
                 * Make a new source and make a new event instance
                 */
                $event = $this->eventMapper->createModel();

                $this->log->debug(sprintf(
                    'Creating a new %s event "%s" using guid %s',
                    $this->source,
                    $title,
                    $guid
                ));
            }

            /* Assign the usual details
             */
            $event
                ->setTitle($title)
                ->setDetail($detail)
                ->setLocation($location)
                ->setStartAt($startDatetime)
                ->setFinishAt($endDatetime)
                ->setAllday((bool)$allDay)
                ->setType($eventType)
                ->setClassification(\Schoolbox\External\Event\Model::CLASSIFICATION_EVENT)
                ->setGuid($guid)
                ->setSource($this->source);

            /* Assigned Event Type via the payload's Category field "cat_desc"
             */
            if (!isset($this->eventTypes[$eventType])) {
                $this->log->warning(sprintf(
                    '%s event "%s" has an event type "%s" which is not in admin Event Type list; giving it "%s" anyway',
                    $this->source,
                    $event->getTitle(),
                    $eventType,
                    $this->eventTypes->first()->getType()
                ));

                /* Remember it so we can tell the user which event types were missing
                 */
                $missingEventTypes[$eventType] = $eventType;
            }

            /* Some diagnostic loggings
             */
            $this->log->debug(sprintf(
                '%s event "%s" is in campuses: %s',
                $this->source,
                $event->getTitle(),
                implode(', ', $campuses->getPropertyValues('name'))
            ));

            $this->log->debug(sprintf(
                '%s event "%s" is in role type: %s',
                $this->source,
                $event->getTitle(),
                $this->roleType
            ));

            /* Save it
             */
            if ($this->perform) {
                $this->eventMapper->save($event);

                /* Assign role relations
                 *
                 * These are role-specific and hardcoded per strat, but there might be multiple
                 * instances of the same role type
                 */
                $this->roletypeRelationMapper->save($this->roletypeRelationMapper->createModel()
                    ->setEventId($event->getId())
                    ->setRoleType($this->roleType));

                $this->log->info(sprintf(
                    'Saved %s event "%s" with roletype relationship %s',
                    $this->source,
                    $event->getTitle(),
                    $this->roleType
                ));

                /* Slam in all the relevant campuses
                 */
                foreach ($campuses as $campus) {
                    $this->campusRelationMapper->save($this->campusRelationMapper->createModel()
                        ->setEventId($event->getId())
                        ->setCampusName($campus->getName()));

                    $this->log->info(sprintf(
                        'Saved %s event "%s" with campus relationship %s',
                        $this->source,
                        $event->getTitle(),
                        $campus->getName()
                    ));
                }

                $this->log->info(sprintf(
                    'Saved %s event "%s" with guid %s',
                    $this->source,
                    $event->getTitle(),
                    $event->getGuid()
                ));
            } else {
                $this->log->info(sprintf(
                    'Planning %s event "%s" with guid %s',
                    $this->source,
                    $event->getTitle(),
                    $event->getGuid()
                ));
            }

            /* Increase the count
             */
            $n++;
        }

        /* Tell someone these need to be configured in admin
         */
        $brokenEventTypes = [];

        if (\count($missingEventTypes)) {
            foreach ($missingEventTypes as $missingEventType) {
                $brokenEventTypes[] = $missingEventType;
            }
        }

        /* Sort alphabetically
         */
        sort($brokenEventTypes);

        /* Remember for the summary
         */
        $summarySchoolbox = sprintf(
            'Imported %s events into schoolbox',
            $n
        );

        $summaryData = [
            $summaryTass,
            $summarySchoolbox,
            $brokenEventTypes
        ];

        $this->registry->remember($this->endpoint, $summaryData);
    }


    /**
     * Generate a list of campus ids which might be associated with $campusCode
     *
     * @param string $campusCode
     * @return GenericCollection
     * @throws \UnexpectedValueException
     */
    protected function findCampuses(string $campusCode): GenericCollection
    {
        $campuses = new \Schoolbox\GenericCollection(\Schoolbox\Campus::class);

        foreach ($this->campuses as $campus) {
            if ($campus->getName() === $campusCode || $campus->getExtTimetableCode() === $campusCode) {
                $campuses->add($campus);
            }
        }

        /* If you didn't match any campuses, add them all
         */
        if (!\count($campuses)) {
            return $this->campuses;
        }

        return $campuses;
    }
}
