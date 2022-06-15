<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard\Sessions;

use Psr\Log\LoggerInterface;
use Schoolbox\Clipboard\Hierarchy\Lookup\Person;
use Schoolbox\Clipboard\Hierarchy\Registry;
use Schoolbox\Clipboard\IApi;
use Schoolbox\Clipboard\IStrategy;
use Schoolbox\Config\ConfigMapper;
use Symfony\Component\Lock\LockInterface;

/**
 * Generate a list of Clipboard Sessions to load as schoolbox Matchups
 */
class Sessions implements IStrategy
{
    /**
     * Short-circuit to prevent infinite loops
     */
    private const MAX_CALLS = 400;


    /**
     * Number of results per page
     */
    private const NUM_PER_PAGE = 100;


    /**
     * A placeholder venue if one wasn't specified by Clipboard
     */
    private const UNSPECIFIED_LOCATION = 'Unspecified location';


    /**
     * This is used as the description in the event modal popup
     * There isn't one field that corresponds to that usage, but there
     * is a conglomerate of various fields which result in the same outcome
     *
     * This was first defined by Wesley Melbourne, and now inflicts across
     * all clipboard implementations. Until we get feedback from schools
     * this is the Best Guess for now
     */
    private const NOTES_TEMPLATE = "
        %s
        <p>%s</p>
    ";


    /**
     * This is a giant slab of text that comes from the accumulation of Additional Fields'
     * data which may, or may not, exist. If there's meaningful content, it's appended to
     * the end of NOTES_TEMPLATE, with e.g. a horizontal rule for a separator
     */
    private const ADDITIONAL_FIELDS_TEMPLATE = "
        %s
    ";


    /**
     * This represents the custom fields' key-value pair
     */
    private const ADDITIONAL_FIELDS_TUPLE = "<strong>%s:</strong> %s<br />";


    /**
     * This is used as the description for home team's outcome since all
     * clipboard teams are Home Teams (we never import enemy teams like this)
     */
    private const RESULT_TEMPLATE = "
        Our score: %s<br />
        Opponent score: %s<br />
    ";


    /**
     * Cosmetic strings representing match status, as defined by Clipboard
     */
    private const STATUS_CANCELLED = '<span class="alert-box warning">Cancelled</span>';
    private const STATUS_BYE = '<span class="alert-box highlight">Bye</span>';


    /**
     * Force date params into clipboard sessions endpoint to get a bigger set of data
     * This is measured in months - stave defaults are equivalent to "get events from
     * one month ago till six months in the future"
     */
    private const FROM = -1;
    private const TO = 6;


    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * @var bool
     */
    private bool $perform;


    /**
     * @var LockInterface
     */
    private LockInterface $lock;


    /**
     * @var IApi
     */
    private IApi $api;


    /**
     * @var string
     */
    private string $timezone;


    /**
     * @var Registry
     */
    private Registry $registry;


    /**
     * @var Person
     */
    private \Schoolbox\Clipboard\Hierarchy\Lookup\Person $userLookup;


    /**
     * @var \Schoolbox\Fixtures\Venue\Mapper
     */
    private \Schoolbox\Fixtures\Venue\Mapper $venueMapper;


    /**
     * @var \Schoolbox\Fixtures\Matchup\Mapper
     */
    private \Schoolbox\Fixtures\Matchup\Mapper $matchupMapper;


    /**
     * @var \Schoolbox\Fixtures\Coach\Mapper
     */
    private \Schoolbox\Fixtures\Coach\Mapper $coachMapper;


    /**
     * @var \Schoolbox\Fixtures\Team\Coach\Mapper
     */
    private \Schoolbox\Fixtures\Team\Coach\Mapper $teamCoachMapper;


    /**
     * @var \Schoolbox\Fixtures\Result\Mapper
     */
    private \Schoolbox\Fixtures\Result\Mapper $resultMapper;


    /**
     * @var \Schoolbox\Clipboard\Hierarchy\Codec\Division
     */
    private \Schoolbox\Clipboard\Hierarchy\Codec\Division $divisionCodec;


