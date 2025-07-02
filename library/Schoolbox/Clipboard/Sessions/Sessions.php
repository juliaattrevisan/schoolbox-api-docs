<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard\Sessions;

use Alaress\Database\Predicate\InvalidColumnException;
use Psr\Log\LoggerInterface;
use Schoolbox\Clipboard\Hierarchy\Codec\Division;
use Schoolbox\Clipboard\Hierarchy\Lookup\Person;
use Schoolbox\Clipboard\Hierarchy\Registry;
use Schoolbox\Clipboard\IApi;
use Schoolbox\Clipboard\IStrategy;
use Schoolbox\Fixtures\Venue\Mapper;
use Schoolbox\Fixtures\Venue\Model;
use Schoolbox\Mapper\Exception;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
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
     * Whitelist meaningful results
     *     W     We won
     *     L     We lost
     *     D     It's a draw
     *     F     We forfeited
     *     OF    Opponent forfeited
     */
    private const VALID_RESULT_OUTCOMES = ['W', 'L', 'D', 'F', 'OF'];


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
     * @var Division
     */
    private Division $divisionCodec;


    /**
     * Constructor.
     *
     * @param LoggerInterface                    $log
     * @param bool                               $perform
     * @param bool                               $partial
     * @param LockInterface                      $lock
     * @param IApi                               $api
     * @param string                             $timezone
     * @param Registry                           $registry
     * @param Person                             $userLookup
     * @param Mapper                             $venueMapper
     * @param \Schoolbox\Fixtures\Matchup\Mapper $matchupMapper
     * @param \Schoolbox\Fixtures\Result\Mapper  $resultMapper
     * @param \Schoolbox\Clipboard\Log\Mapper    $importLogMapper
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private bool $partial,
        private LockInterface $lock,
        private IApi $api,
        private string $timezone,
        private Registry $registry,
        private Person $userLookup,
        private \Schoolbox\Fixtures\Venue\Mapper $venueMapper,
        private \Schoolbox\Fixtures\Matchup\Mapper $matchupMapper,
        private \Schoolbox\Fixtures\Result\Mapper $resultMapper,
        private \Schoolbox\Clipboard\Log\Mapper $importLogMapper
    ) {
        $this->divisionCodec = new Division();
    }


    /**
     * @return void
     * @throws Exception
     * @throws InvalidColumnException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws LockAcquiringException
     * @throws LockConflictedException
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
        $endDateTime = \Alaress_Date_Format::now()->addMonths(self::TO)->getDatabaseDate();

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

            $args = [
                'includeTeams' => 'true',
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
            ];

            /* In Partial Mode, specify two additional datetimes
             */
            if ($this->partial) {
                $updatedAfter = $this->importLogMapper->findNextPartialDate()->toIso8601String();
                $updatedBefore = \Alaress_Date_Format::now()->toIso8601String();

                $this->log->info(sprintf(
                    'Obtaining partial updates for Sessions updated between %s and %s',
                    $updatedAfter,
                    $updatedBefore
                ));

                $args['updatedAfter'] = $updatedAfter;
                $args['updatedBefore'] = $updatedBefore;
            }

            $sessions = $this->api->retrieve('GET', $endpoint, $args);

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
                    /* Division identifiers
                     */
                    $teamCategoryId = $teamData['category']['id'] ?? '';
                    $teamCategory = $teamData['category']['name'] ?? '';
                    $teamSubcategoryId = $teamData['subcategory']['id'] ?? '';
                    $teamSubcategory = $teamData['subcategory']['name'] ?? '';

                    /* Team identifiers
                     */
                    $teamId = (int)$teamData['id'];
                    $teamName = $teamData['name'];

                    /* Assemble a proxy representation of a "division" which is an amalgamation
                     * of Clipboard categories' and Clipboard subcategories' ids. This is synonymous
                     * with encoding a division name, just with ids, and results in same format.
                     *
                     * Division external id defaults to the activity name when both category and
                     * subcategory weren't specified.
                     */
                    $divisionId = $this->divisionCodec->encode((string)$teamCategoryId, (string)$teamSubcategoryId, $teamActivity);

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
                    $division = $this->registry->recallDivision($season, $teamActivityId, $sport, $divisionId, $divisionName);

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
                    $venue = $this->findVenue($session['locationData'] ?? []);

                    $matchupName = trim($session['title'] ?? ''); // Some sessions don't have a title.

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

                    $finishAt = (
                        $session['endDateTime'] === null
                        ? null()
                        : (new \Alaress_Date_Format(explode('.', $session['endDateTime'])[0], \Alaress_Date_Format::DATABASE_FORMAT, new \DateTimeZone('UTC')))
                    )?->setTimezone($this->timezone);

                    /* See if this matchup exists by taking the bestest guess,
                     * then update its Notes in case it changed somehow in Clipboard
                     */
                    $matchId = $session['id'];

                    try {
                        $matchup = $this->matchupMapper->findOneByColumnList([
                            'external_id' => $matchId,
                            'home_team_id' => $team->getId()
                        ]);

                        $this->log->debug(sprintf(
                            'Updating matchup %s on %s, at venue %s',
                            $matchup->getDisplayName(),
                            $matchup->getStartAt()->getDatabase(),
                            null === $matchup->getVenue() ? self::UNSPECIFIED_LOCATION : $matchup->getVenue()->getName()
                        ));

                        $action = 'Updating';
                    } catch (\Schoolbox\Mapper\Exception $e) {
                        $action = 'Creating';

                        $matchup = $this->matchupMapper->createModel()
                            ->setExternalId((string)$matchId)
                            ->setHomeTeam($team);
                    }

                    /* You just can't tell what might have caused this to appear here
                     *
                     * TODO: Perhaps do a more surgical diff to avoid the SUDS-like
                     *  indexing problem when we change too many columns, but Clipboard
                     *  data is a far smaller footprint than SUDS
                     */
                    $matchup
                        ->setName($matchupName)
                        ->setSeason($season)
                        ->setDivision($division)
                        ->setVenue($venue)
                        ->setStartAt($startAt)
                        ->setFinishAt($finishAt);

                    $this->log->debug(sprintf(
                        '%s matchup %s on %s, at venue %s',
                        $action,
                        $matchup->getDisplayName(),
                        $matchup->getStartAt()->getDatabase(),
                        null === $matchup->getVenue() ? self::UNSPECIFIED_LOCATION : $matchup->getVenue()->getName()
                    ));

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

                        $customFieldLists = (new CustomFields())->generate($customFieldsMetadata);

                        foreach ($customFieldLists as $type => $customFieldList) {
                            $this->log->debug(sprintf(
                                'Inspecting "%s" custom fields in customFieldLists...',
                                $type
                            ));

                            foreach ($customFieldList as $key => $fieldData) {
                                /* $sessions[customFieldsData] contains a different list than the one we're currently consuming
                                 * Skip over it
                                 */
                                if (!array_key_exists($key, $session['customFieldsData'])) {
                                    $this->log->debug(sprintf(
                                        'Skipping custom field metadata for "%s" - that is not in session[customFieldsData] list which contains the following keys directly from the API: %s',
                                        $key,
                                        implode(', ', array_keys($session['customFieldsData']))
                                    ));

                                    continue;
                                }

                                $label = (string)$customFieldsMetadata[$key]['label'];

                                /* We only care when Custom Fields are viewable by schoolbox users:
                                 *     * guardian
                                 *     * student
                                 *
                                 * Schools sometimes use staff-only custom metadata fields which
                                 * aren't intended for schoolbox users to see
                                 */
                                if (!$this->visibleCustomMetadataField($customFieldsMetadata[$key])) {
                                    $this->log->debug(sprintf(
                                        'Skipping custom field metadata for "%s"; it was not meant for students/parents',
                                        $label
                                    ));

                                    continue;
                                }

                                /* If there's a meaningful value, stack it up
                                 * Otherwise ignore it
                                 *
                                 * Clipboard can send non-strings like arrays and objects here, which is odd.
                                 * Can't do much with those, so skip them. The current state of this implementation
                                 * is that they could potentially send you an array of clipboard-specific ids
                                 * which is mostly useless to us at the moment; those are the only known non-string
                                 * datatypes that clipboard is intending to send for now.
                                 *
                                 * Clipboard themselves recommended against processing these arrays of clipboard
                                 * ids...the sis probably doesn't even know about it:
                                 *
                                 * https://alaress.slack.com/archives/C01CQG1SCF4/p1715928707969979
                                 * "This may not be ideal for Schoolbox currently and for now it may be best to
                                 * just not display those specific custom fields." -- Clipboard James, May 20
                                 */
                                $value = $session['customFieldsData'][$key];

                                if (is_scalar($value)) {
                                    if ('' !== trim((string)$value)) {
                                        $customFields[] = sprintf(
                                            self::ADDITIONAL_FIELDS_TUPLE,
                                            $label,
                                            $value
                                        );
                                    } else {
                                        $this->log->debug(sprintf(
                                            'Skipping custom field metadata for "%s"; empty value',
                                            $label,
                                        ));
                                    }
                                } else {
                                    $this->log->debug(sprintf(
                                        'Skipping custom field metadata for "%s"; not a scalar data type',
                                        $label,
                                    ));
                                }
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
                            $notes .= trim(sprintf(
                                self::ADDITIONAL_FIELDS_TEMPLATE,
                                implode('', $customFields)
                            ));

                            $this->log->debug(sprintf(
                                "Found the following custom field data: %s\n    ",
                                $notes
                            ));
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
                    $resultDescription = $this->generateResult($session);

                    /* If the resultDescription is null, it means this matchup
                     * shouldn't have a result; delete all results if they existed
                     */
                    if (null === $resultDescription) {
                        foreach ($matchup->getResults() as $currentResult) {
                            $this->resultMapper->delete($currentResult);
                        }
                    } else {
                        /* There was something meaningful to say; save a new Result
                         * if one doesn't already exist for this matchup and team
                         */
                        try {
                            $result = $this->resultMapper->findOneByColumnList([
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
                        $result->setDescription($resultDescription);

                        if ($this->perform) {
                            $this->resultMapper->save($result);
                        }
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
     * Find a way to generate a corresponding Venue
     * If a venue name was not specified, generate a placeholder
     * (venues are optional in clipboard)
     *
     * @param array|null $locationData
     * @return Model|null
     * @throws \PDOException
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    private function findVenue(?array $locationData): ?\Schoolbox\Fixtures\Venue\Model
    {
        /* Name or address not set?
         * Can't work with this; just leave
         */
        if (!isset($locationData['name'], $locationData['address'])) {
            return null;
        }

        /* Mine the clipboard data for venue info
         * Beware: sometimes fields won't be provided to you, despite the documentation
         *
         * Latitude and Longitude fields might not even be here
         * Sometimes it is, sometimes it isn't
         */
        $venueData = [
            'external_id' => trim((string)$locationData['id']),
            'name' => trim((string)$locationData['name']),
            'address' => trim((string)$locationData['address']),
            'latitude' => trim((string)($locationData['latitude'] ?? null)),
            'longitude' => trim((string)($locationData['longitude'] ?? null))
        ];

        /* Set up a Venue instance for this Matchup,
         * reusing an existing one if possible
         */
        try {
            $venue = $this->venueMapper->findOneByColumn('external_id', $venueData['external_id']);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $venue = $this->venueMapper->createModel()
                ->setName($venueData['name'])
                ->setExternalId($venueData['external_id']);
        }

        /* Update existing address to whatever it now is, in case:
         *
         * a) you've already imported previously and didn't have an address back then, or...
         * b) you had a venue called something generic like "The Usual Place" which has
         *    a different address contextually and just changes throughout the year
         */
        $venue->setAddress($venueData['address'])
            ->setLatitude($venueData['latitude'])
            ->setLongitude($venueData['longitude']);

        if ($this->perform) {
            $this->venueMapper->save($venue);
        }

        return $venue;
    }


    /**
     * This decides whether a metadata field was visible to schoolbox users.
     * Any metadata field with EITHER 'guardian' and/or 'student' will be imported
     * into the $notes for a Matchup.
     *
     * The clipboard ui seems to put 'guardian' and 'student' into this array together,
     * but there might be some other place which only puts one of those values here
     *
     * The array_intersect() + empty() will cover both use cases
     *
     * @param array $metadataField
     * @return bool
     */
    private function visibleCustomMetadataField(array $metadataField): bool
    {
        /* Maybe one day this is significant enough to warrant evolution into private const
         * but this is its only usage so far
         */
        $visibleToSchoolbox = ['guardian', 'student'];

        /* Empty array means neither 'guardian' nor 'student' was listed here
         * so it should be hidden from schoolbox
         */
        return !empty(array_intersect($visibleToSchoolbox, $metadataField['showToUserTypes'] ?? []));
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
     * @param array         $session
     * @return string|null
     */
    private function generateResult(array $session): ?string
    {
        /* Whitelist meaningful results
         *     W     We won
         *     L     We lost
         *     D     It's a draw
         *     F     We forfeited
         *     OF    Opponent forfeited
         *
         * Confirmed with Ed Kolyer that 'NR' means "No Results" and is functionally
         * equivalent to a null
         *
         * Not in any of the valid result outcomes? Nothing to say
         *
         * Exit early
         */
        if (!\in_array($session['result'], self::VALID_RESULT_OUTCOMES, true)) {
            $this->log->debug('No valid results recorded; skipping result creation.');

            return null;
        }

        /* Neither team has anything interesting in the results fields
         * Nothing to say
         */
        if ('' === trim((string)$session['organisationScore']) && '' === trim((string)$session['opponentScore'])) {
            $this->log->debug('Our score and opponent scores are empty; skipping result creation.');

            return null;
        }

        /* I guess something meaningful happened here
         */
        $result = sprintf(
            self::RESULT_TEMPLATE,
            $session['organisationScore'],
            $session['opponentScore']
        );

        $this->log->debug(sprintf(
            'Found result for this matchup: %s',
            $result
        ));

        return $result;
    }
}