    /**
     * Constructor.
     *
     * @param LoggerInterface                       $log
     * @param bool                                  $perform
     * @param LockInterface                         $lock
     * @param IApi                                  $api
     * @param string                                $timezone
     * @param Registry                              $registry
     * @param Person                                $userLookup
     * @param \Schoolbox\Fixtures\Venue\Mapper      $venueMapper
     * @param \Schoolbox\Fixtures\Matchup\Mapper    $matchupMapper
     * @param \Schoolbox\Fixtures\Coach\Mapper      $coachMapper
     * @param \Schoolbox\Fixtures\Team\Coach\Mapper $teamCoachMapper
     * @param \Schoolbox\Fixtures\Result\Mapper     $resultMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        LockInterface $lock,
        IApi $api,
        string $timezone,
        Registry $registry,
        Person $userLookup,
        \Schoolbox\Fixtures\Venue\Mapper $venueMapper,
        \Schoolbox\Fixtures\Matchup\Mapper $matchupMapper,
        \Schoolbox\Fixtures\Coach\Mapper $coachMapper,
        \Schoolbox\Fixtures\Team\Coach\Mapper $teamCoachMapper,
        \Schoolbox\Fixtures\Result\Mapper $resultMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->lock = $lock;
        $this->api = $api;
        $this->timezone = $timezone;
        $this->registry = $registry;
        $this->userLookup = $userLookup;
        $this->venueMapper = $venueMapper;
        $this->matchupMapper = $matchupMapper;
        $this->coachMapper = $coachMapper;
        $this->teamCoachMapper = $teamCoachMapper;
        $this->resultMapper = $resultMapper;
        $this->divisionCodec = new \Schoolbox\Clipboard\Hierarchy\Codec\Division();
    }


    /**
     * @return void
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \Symfony\Component\Lock\Exception\LockAcquiringException
     * @throws \Symfony\Component\Lock\Exception\LockConflictedException
     * @throws \UnexpectedValueException
     */
    public function import(): void
    {
        /* TODO: Clipboard is going to provide external ids for us to find
         *  They would appear here, but for now this will be unused
         */
        $userLookup = $this->userLookup->generate();

        /* Consume data from Clipboard endpoint - each Session comes with info about
         * its own hierarchy so map them to schoolbox concepts as best we can
         */
        $endpoint = 'sessions';

        $this->log->info('');

        $this->log->info(sprintf(
            'Downloading data from endpoint: %s (this might take a while)',
            $endpoint
        ));

        /* I guess have an ultralong season to basically remove it from typical usage
         * This season basically encompasses everything so we only need to spawn it once
         */
        $season = $this->registry->recallSeason();

        /* Clipboard didn't implement their default data window as per their docs
         *
         * The default is actually [4 weeks in the past - 4 weeks into the future]
         * not 6 months into the future as stated in their docs
         *
         * Force them to give me more datums
         */
        $startDateTime = \Alaress_Date_Format::now()->addMonths(self::FROM)->getDatabaseDate();
        $endDateTime =  \Alaress_Date_Format::now()->addMonths(self::TO)->getDatabaseDate();

        /* Bash through paginated data
         */
        $currentPage = 1;
        $lastPage = null;

        /* This will be set to something truthy, until the end of
         * the paginated data, at which point it becomes falsy
         */
        $nextUrl = $endpoint;

        while ($nextUrl && (null === $lastPage || $currentPage <= $lastPage) && $currentPage < self::MAX_CALLS) {
            $this->lock->refresh();

            $sessions = $this->api->retrieve('GET', $endpoint, [
                'includeTeams' => 'true',
                'includeStaff' => 'true',
                'includeRoundName' => 'true',
                'includeStatuses' => ['confirmed'],
                /* These might also be known as "Additional Fields" in the clipboard ui
                 */
                'includeCustomFieldsMetadata' => 'true',
                'startDateTime' => $startDateTime,
                'endDateTime' => $endDateTime,
                /* Apparently this should give you "only not cancelled" sessions
                 */
                'cancelled' => 'false',
                /* Bash through paginated data
                 */
                'page' => $currentPage,
                'pageLength' => self::NUM_PER_PAGE
            ]);

            /* Store metadata for custom fields: their types and their labels
             * This is to make their contents presentable in the frontend by
             * appending it to each event's $notes
             *
             * Metadata is supplied in the $session var, not the $session['data']
             * var; it's meant to apply to everything in the payload
             *
             * Therefore we can remember it per endpoint retrieval. Technically
             * we'd only need to remember it once, I think, but I don't really
             * see there being an advantage one way or the other; we canjust use
             * the customFieldsMetadata struct as provided in each $session
             */
            $customFieldsMetadata = $sessions['customFieldsMetadata'] ?? null;

            foreach ($sessions['data'] as $session) {
                /* Sport identifiers
                 */
                $teamActivityId = (int)$session['activity']['id'];
                $teamActivity = $session['activity']['name'] ?? '';

                foreach ($session['teams'] as $teamData) {
                    $this->lock->refresh();

                    /* Division identifiers
                     */
                    $teamCategory = $teamData['category']['name'] ?? '';
                    $teamSubcategory = $teamData['subcategory']['name'] ?? '';

                    /* Team identifiers
                     */
                    $teamId = (int)$teamData['id'];
                    $teamName = $teamData['name'];

                    /* Try to manufacture a division title
                     */
                    $divisionName = $this->divisionCodec->encode($teamCategory, $teamSubcategory, $teamActivity);

                    $this->log->debug(sprintf(
                        'Encoded category/subcategory/activity (%s, %s, %s) into schoolbox division name: %s',
                        $teamCategory,
                        $teamSubcategory,
                        $teamActivity,
                        $divisionName
                    ));

                    /* Try to spawn a corresponding Sport instance
                     */
                    $sport = $this->registry->recallSport($teamActivityId, $teamActivity);

                    /* Try to spawn a corresponding Division instance
                     */
                    $division = $this->registry->recallDivision($season, $teamActivityId, $sport, $divisionName);

                    /* Try to spawn a corresponding Team instance
                     * $teamId is used later to figure out who vs who, when importing Matchups
                     * Teams are indexed by ids for this particular usage, unlike others
                     */
                    $team = $this->registry->recallTeam($teamActivityId, $division, $teamId, $teamName);

                    $this->log->info(sprintf(
                        'Sport: %s (id %s), Division: %s (id %s), Team: %s (id %s)',
                        $sport->getName(),
                        $sport->getId(),
                        $division->getName(),
                        $division->getId(),
                        $team->getName(),
                        $team->getId()
                    ));

                    /* Set up a Matchup instance for this team; they aren't vsing anyone
                     */
                    $venue = $this->findVenue($session['locationData']['name'] ?? null);

                    $matchupName = trim($session['title']);

                    /* Clipboard sends the timestamps as UTC but with no identification that they are UTC timestamps.
                     * so lets force the creation of the date object to be a UTC timezone,
                     * then update that object to the local timezone specified from the DB config.
                     *
                     * The rest of the system expects all dates to be in a local timezone, NOT UTC
                     * https://api-docs.clipboard.app/?id=timestamps
                     *
                     * Sometimes clipboard sends data with microseconds in there, which carbon won't understand
                     * with the date format we want to use - explode it out first
                     *
                     * We set the timezone to be appropriate to wherever the schoolbox instance happens to be
                     * so that frontend can render the UTC timestamp correctly
                     */
                    $startAt = (new \Alaress_Date_Format(explode('.', $session['startDateTime'])[0], \Alaress_Date_Format::DATABASE_FORMAT, new \DateTimeZone('UTC')))
                        ->setTimezone($this->timezone);

                    $finishAt = (new \Alaress_Date_Format(explode('.', $session['endDateTime'])[0], \Alaress_Date_Format::DATABASE_FORMAT, new \DateTimeZone('UTC')))
                        ->setTimezone($this->timezone);

                    /* See if this matchup exists by taking the bestest guess,
                     * then update its Notes in case it changed somehow in Clipboard
                     */
                    try {
                        $matchup = $this->matchupMapper->findOneByColumnList([
                            'name' => $matchupName,
                            'division_id' => $division->getId(),
                            'season_id' => $season->getId(),
                            'home_team_id' => $team->getId(),
                            'start_at' => $startAt->getDatabase(),
                            'finish_at' => $finishAt->getDatabase(),
                        ]);

                        $this->log->debug(sprintf(
                            'Updating matchup %s on %s, at venue %s',
                            $matchup->getDisplayName(),
                            $matchup->getStartAt()->getDatabase(),
                            null === $matchup->getVenue() ? self::UNSPECIFIED_LOCATION : $matchup->getVenue()->getName()
                        ));
                    } catch (\Schoolbox\Mapper\Exception $e) {
                        $matchup = $this->matchupMapper->createModel()
                            ->setName($matchupName)
                            ->setSeason($season)
                            ->setDivision($division)
                            ->setVenue($venue)
                            ->setHomeTeam($team)
                            ->setStartAt($startAt)
                            ->setFinishAt($finishAt);

                        $this->log->debug(sprintf(
                            'Creating matchup %s on %s, at venue %s',
                            $matchup->getDisplayName(),
                            $matchup->getStartAt()->getDatabase(),
                            null === $matchup->getVenue() ? self::UNSPECIFIED_LOCATION : $matchup->getVenue()->getName()
                        ));
                    }

                    /* Slap in a team coach
                     */
                    foreach ($session['assignedStaff'] as $assignedStaff) {
                        $this->assignCoach($this->findCoach($assignedStaff), $team);
                    }

                    /* Event description contains some data that could be updated in Clipboard,
                     * forcing us to update the $notes every time. We can't skip over matchups
                     * which existed previously anymore.
                     */
                    $notes = sprintf(
                        self::NOTES_TEMPLATE,
                        $this->generateStatus($session),
                        nl2br(trim((string)$session['studentParentNotes']))
                    );

                    /* Cram in a set of custom fields' metadata contents into $notes
                     * by collating all the custom metadata records into one big slab
                     */
                    if ($customFieldsMetadata && $session['customFieldsData']) {
                        $customFields = [];

                        foreach ($session['customFieldsData'] as $key => $value) {
                            /* Look up what this was meant to be called
                             */
                            $label = $customFieldsMetadata[$key]['label'];

                            /* If there's a meaningful value, stack it up
                             * Otherwise ignore it
                             */
                            if ('' !== trim((string)$value)) {
                                $customFields[] = sprintf(
                                    self::ADDITIONAL_FIELDS_TUPLE,
                                    $label,
                                    $value
                                );
                            }
                        }

                        /* Use a standard html break for a separator
                         * I assume each key/value appears on its own line
                         */
                        if (\count($customFields)) {
                            /* customFieldMetadata is a set of key-value pairs:
                             *    busDepartureTime
                             *    studentArrivalTime
                             *    coachstaffContactDetails
                             *
                             * so I guess we just concatenate all their values for now
                             */
                            $notes .= sprintf(
                                self::ADDITIONAL_FIELDS_TEMPLATE,
                                implode('', $customFields)
                            );
                        }
                    }

                    /* Event description contains some data that could be updated in Clipboard,
                     * forcing us to update the $notes every time. We can't skip over matchups
                     * which existed previously anymore.
                     */
                    $matchup->setNotes($notes);

                    /* We saw this, so we keep this
                     */
                    if ($this->perform) {
                        $this->matchupMapper->save($matchup);
                    }

                    /* Figure out existing results for the home team
                     * then update it to whatever it currently is
                     */
                    try {
                        $result = $this->resultMapper
                            ->findOneByColumnList([
                                'match_id' => $matchup->getId(),
                                'team_id' => $team->getId()
                            ]);
                    } catch (\Schoolbox\Mapper\Exception $e) {
                        $result = $this->resultMapper->createModel()
                            ->setMatchup($matchup)
                            ->setTeam($team);
                    }

                    /* Update home team's description with whatever it is now
                     */
                    $result->setDescription($this->generateResult($result, $session));

                    if ($this->perform) {
                        $this->resultMapper->save($result);
                    }
                }
            }

            /* Bash through paginated data
             */
            $lastPage = $sessions['pagination']['lastPage'];

            if ($currentPage >= $lastPage) {
                $nextUrl = null;
            }

            $currentPage++;
        }
    }


    /**
     * Assign a coach to a team, if they aren't already assigned
     *
     * @param \Schoolbox\Fixtures\Coach\Model $coach
     * @param \Schoolbox\Fixtures\Team\Model  $team
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function assignCoach(\Schoolbox\Fixtures\Coach\Model $coach, \Schoolbox\Fixtures\Team\Model $team): void
    {
        try {
            $teamCoach = $this->teamCoachMapper->findOneByColumnList([
                'team_id' => $team->getId(),
                'coach_id' => $coach->getId()
            ]);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $teamCoach = $this->teamCoachMapper->createModel()
                ->setTeam($team)
                ->setCoach($coach);

            if ($this->perform) {
                $this->teamCoachMapper->save($teamCoach);
            }
        }
    }


    /**
     * Find a coach for the given Clipboard data $coach
     * Creates a coach record if they couldn't be found
     *
     * @param array $coach
     * @return \Schoolbox\Fixtures\Coach\Model
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function findCoach(array $coach): \Schoolbox\Fixtures\Coach\Model
    {
        $coachName = "{$coach['firstName']} {$coach['lastName']}";

        try {
            $coach = $this->coachMapper->findOneByColumnList([
                'name' => $coachName
            ]);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $coach = $this->coachMapper->createModel()
                ->setName($coachName);
        }

        if ($this->perform) {
            $this->coachMapper->save($coach);
        }

        return $coach;
    }


    /**
     * Find a way to generate a corresponding Venue
     * If a venue name was not specified, generate a placeholder
     * (venues are optional in clipboard)
     *
     * @param string|null $venueName
     * @return \Schoolbox\Fixtures\Venue\Model|null
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \UnexpectedValueException
     */
    private function findVenue(?string $venueName): ?\Schoolbox\Fixtures\Venue\Model
    {
        /* If venue name wasn't specified, find a placeholder
         * generating one if it doesn't already exist
         */
        if ('' === trim((string)$venueName)) {
            return null;
        }

        /* Set up a Venue instance for this Matchup,
         * reusing an existing one if possible
         */
        try {
            $venue = $this->venueMapper->findOneByColumn('name', $venueName);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $venue = $this->venueMapper->createModel()
                ->setName($venueName);

            if ($this->perform) {
                $this->venueMapper->save($venue);
            }
        }

        return $venue;
    }


    /**
     * Decide a little html fragment to represent the boolean status of this match:
     * Bye, or not a Bye; or if a match is cancelled
     *
     * @param array $session
     * @return string
     */
    private function generateStatus(array $session): string
    {
        if ($session['cancelled']) {
            return self::STATUS_CANCELLED;
        }

        if ($session['bye']) {
            return self::STATUS_BYE;
        }

        /* Nothing special to tell people about; just use empty string
         */
        return '';
    }


    /**
     * Generate a slab of text for the home team's result description
     *
     * @param \Schoolbox\Fixtures\Result\Model $result
     * @param array                            $session
     * @return string
     */
    private function generateResult(\Schoolbox\Fixtures\Result\Model $result, array $session): string
    {
        /* Neither team has anything interesting in the results fields
         * Nothing to say
         */
        if ('' === trim((string)$session['organisationScore']) && '' === trim((string)$session['opponentScore'])) {
            return '';
        }

        return sprintf(
            self::RESULT_TEMPLATE,
            $session['organisationScore'] ?? '',
            $session['opponentScore'] ?? ''
        );
    }
}
