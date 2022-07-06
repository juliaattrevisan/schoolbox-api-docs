<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Import;

use Alaress\Web\Controller\Base;
use Alaress\Csv\Reader;
use Schoolbox\Collection;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Fixtures\Sport\Model as Sport;
use Schoolbox\Fixtures\Sport\Mapper as SportMapper;
use Schoolbox\Fixtures\Season\Model as Season;
use Schoolbox\Fixtures\Season\Mapper as SeasonMapper;
use Schoolbox\Fixtures\Division\Model as Division;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\Team\Model as Team;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Matchup\Model as Matchup;
use Schoolbox\Fixtures\Matchup\Mapper as MatchupMapper;
use Schoolbox\Fixtures\ItineraryItem\Model as ItineraryItem;
use Schoolbox\Fixtures\ItineraryItem\Mapper as ItineraryItemMapper;
use Schoolbox\Fixtures\Venue\Model as Venue;
use Schoolbox\Fixtures\Venue\Mapper as VenueMapper;
use Schoolbox\Fixtures\School\Model as School;
use Schoolbox\Fixtures\School\Mapper as SchoolMapper;
use Schoolbox\Fixtures\Position\Model as Position;
use Schoolbox\Fixtures\Position\Mapper as PositionMapper;
use Schoolbox\Fixtures\Status\Model as Status;
use Schoolbox\Fixtures\Status\Mapper as StatusMapper;
use Schoolbox\Fixtures\Coach\Model as Coach;
use Schoolbox\Fixtures\Coach\Mapper as CoachMapper;
use Schoolbox\Fixtures\Team\Player\Model as TeamPlayer;
use Schoolbox\Fixtures\Team\Player\Mapper as TeamPlayerMapper;
use Schoolbox\Fixtures\Team\Coach\Model as TeamCoach;
use Schoolbox\Fixtures\Team\Coach\Mapper as TeamCoachMapper;
use Schoolbox\Fixtures\Division\User\Model as DivisionUser;
use Schoolbox\Fixtures\Division\User\Mapper as DivisionUserMapper;
use Schoolbox\Fixtures\Import\Mapper as ImportMapper;
use Schoolbox\Fixtures\Import\Model as Import;
use Schoolbox\Fixtures\Import\Errors\Mapper as ImportErrorsMapper;
use Schoolbox\Fixtures\Import\Errors\Model as ImportErrors;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\UserMapper;

/**
 * Class Controller
 *
 * We allow the user to upload everything in either a single file, or separate files- thus we iterate over all attached
 * CSV files. We need to grab items in a particular order thus we need to loop through the files multiple times to make sure we
 * got everything
 *
 * ------------------------------------------------------------------------------------------------------------------------
 * CSV format
 * ------------------------------------------------------------------------------------------------------------------------
 * SPORT,name,url
 * POSITION,sport,name
 * VENUE,name,address,latitude,longitude
 * SCHOOL,name
 * STATUS,name
 * SEASON,name,start,end
 * DIVISION,season,sport,name,url,player1,player2,player3...player(n)
 * TEAM,season,sport,division,name,url,player1,player2,player3...player(n)
 * COACH,season,sport,division,team,coach1,coach2,coach3...coach(n)
 * FIXTURE,season,sport,division,name.date.time,venue,home_team_or_school,away_team_or_school,note,itinerary_date1,itinerary_time1,itinerary_description1...itinerary_date(n),itinerary_time(n),itinerary_description(n)
 * ------------------------------------------------------------------------------------------------------------------------
 *
 * The first column indicates type of entity (sport, position, venue, school, status, season, division, team, coach, fixture)
 * All player/coach ID's are expected to be the external ID
 * For the purposes of name matching all strings are trimmed and converted to lower case
 * Entities can appear in any order, code does multiple passes across all uploaded files looking for entities in a logical order
 *
 * @package Schoolbox\Fixtures\Import
 */
class Controller extends Base
{
    // These are the keys used in the first column of the spreadsheet to denote entity type

    private const TYPE_SPORT = 'sport';
    private const TYPE_POSITION = 'position';
    private const TYPE_VENUE = 'venue';
    private const TYPE_SCHOOL = 'school';
    private const TYPE_STATUS = 'status';
    private const TYPE_SEASON = 'season';
    private const TYPE_DIVISION = 'division';
    private const TYPE_TEAM = 'team';
    private const TYPE_COACH = 'coach';
    private const TYPE_FIXTURE = 'fixture';

    private const STATUS_RUNNING = 'started';
    private const STATUS_FAILED = 'failed';
    private const STATUS_COMPLETED = 'completed';

    private const RESULT_SUCCESS = 'success';
    private const RESULT_ERROR = 'error';

    /**
     * @var array
     */
    private $disableCache = ['cache' => false];

    /**
     * @var Import $import
     */
    private $import;

    /**
     * @var string $result
     */
    private $result = '';

    /**
     * @var SportMapper
     */
    protected $sportMapper;

    /**
     * @var CoachMapper
     */
    protected $coachMapper;

    /**
     * @var SeasonMapper
     */
    protected $seasonMapper;

    /**
     * @var DivisionMapper
     */
    protected $divisionMapper;

    /**
     * @var DivisionUserMapper
     */
    protected $divisionUserMapper;

    /**
     * @var TeamMapper
     */
    protected $teamMapper;

    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var MatchupMapper
     */
    protected $matchupMapper;

    /**
     * @var VenueMapper
     */
    protected $venueMapper;

    /**
     * @var ItineraryItemMapper
     */
    protected $itineraryItemMapper;

    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * @var SchoolMapper
     */
    protected $schoolMapper;

    /**
     * @var PositionMapper
     */
    protected $positionMapper;

    /**
     * @var StatusMapper
     */
    protected $statusMapper;

    /**
     * @var ImportMapper
     */
    protected $importMapper;

    /**
     * @var ImportErrorsMapper
     */
    protected $importErrorsMapper;

    /**
     * @var TeamPlayerMapper
     */
    protected $teamPlayerMapper;

    /**
     * @var TeamCoachMapper
     */
    protected $teamCoachMapper;

    /**
     * Controller constructor.
     */
    public function __construct(private FilesystemProxy $filesystemProxy)
    {
        $this->setUserMapper(UserMapper::getInstance());
        $this->setMatchupMapper(MatchupMapper::getInstance());
        $this->setSportMapper(SportMapper::getInstance());
        $this->setCoachMapper(CoachMapper::getInstance());
        $this->setSeasonMapper(SeasonMapper::getInstance());
        $this->setDivisionMapper(DivisionMapper::getInstance());
        $this->setDivisionUserMapper(DivisionUserMapper::getInstance());
        $this->setTeamMapper(TeamMapper::getInstance());
        $this->setItineraryItemMapper(ItineraryItemMapper::getInstance());
        $this->setVenueMapper(VenueMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
        $this->setSchoolMapper(SchoolMapper::getInstance());
        $this->setPositionMapper(PositionMapper::getInstance());
        $this->setStatusMapper(StatusMapper::getInstance());
        $this->setTeamPlayerMapper(TeamPlayerMapper::getInstance());
        $this->setTeamCoachMapper(TeamCoachMapper::getInstance());
        $this->setImportMapper(ImportMapper::getInstance());
        $this->setImportErrorsMapper(ImportErrorsMapper::getInstance());
    }

    /**
     * Update import progress in log table
     * @param string                            $result (success/error)
     * @param \Schoolbox\Resource\Storage\Model $file   File currently being processed
     * @param int                               $line   Line number
     * @param int                               $column Column number
     * @param string                            $text   Message to add to log
     * @param string                            $status Status of CSV import
     * @throws MapperException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function updateLog($result, $file, $line, $column, $text, $status = Controller::STATUS_RUNNING)
    {
        // Record error

        $importError = new ImportErrors();
        $importError->setImportId($this->import->getId());
        $importError->setLine($line);
        $importError->setColumn($column);

        if ($file != null) {
            $importError->setHash($file->getHash());
            $importError->setFilename("{$file->getFilename()}.{$file->getExtension()}");
        }

        $importError->setResult($text);
        $importError->setCreatedAt(\Alaress_Date_Format::now());
        $importError->setUpdatedAt(\Alaress_Date_Format::now());
        $importError->setType($result);
        $this->getImportErrorsMapper()->save($importError);

        // Update import status

        $this->import->setStatus($status);
        $this->import->setCreatedAt(\Alaress_Date_Format::now());
        $this->import->setUpdatedAt(\Alaress_Date_Format::now());
        $this->getImportMapper()->save($this->import);
    }

    /**
     * Insert an attach zone into the template
     *
     * @param Collection $attachments
     * @return array
     */
    private function getAttachZone($attachments = null)
    {
        $zone = new Zone('attachment');
        $zone
            ->setThumbnailSize(64)
            ->setModalTitle('Select CSV')
            ->setAttachmentsLabel('')
            ->setAttachPrompt('Upload CSV')
            ->setReadOnly(false)
            ->setMulti(true)
            ->attachFiles($attachments ?? []);

        return $zone->getTwigContext();
    }

    /**
     * CSV upload form and instructions
     */
    public function getUpload()
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesImport/upload.twig', [
            'attachzone' => $this->getAttachZone()
        ]));
    }

    /**
     * Show import progress
     * @param int $id Import ID
     * @param int $skip Number of items to skip
     */
    public function getImportProgress($id, $skip = 0)
    {
        try {
            $import = $this->getImportMapper()->findById($id);
            $importErrors = $this->getImportErrorsMapper()->findByColumn('import_id', $id)->sortBy('id', 'asc');

            $result = [];
            $result['status'] = $import->getStatus();
            $result['errors'] = [];

            $count = 0;

            /** @var ImportErrors $error */
            foreach ($importErrors as $error) {
                if ($skip > 0) {
                    $render = false;
                    $count++;
                    if ($count > $skip) {
                        $render = true;
                    }
                } else {
                    $render = true;
                }

                if ($render) {
                    $result['errors'][] = [
                        'created_at' =>
                            $error->getCreatedAt()->get24Hour() . ":" .
                            ($error->getCreatedAt()->getMinute() < 10 ? '0' . $error->getCreatedAt()->getMinute() : $error->getCreatedAt()->getMinute()) . ":" .
                            ($error->getCreatedAt()->getSecond() < 10 ? '0' . $error->getCreatedAt()->getSecond() : $error->getCreatedAt()->getSecond()),
                        'result' => $error->getResult(),
                        'filename' => $error->getFilename(),
                        'line' => $error->getLine(),
                        'type' => $error->getType(),
                        'column' => $error->getColumn()
                    ];
                }
            }

            $this->getResponse()->content->set([
                'format' => 'json',
                'data' => $this->buildJsonOutput($result)
            ]);
        } catch (MapperException $e) {
            // Requested import didn't exist,

            $this->getResponse()->content->set([
                'format' => 'json',
                'data' => []
            ]);
        }
    }

    /**
     * Reverse geocode an address
     * @param string $string
     * @return array|null
     */
    private function lookupAddress($string)
    {

        $string = str_replace(" ", "+", urlencode($string));
        $details_url = "http://maps.googleapis.com/maps/api/geocode/json?address=" . $string . "&sensor=false";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $details_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = json_decode(curl_exec($ch), true);

        // If Status Code is ZERO_RESULTS, OVER_QUERY_LIMIT, REQUEST_DENIED or INVALID_REQUEST
        if ($response['status'] != 'OK') {
            return null;
        }

        $geometry = $response['results'][0]['geometry'];

        $array = array(
            'longitude' => $geometry['location']['lng'],
            'latitude' => $geometry['location']['lat'],
            'location_type' => $geometry['location_type'],
        );

        return $array;
    }

    /**
     * Display import status
     * @param int $id
     */
    public function getImport($id)
    {
        $this->getResponse()->content->set([
            'template' => '@sportsfixturesImport/importing.twig',
            'data' => array(
                'id' => $id
            )
        ]);
    }

    /**
     * Parse uploaded CSV and process the import in a detached state
     */
    public function postUpload()
    {
        $post = $this->getRequest()->post;

        // We are going to read each line of the CSV data into arrays, process each line looking to see if a matching record already
        // exists in the corresponding table, creating it if it doesn't and updating its details if it does.

        if (is_array($post['attachment'])) {
            // Do the import in a detached state so it doesn't time out

            // Create a log entry

            // Start logging import progress

            $this->import = new Import();
            $this->import->setOwnerId($this->getUser()->getId());
            $this->import->setStatus(Controller::STATUS_RUNNING);
            $this->import->setResult($this->result);
            $this->import->setCreatedAt(\Alaress_Date_Format::now());
            $this->import->setUpdatedAt(\Alaress_Date_Format::now());
            $this->getImportMapper()->save($this->import);

            $this->updateLog(Controller::RESULT_SUCCESS, null, 0, 0, "Import started");

            $this->getResponse()->content->set([
                'format' => 'noContent',
                'postOutput' => [

                    function () use ($post) {

                        // Get all uploaded files

                        $attachmentObjects = (new CollectorFactory())->build($post['attachment'])->getAll();

                        // Create blank arrays to hold everything

                        // Begin the import looking for entities that aren't dependent on others

                        try {
                            /** @var \Schoolbox\Resource\Storage\Model $storageObject */
                            foreach ($attachmentObjects as $storageObject) {
                                $this->loadSports($storageObject);
                                $this->loadSeasons($storageObject);
                                $this->loadVenues($storageObject);
                                $this->loadSchools($storageObject);
                                $this->loadStatuses($storageObject);
                            }

                            // Now we can load positions and division

                            /** @var \Schoolbox\Resource\Storage\Model $storageObject */
                            foreach ($attachmentObjects as $storageObject) {
                                $this->loadPositions($storageObject);
                            }

                            // Now we can load teams

                            /** @var \Schoolbox\Resource\Storage\Model $storageObject */
                            foreach ($attachmentObjects as $storageObject) {
                                $this->loadTeams($storageObject);
                            }

                            // Now we can load coaches

                            /** @var \Schoolbox\Resource\Storage\Model $storageObject */
                            foreach ($attachmentObjects as $storageObject) {
                                $this->loadCoaches($storageObject);
                                $this->loadFixtures($storageObject);
                            }

                            // Success, update the status

                            $this->updateLog(Controller::RESULT_SUCCESS, null, 0, 0, "Import completed", Controller::STATUS_COMPLETED);
                        } catch (\Exception $e) {
                            // Failed, record the error message

                            $this->updateLog(Controller::RESULT_ERROR, null, 0, 0, "Import failed - {$e->getMessage()} {$e->getTraceAsString()}", Controller::STATUS_FAILED);
                        }

                        // All done!
                    }
                ],
            ]);

            $this->getResponse()->redirect->afterPost("/fixtures/import/{$this->import->getId()}");
        } else {
            // Nothing was attached, not much to do but stare at our navels

            $this->getResponse()->redirect->afterPost("/fixtures/upload");
        }
    }

    /**
     * Trim a string to remove whitespace and convert it all to lowercase
     * @param string $text1
     * @param string $text2
     * @return string
     */
    private function compareKey($text1, $text2)
    {
        return $this->makeKey($text1) == $this->makeKey($text2);
    }

    /**
     * Turn a text string into a usable key for comparison
     * @param string $text
     * @return string
     */
    private function makeKey($text)
    {
        return strtolower(trim($text));
    }

    /**
     * Convert a text date from CSV into Alaress_Date_Format
     * @param string $date Date in DD/MM/YY or DD/MM/YYYY
     * @param string $time Time in 24 hour format HH:MM:SS (defaults to midnight 00:00)
     * @return \Alaress_Date_Format|null
     * @throws \Exception
     */
    private function makeDateTime($date, $time = "00:00:00")
    {
        if (empty($date)) {
            return null;
        }

        try {
            $dateObject = new \Alaress_Date_Format("{$date} {$time}", ['d/m/y H:i:s', 'd/m/Y H:i:s']);
        } catch (\Exception $e) {
            throw new \Exception("Invalid date format, please ensure all dates are entered in DD/MM/YY format.");
        }

        return $dateObject;
    }

    /**
     * Iterate a CSV file looking for sports
     * @param \Schoolbox\Resource\Storage\Model $storageObject
     * @throws \Exception
     */
    private function loadSports($storageObject)
    {
        // Go through the CSV and extract all of the information into an array

        $reader = new Reader($this->filesystemProxy->readStream($storageObject));

        while ($reader->valid()) {
            $reader->next();
            $row = $reader->current();

            if ($row && $this->compareKey($row[0], Controller::TYPE_SPORT)) {
                $error = false;

                if (empty(trim($row[1]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 2, "SKIPPED SPORT: No sport name specified");
                    $error = true;
                }

                if (!empty($row[2])) {
                    $url = new \Url($row[2]);

                    if (!$url->isUrl()) {
                        $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 3, "SKIPPED SPORT: Invalid URL specified ({$row[2]})");
                        $error = true;
                    }
                }

                if (!$error) {
                    try {
                        $sport = $this->getSportMapper()->findOneByColumn('name', $row[1], $this->disableCache);
                        $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "UPDATING: Sport '{$row[1]}'");
                    } catch (MapperException $e) {
                        $sport = new Sport();
                        $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "INSERTING: Sport '{$row[1]}'");
                    }

                    if (!$error) {
                        $sport->setName($row[1]);
                        $sport->setUrl($row[2]);
                        $this->getSportMapper()->save($sport);
                    }
                }
            }
        }
    }

    /**
     * Iterate a CSV file looking for positions
     * @param \Schoolbox\Resource\Storage\Model $storageObject
     * @throws \Exception
     */
    private function loadPositions($storageObject)
    {
        // Go through the CSV and extract all of the information into an array

        $reader = new Reader($this->filesystemProxy->readStream($storageObject));

        while ($reader->valid()) {
            $reader->next();
            $row = $reader->current();

            if ($row && $this->makeKey($row[0]) == Controller::TYPE_POSITION) {
                $error = false;

                if (empty(trim($row[1]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 2, "SKIPPED POSITION: No sport name specified");
                    $error = true;
                }

                if (empty(trim($row[2]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 3, "SKIPPED POSITION: No position name specified");
                    $error = true;
                }

                if (!$error) {
                    try {
                        $sport = $this->getSportMapper()->findOneByColumn('name', $row[1], $this->disableCache);
                    } catch (MapperException $e) {
                        $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "INSERTING: Sport '{$row[1]}'");
                        $sport = new Sport();
                        $sport->setName($row[1]);
                        $this->getSportMapper()->save($sport);
                    }

                    try {
                        $position = $this->getPositionMapper()->findOneByColumnList([
                            'sport_id' => $sport->getId(),
                            'name' => $row[2]
                        ], $this->disableCache);
                        $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "UPDATING: Position '{$row[2]}'");
                    } catch (MapperException $e) {
                        $position = new Position();
                        $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "INSERTING: Position '{$row[2]}'");
                    }

                    $position->setName($row[2]);
                    $position->setSport($sport);
                    $this->getPositionMapper()->save($position);
                }
            }
        }
    }


    /**
     * Iterate a CSV file looking for venues
     * @param \Schoolbox\Resource\Storage\Model $storageObject
     * @throws \Exception
     */
    private function loadVenues($storageObject)
    {
        $reader = new Reader($this->filesystemProxy->readStream($storageObject));

        while ($reader->valid()) {
            $reader->next();
            $row = $reader->current();

            if ($row && $this->makeKey($row[0]) == Controller::TYPE_VENUE) {
                $error = false;

                if (empty(trim($row[1]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 2, "SKIPPED VENUE: No venue name specified");
                    $error = true;
                }

                if (!is_numeric($row[3]) && !empty(trim($row[3]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 4, "SKIPPED VENUE: Invalid latitude specified ({$row[3]})");
                    $error = true;
                }

                if (!is_numeric($row[4]) && !empty(trim($row[4]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 5, "SKIPPED VENUE: Invalid longitude specified ({$row[4]})");
                    $error = true;
                }

                // If no latitude/longitude specified attempt to reverse geocode

                if (empty($row[3]) && empty($row[4])) {
                    $location = $this->lookupAddress($row[2]);

                    if ($location != null) {
                        $row[3] = $location['latitude'];
                        $row[4] = $location['longitude'];
                    } else {
                        $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 4, "VENUE WARNING: Could not determine latitude/longitude from address");
                    }
                }

                if (!$error) {
                    try {
                        $venue = $this->getVenueMapper()->findOneByColumn('name', $row[1], $this->disableCache);
                        $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "UPDATING: Venue '{$row[1]}'");
                    } catch (MapperException $e) {
                        $venue = new Venue();
                        $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "INSERTING: Venue '{$row[1]}'");
                    }

                    $venue->setName($row[1]);
                    $venue->setAddress($row[2]);
                    $venue->setLatitude($row[3]);
                    $venue->setLongitude($row[4]);
                    $this->getVenueMapper()->save($venue);
                }
            }
        }
    }


    /**
     * Iterate a CSV file looking for schools
     * @param \Schoolbox\Resource\Storage\Model $storageObject
     * @throws \Exception
     */
    private function loadSchools($storageObject)
    {
        // Go through the CSV and extract all of the information into an array

        $reader = new Reader($this->filesystemProxy->readStream($storageObject));

        while ($reader->valid()) {
            $reader->next();
            $row = $reader->current();

            if ($row && $this->makeKey($row[0]) == Controller::TYPE_SCHOOL) {
                $error = false;

                if (empty(trim($row[1]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 2, "SKIPPED SCHOOL: No school name specified");
                    $error = true;
                }

                if (!$error) {
                    try {
                        $this->getSchoolMapper()->findOneByColumn('name', $row[1], $this->disableCache);
                        $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "SKIPPED SCHOOL: School '{$row[1]}' already exists");
                    } catch (MapperException $e) {
                        $school = new School();
                        $school->setName($row[1]);
                        $this->getSchoolMapper()->save($school);

                        $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "INSERTING: School '{$row[1]}'");
                    }
                }
            }
        }
    }


    /**
     * Iterate a CSV file looking for match statuses
     * @param \Schoolbox\Resource\Storage\Model $storageObject
     * @throws \Exception
     */
    private function loadStatuses($storageObject)
    {
        // Go through the CSV and extract all of the information into an array

        $reader = new Reader($this->filesystemProxy->readStream($storageObject));

        while ($reader->valid()) {
            $reader->next();
            $row = $reader->current();

            if ($row && $this->makeKey($row[0]) == Controller::TYPE_STATUS) {
                $error = false;

                if (empty(trim($row[1]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 2, "SKIPPED STATUS: No status name specified");
                    $error = true;
                }

                if (!$error) {
                    try {
                        $status = $this->getStatusMapper()->findOneByColumn('name', $row[1], $this->disableCache);
                        $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "SKIPPED STATUS: Status '{$row[1]}' already exists");
                    } catch (MapperException $e) {
                        $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "INSERTING: Status '{$row[1]}'");
                        $status = new Status();
                        $status->setName($row[1]);
                        $this->getStatusMapper()->save($status);
                    }
                }
            }
        }
    }


    /**
     * Iterate a CSV file looking for seasons
     * @param \Schoolbox\Resource\Storage\Model $storageObject
     * @throws \Exception
     */
    private function loadSeasons($storageObject)
    {
        // Go through the CSV and extract all of the information into an array

        $reader = new Reader($this->filesystemProxy->readStream($storageObject));

        while ($reader->valid()) {
            $reader->next();
            $row = $reader->current();

            if ($row && $this->makeKey($row[0]) == Controller::TYPE_SEASON) {
                $error = false;

                if (empty(trim($row[1]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 2, "SKIPPED SEASON: No season name specified");
                    $error = true;
                }

                if (empty(trim($row[2]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 3, "SKIPPED SEASON: No start date specified");
                    $error = true;

                    // Kludge to stop storm complaining about not being set
                    $startDate = null;
                } else {
                    try {
                        $startDate = $this->makeDateTime($row[2]);
                    } catch (\Exception $e) {
                        $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 3, "SKIPPED SEASON: Invalid start date specified ({$row[2]})");
                        $error = true;

                        // Kludge to stop storm complaining about not being set
                        $startDate = null;
                    }
                }

                if (empty(trim($row[3]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 4, "SKIPPED SEASON: No end date specified");
                    $error = true;

                    // Kludge to stop storm complaining about not being set
                    $endDate = null;
                } else {
                    try {
                        $endDate = $this->makeDateTime($row[3], '23:59:59');
                    } catch (\Exception $e) {
                        $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 4, "SKIPPED SEASON: Invalid end date specified ({$row[3]})");
                        $error = true;

                        // Kludge to stop storm complaining about not being set
                        $endDate = null;
                    }
                }

                if (!$error) {
                    try {
                        $season = $this->getSeasonMapper()->findOneByColumn('name', $row[1], $this->disableCache);
                        $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "UPDATING: Season '{$row[1]}'");
                    } catch (MapperException $e) {
                        $season = new Season();
                        $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "INSERTING: Season '{$row[1]}'");
                    }

                    $season->setName($row[1]);
                    $season->setStart($startDate);
                    $season->setEnd($endDate);

                    $this->getSeasonMapper()->save($season);
                }
            }
        }
    }


    /**
     * Iterate a CSV file looking for teams
     * @param \Schoolbox\Resource\Storage\Model $storageObject
     * @throws \Exception
     */
    private function loadTeams($storageObject)
    {
        // Go through the CSV and extract all of the information into an array

        $reader = new Reader($this->filesystemProxy->readStream($storageObject));
        $reader->setLoadFromFirst(true);

        while ($reader->valid()) {
            $reader->next();
            $row = $reader->current();

            if ($row && $this->makeKey($row[0]) == Controller::TYPE_TEAM) {
                $error = false;
                $season = null;

                if (empty(trim($row[4]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 5, "SKIPPED TEAM: No team name specified");
                    $error = true;
                }

                if (!empty($row[5])) {
                    $url = new \Url($row[5]);

                    if (!$url->isUrl()) {
                        $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 6, "SKIPPED TEAM: Invalid URL specified ({$row[5]})");
                        $error = true;
                    }
                }

                if (empty(trim($row[1]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 2, "SKIPPED TEAM: No season name specified");
                    $error = true;
                } else {
                    try {
                        /** @var Season $season */
                        $season = $this->getSeasonMapper()->findOneByColumn('name', $row[1], $this->disableCache);
                    } catch (MapperException $e) {
                        $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 2, "SKIPPED TEAM: Season not found in database ({$row[1]})");
                        $error = true;
                    }
                }

                if (empty(trim($row[2]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 3, "SKIPPED TEAM: No sport name specified");
                    $error = true;
                }

                if (empty(trim($row[3]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 4, "SKIPPED TEAM: No division name specified");
                    $error = true;
                }

                if (!$error) {
                    // Find the sport associated with the division, and create if doesn't exist

                    try {
                        $sport = $this->getSportMapper()->findOneByColumn('name', $row[2], $this->disableCache);
                    } catch (MapperException $e) {
                        $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "INSERTING: Sport '{$row[2]}'");
                        $sport = new Sport();
                        $sport->setName($row[2]);
                        $this->getSportMapper()->save($sport);
                    }

                    // Find the division associated with the division, and create if doesn't exist

                    $division = $this->spawnDivision($row[3], $sport, $season, $storageObject, $reader);

                    // Create/update the team

                    try {
                        $team = $this->getTeamMapper()->findOneByColumnList([
                            'name' => $row[4],
                            'division_id' => $division->getId()
                        ], $this->disableCache);
                        $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "UPDATING: Team '{$row[1]}'");
                    } catch (MapperException $e) {
                        $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "INSERTING: Team '{$row[4]}'");
                        $team = new Team();
                    }

                    $team->setName($row[4]);
                    $team->setUrl($row[5]);
                    $team->setDivision($division);
                    $this->getTeamMapper()->save($team);


                    // Add new players to team, this will not delete any existing players so as not to screw over existing
                    // match participants/results

                    $players = [];

                    for ($i = 6; $i < count($row); $i++) {
                        if (is_numeric($row[$i])) {
                            $players[] = $row[$i];
                        }
                    }

                    foreach ($players as $player) {
                        try {
                            $user = $this->getUserMapper()->findByExternalId($player);

                            try {
                                $this->getDivisionUserMapper()->findOneByColumnList([
                                    'division_id' => $division->getId(),
                                    'user_id' => $user->getId()
                                ], $this->disableCache);
                                $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 0, "SKIPPED DIVISION PLAYER: Already in division ({$player})");
                            } catch (MapperException $e) {
                                $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "INSERTING: Player '{$player}' to division '{$division->getName()}'");

                                $divisionPlayer = new DivisionUser();
                                $divisionPlayer->setDivision($division);
                                $divisionPlayer->setUser($user);
                                $divisionPlayer->setSequence(0);
                                $this->getDivisionUserMapper()->save($divisionPlayer);
                            }

                            try {
                                $this->getTeamPlayerMapper()->findOneByColumnList([
                                    'team_id' => $team->getId(),
                                    'user_id' => $user->getId()
                                ], $this->disableCache);
                                $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 0, "SKIPPED TEAM PLAYER: Already in team ({$player})");
                            } catch (MapperException $e) {
                                $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "INSERTING: Player '{$player}' to team '{$team->getName()}'");

                                $teamPlayer = new TeamPlayer();
                                $teamPlayer->setTeam($team);
                                $teamPlayer->setSequence(0);
                                $teamPlayer->setUser($user);
                                $this->getTeamPlayerMapper()->save($teamPlayer);
                            }
                        } catch (MapperException $e) {
                            $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 0, "SKIPPED TEAM PLAYER: Unknown ID ({$player})");
                        }
                    }
                }
            }
        }
    }


    /**
     * Iterate a CSV file looking for coaches
     * @param \Schoolbox\Resource\Storage\Model $storageObject
     * @throws \Exception
     */
    private function loadCoaches($storageObject)
    {
        $reader = new Reader($this->filesystemProxy->readStream($storageObject));
        $reader->setLoadFromFirst(true);

        while ($reader->valid()) {
            $reader->next();
            $row = $reader->current();

            if ($row && $this->makeKey($row[0]) == Controller::TYPE_COACH) {
                $error = false;
                $season = null;

                if (empty(trim($row[1]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 2, "SKIPPED COACH: No season name specified");
                    $error = true;
                } else {
                    try {
                        /** @var Season $season */
                        $season = $this->getSeasonMapper()->findOneByColumn('name', $row[1], $this->disableCache);
                    } catch (MapperException $e) {
                        $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 2, "SKIPPED COACH: Season not found in database ({$row[1]})");
                        $error = true;
                    }
                }
                if (empty(trim($row[2]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 3, "SKIPPED COACH: No sport name specified");
                    $error = true;
                }

                if (empty(trim($row[3]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 4, "SKIPPED COACH: No division name specified");
                    $error = true;
                }

                if (empty(trim($row[4]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 5, "SKIPPED COACH: No team name specified");
                    $error = true;
                }

                if (!$error) {
                    // Find the sport associated with the division, and create if doesn't exist

                    try {
                        $sport = $this->getSportMapper()->findOneByColumn('name', $row[2], $this->disableCache);
                    } catch (MapperException $e) {
                        $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "INSERTING: Sport '{$row[2]}'");
                        $sport = new Sport();
                        $sport->setName($row[2]);
                        $this->getSportMapper()->save($sport);
                    }

                    // Find the division associated with the division, and create if doesn't exist

                    $division = $this->spawnDivision($row[3], $sport, $season, $storageObject, $reader);

                    try {
                        $team = $this->getTeamMapper()->findOneByColumnList([
                            'name' => $row[4],
                            'division_id' => $division->getId()
                        ], $this->disableCache);
                    } catch (MapperException $e) {
                        $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "INSERTING: Team '{$row[4]}'");
                        $team = new Team();
                        $team->setName($row[4]);
                        $team->setDivision($division);
                        $this->getTeamMapper()->save($team);
                    }

                    $coaches = [];

                    for ($i = 4; $i < count($row); $i++) {
                        if (is_numeric($row[$i])) {
                            $coaches[] = $row[$i];
                        }
                    }

                    foreach ($coaches as $coach) {
                        try {
                            $this->getCoachMapper()->findOneByColumnList([
                                'user_id' => $coach
                            ], $this->disableCache);
                        } catch (MapperException $e) {
                            try {
                                $user = $this->getUserMapper()->findByExternalId($coach);

                                try {
                                    $coach = $this->getCoachMapper()->findOneByColumn('user_id', $user->getId(), $this->disableCache);
                                } catch (MapperException $e) {
                                    $coach = new Coach();
                                    $coach->setUser($user);
                                    $coach->setName($user->getFullName());
                                    $this->getCoachMapper()->save($coach);
                                }

                                try {
                                    $teamCoach = $this->getTeamCoachMapper()->findOneByColumnList([
                                       'team_id' => $team->getId(),
                                       'coach_id' => $coach->getId()
                                    ], $this->disableCache);
                                    $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "SKIPPING: Coach '{$user->getExternalId()}'");
                                } catch (MapperException $e) {
                                    $teamCoach = new TeamCoach();
                                    $teamCoach->setTeam($team);
                                    $teamCoach->setCoach($coach);
                                    $this->getTeamCoachMapper()->save($teamCoach);
                                    $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "INSERTING: Coach '{$user->getExternalId()}'");
                                }
                            } catch (MapperException $e) {
                                $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 0, "SKIPPED COACH: Unknown ID ({$coach})");
                            }
                        }
                    }
                }
            }
        }
    }


    /**
     * Iterate a CSV file looking for match fixtures
     * @param \Schoolbox\Resource\Storage\Model $storageObject
     * @throws \Exception
     */
    private function loadFixtures($storageObject)
    {
        $reader = new Reader($this->filesystemProxy->readStream($storageObject));
        $reader->setLoadFromFirst(true);

        while ($reader->valid()) {
            $reader->next();
            $row = $reader->current();

            if ($row && strtolower(trim($row[0])) == Controller::TYPE_FIXTURE) {
                $error = false;
                $season = null;

                if (empty(trim($row[1]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 2, "SKIPPED FIXTURE: No season name specified");
                    $error = true;
                } else {
                    try {
                        /** @var Season $season */
                        $season = $this->getSeasonMapper()->findOneByColumn('name', $row[1], $this->disableCache);
                    } catch (MapperException $e) {
                        $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 2, "SKIPPED FIXTURE: Season not found in database ({$row[1]})");
                        $error = true;
                    }
                }

                if (empty(trim($row[2]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 3, "SKIPPED FIXTURE: No sport name specified");
                    $error = true;
                }

                if (empty(trim($row[3]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 4, "SKIPPED FIXTURE: No division name specified");
                    $error = true;
                }

                if (empty(trim($row[4]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 5, "SKIPPED FIXTURE: No match name specified");
                    $error = true;
                }

                if (empty(trim($row[7]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 6, "SKIPPED FIXTURE: No venue name specified");
                    $error = true;
                }

                if (empty(trim($row[8]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 7, "SKIPPED FIXTURE: No home team name specified");
                    $error = true;
                }

                if (empty(trim($row[9]))) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 8, "SKIPPED FIXTURE: No away team name specified");
                    $error = true;
                }
                try {
                    if ($this->makeDateTime($row[5], $row[6]) == null) {
                        $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 6, "SKIPPED FIXTURE: No date/time specified");
                        $error = true;
                    }
                } catch (\Exception $e) {
                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 6, "SKIPPED FIXTURE: Invalid date/time specified ({$row[5]} {$row[6]})");
                    $error = true;
                }

                if (!$error) {
                    // Find the sport associated with the division, and create if doesn't exist

                    try {
                        $sport = $this->getSportMapper()->findOneByColumn('name', $row[2], $this->disableCache);
                    } catch (MapperException $e) {
                        $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "INSERTING: Sport '{$row[2]}'");
                        $sport = new Sport();
                        $sport->setName($row[2]);
                        $this->getSportMapper()->save($sport);
                    }

                    // Find the division associated with the division, and create if doesn't exist

                    $division = $this->spawnDivision($row[3], $sport, $season, $storageObject, $reader);

                    // Find the venue associated with the match

                    try {
                        $venue = $this->getVenueMapper()->findOneByColumnList([
                            'name' => $row[7]
                        ], $this->disableCache);
                    } catch (MapperException $e) {
                        $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "INSERTING: Venue '{$row[7]}'");
                        $venue = new Venue();
                        $venue->setName($row[7]);
                        $this->getVenueMapper()->save($venue);
                    }

                    // Find the teams associated with the match

                    try {
                        $teamHome = $this->getTeamMapper()->findOneByColumnList([
                            'name' => $row[8],
                            'division_id' => $division->getId()
                        ], $this->disableCache);
                    } catch (MapperException $e) {
                        /** @var Team $teamHome */
                        $teamHome = null;
                    }

                    try {
                        $teamAway = $this->getTeamMapper()->findOneByColumnList([
                            'name' => $row[9],
                            'division_id' => $division->getId()
                        ], $this->disableCache);
                    } catch (MapperException $e) {
                        /** @var Team $teamAway */
                        $teamAway = null;
                    }

                    // Find the schools associated with the match

                    try {
                        $schoolHome = $this->getSchoolMapper()->findOneByColumnList([
                            'name' => $row[8]
                        ], $this->disableCache);
                    } catch (MapperException $e) {
                        /** @var School $schoolHome */
                        $schoolHome = null;
                    }

                    try {
                        $schoolAway = $this->getSchoolMapper()->findOneByColumnList([
                            'name' => $row[9]
                        ], $this->disableCache);
                    } catch (MapperException $e) {
                        /** @var School $schoolAway */
                        $schoolAway = null;
                    }

                    // Make sure we have a valid home combination

                    if ($teamHome != null && $schoolHome != null) {
                        // A team and school were both found matching the away team name, need user intervention to deduplicate
                        $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 9, "SKIPPED FIXTURE: Home team name matches both school and team");
                        $error = true;
                    } else {
                        if ($teamHome == null && $schoolHome == null) {
                            // No home team/school specified
                            $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 9, "SKIPPED FIXTURE: Could not find a matching home team or school");
                            $error = true;
                        }
                    }

                    // Make sure we have a valid away combination

                    if ($teamAway != null && $schoolAway != null) {
                        // A team and school were both found matching the away team name, need user intervention to deduplicate
                        $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 9, "SKIPPED FIXTURE: Away team name matches both school and team");
                        $error = true;
                    } else {
                        if ($teamAway == null && $schoolAway == null) {
                            // No away team/school specified
                            $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), 9, "SKIPPED FIXTURE: Could not find a matching away team or school");
                            $error = true;
                        }
                    }

                    if (!$error) {
                        try {
                            $matchup = $this->getMatchupMapper()->findOneByColumnList([
                                'division_id' => $division->getId(),
                                'season_id' => $season->getId(),
                                'venue_id' => $venue->getId(),
                                'home_team_id' => $teamHome == null ? null : $teamHome->getId(),
                                'away_team_id' => $teamAway == null ? null : $teamAway->getId(),
                                'home_school_id' => $schoolHome == null ? null : $schoolHome->getId(),
                                'away_school_id' => $schoolAway == null ? null : $schoolAway->getId(),
                                'start_at' => $this->makeDateTime($row[5], $row[6])
                            ], $this->disableCache);
                            $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "UPDATING: Match '{$division->getName()} {$sport->getName()} {$venue->getName()}'");
                        } catch (MapperException $e) {
                            $matchup = new Matchup();
                            $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "INSERTING: Match '{$division->getName()} {$sport->getName()} {$venue->getName()}'");
                        }

                        $matchup->setDivision($division);
                        $matchup->setSeason($season);
                        $matchup->setVenue($venue);
                        $matchup->setName($row[4]);
                        $matchup->setStartAt($this->makeDateTime($row[5], $row[6]));
                        $matchup->setNotes($row[10]);

                        if ($teamHome != null) {
                            $matchup->setHomeTeam($teamHome);
                        }
                        if ($teamAway != null) {
                            $matchup->setAwayTeam($teamAway);
                        }
                        if ($schoolHome != null) {
                            $matchup->setHomeSchool($schoolHome);
                        }
                        if ($schoolAway != null) {
                            $matchup->setAwaySchool($schoolAway);
                        }

                        $this->getMatchupMapper()->save($matchup);

                        // Grab itinerary items for the match and save them

                        $items = [];

                        /* So that 11 and 3 aren't so mystical
                         */
                        $itineraryItemsStart = 11;
                        $colsPerItineraryItem = 3;

                        for ($i = $itineraryItemsStart; $i < count($row); $i += $colsPerItineraryItem) {
                            /* Be optimistic, presuming everything was done by the book
                             */
                            $itineraryError = false;

                            /* Screw arithmetic, go algebra
                             */
                            $itineraryDateIndex = $i;
                            $itineraryDate = trim($row[$itineraryDateIndex]);

                            $itineraryTimeIndex = $i + 1;
                            $itineraryTime = trim($row[$itineraryTimeIndex]);

                            $itineraryDescIndex = $i + 2;
                            $itineraryDesc = trim($row[$itineraryDescIndex]);

                            /* Only try to spawn itinerary items if all three data exist
                             * Report errors for each missing data
                             */
                            if (!strlen($itineraryDate)) {
                                $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), $itineraryDateIndex, "SKIPPED ITINERARY ITEM: No date specified");
                                $itineraryError = true;
                            }

                            if (!strlen($itineraryTime)) {
                                $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), $itineraryTimeIndex, "SKIPPED ITINERARY ITEM: No time specified");
                                $itineraryError = true;
                            }

                            if (!strlen($itineraryDesc)) {
                                $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), $itineraryDescIndex, "SKIPPED ITINERARY ITEM: No description specified");
                                $itineraryError = true;
                            }

                            /* Only proceed if no errors were encountered for this itinerary item
                             */
                            if (!$itineraryError) {
                                try {
                                    $datetime = $this->makeDateTime($itineraryDate, $itineraryTime);

                                    /* Stack up if you could get the date to process
                                     */
                                    $items[] = [
                                        'datetime' => $datetime,
                                        'description' => $itineraryDesc
                                    ];
                                } catch (\Exception $e) {
                                    $this->updateLog(Controller::RESULT_ERROR, $storageObject, $reader->key(), $itineraryDateIndex, "SKIPPED ITINERARY ITEM: Invalid date/time specified ({$itineraryDate} {$itineraryTime})");
                                }
                            }
                        }

                        /* Save all the things
                         * First, empty all the itinerary items then resave them
                         */
                        $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "PURGING: Removing all itinerary items for match '{$matchup->getDisplayName()}'");
                        $this->getItineraryItemMapper()->deleteByColumn('match_id', $matchup->getId());

                        foreach ($items as $item) {
                            if (!empty(trim($item['description']))) {
                                $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "INSERTING: Itinerary Item '{$item['description']}'");

                                $itineraryItem = new ItineraryItem();
                                $itineraryItem->setMatchup($matchup);
                                $itineraryItem->setStartAt($item['datetime']);
                                $itineraryItem->setDescription($item['description']);

                                $this->getItineraryItemMapper()->save($itineraryItem);
                            }
                        }
                    }
                }
            }
        }
    }


    /**
     * Decide whether to use an existing Division or spawn a new one
     *
     * @param string $divisionName
     * @param Sport $sport
     * @param Season $season
     * @param \Schoolbox\Resource\Storage\Model $storageObject
     * @param Reader $reader
     * @return Division
     * @throws MapperException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function spawnDivision($divisionName, Sport $sport, Season $season, $storageObject, $reader)
    {
        /* Look for a division in the season specified
         * If it exists, use it as-is
         */
        try {
            $division = $this->getDivisionMapper()->findOneByColumnList([
                'name' => $divisionName,
                'sport_id' => $sport->getId(),
                'season_id' => $season->getId()
            ], $this->disableCache);
        } catch (MapperException $e) {
            /* If the division doesn't exist in that particular season
             * Find out if it exists at all in this sport - if it does,
             * update its season and then use it
             */
            try {
                $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "UPDATING: Division '{$divisionName}' is now in Season {$season->getName()}");

                $division = $this->getDivisionMapper()->findOneByColumnList([
                    'name'     => $divisionName,
                    'sport_id' => $sport->getId(),
                ], $this->disableCache);

                $division->setSeason($season);
                $this->getDivisionMapper()->save($division);
            } catch (MapperException $e) {
                /* Otherwise the division was never in this sport
                 * nor in this season anywhere - spawn a new division
                 */
                $this->updateLog(Controller::RESULT_SUCCESS, $storageObject, $reader->key(), 1, "INSERTING: Division '{$divisionName}'");

                $division = new Division();
                $division->setName($divisionName);
                $division->setSport($sport);
                $division->setSeason($season);
                $this->getDivisionMapper()->save($division);
            }
        }

        return $division;
    }

    /* Getters/Setters
     */

    /**
     * @return SportMapper
     */
    protected function getSportMapper()
    {
        return $this->sportMapper;
    }

    /**
     * @param SportMapper $sportMapper
     * @return static
     */
    protected function setSportMapper($sportMapper)
    {
        $this->sportMapper = $sportMapper;
        return $this;
    }

    /**
     * @return CoachMapper
     */
    protected function getCoachMapper()
    {
        return $this->coachMapper;
    }

    /**
     * @param CoachMapper $coachMapper
     * @return static
     */
    protected function setCoachMapper($coachMapper)
    {
        $this->coachMapper = $coachMapper;
        return $this;
    }

    /**
     * @return SeasonMapper
     */
    protected function getSeasonMapper()
    {
        return $this->seasonMapper;
    }

    /**
     * @param SeasonMapper $seasonMapper
     * @return static
     */
    protected function setSeasonMapper($seasonMapper)
    {
        $this->seasonMapper = $seasonMapper;
        return $this;
    }

    /**
     * @return DivisionMapper
     */
    protected function getDivisionMapper()
    {
        return $this->divisionMapper;
    }

    /**
     * @param DivisionMapper $divisionMapper
     * @return static
     */
    protected function setDivisionMapper($divisionMapper)
    {
        $this->divisionMapper = $divisionMapper;
        return $this;
    }

    /**
     * @return DivisionUserMapper
     */
    protected function getDivisionUserMapper()
    {
        return $this->divisionUserMapper;
    }

    /**
     * @param DivisionUserMapper $divisionUserMapper
     * @return static
     */
    protected function setDivisionUserMapper($divisionUserMapper)
    {
        $this->divisionUserMapper = $divisionUserMapper;
        return $this;
    }

    /**
     * @return TeamMapper
     */
    public function getTeamMapper()
    {
        return $this->teamMapper;
    }

    /**
     * @param  TeamMapper $teamMapper
     * @return static
     */
    public function setTeamMapper(TeamMapper $teamMapper)
    {
        $this->teamMapper = $teamMapper;
        return $this;
    }

    /**
     * @return UserMapper
     */
    public function getUserMapper()
    {
        return $this->userMapper;
    }

    /**
     * @param  UserMapper $userMapper
     * @return static
     */
    public function setUserMapper(UserMapper $userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }

    /**
     * @return MatchupMapper
     */
    public function getMatchupMapper()
    {
        return $this->matchupMapper;
    }

    /**
     * @param MatchupMapper $matchupMapper
     * @return static
     */
    public function setMatchupMapper($matchupMapper)
    {
        $this->matchupMapper = $matchupMapper;
        return $this;
    }

    /**
     * @return ItineraryItemMapper
     */
    public function getItineraryItemMapper()
    {
        return $this->itineraryItemMapper;
    }

    /**
     * @param ItineraryItemMapper $itineraryItemMapper
     * @return static
     */
    public function setItineraryItemMapper($itineraryItemMapper)
    {
        $this->itineraryItemMapper = $itineraryItemMapper;
        return $this;
    }

    /**
     * @return VenueMapper
     */
    public function getVenueMapper()
    {
        return $this->venueMapper;
    }

    /**
     * @param VenueMapper $venueMapper
     * @return static
     */
    public function setVenueMapper($venueMapper)
    {
        $this->venueMapper = $venueMapper;
        return $this;
    }

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->errorMapper;
    }

    /**
     * @param ErrorMapper $errorMapper
     * @return static
     */
    protected function setErrorMapper($errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
    }

    /**
     * @return SchoolMapper
     */
    protected function getSchoolMapper()
    {
        return $this->schoolMapper;
    }

    /**
     * @param SchoolMapper $schoolMapper
     * @return static
     */
    protected function setSchoolMapper($schoolMapper)
    {
        $this->schoolMapper = $schoolMapper;
        return $this;
    }

    /**
     * @return PositionMapper
     */
    protected function getPositionMapper()
    {
        return $this->positionMapper;
    }

    /**
     * @param PositionMapper $positionMapper
     * @return static
     */
    protected function setPositionMapper($positionMapper)
    {
        $this->positionMapper = $positionMapper;
        return $this;
    }

    /**
     * @return StatusMapper
     */
    protected function getStatusMapper()
    {
        return $this->statusMapper;
    }

    /**
     * @param StatusMapper $statusMapper
     * @return static
     */
    protected function setStatusMapper($statusMapper)
    {
        $this->statusMapper = $statusMapper;
        return $this;
    }

    /**
     * @return ImportMapper
     */
    protected function getImportMapper()
    {
        return $this->importMapper;
    }

    /**
     * @param ImportMapper $importMapper
     * @return static
     */
    protected function setImportMapper($importMapper)
    {
        $this->importMapper = $importMapper;
        return $this;
    }

    /**
     * @return ImportErrorsMapper
     */
    protected function getImportErrorsMapper()
    {
        return $this->importErrorsMapper;
    }

    /**
     * @param ImportErrorsMapper $importErrorsMapper
     * @return static
     */
    protected function setImportErrorsMapper($importErrorsMapper)
    {
        $this->importErrorsMapper = $importErrorsMapper;
        return $this;
    }

    /**
     * @return TeamPlayerMapper
     */
    protected function getTeamPlayerMapper()
    {
        return $this->teamPlayerMapper;
    }

    /**
     * @param TeamPlayerMapper $teamPlayerMapper
     * @return static
     */
    protected function setTeamPlayerMapper($teamPlayerMapper)
    {
        $this->teamPlayerMapper = $teamPlayerMapper;
        return $this;
    }

    /**
     * @return TeamCoachMapper
     */
    protected function getTeamCoachMapper()
    {
        return $this->teamCoachMapper;
    }

    /**
     * @param TeamCoachMapper $teamCoachMapper
     * @return static
     */
    protected function setTeamCoachMapper($teamCoachMapper)
    {
        $this->teamCoachMapper = $teamCoachMapper;
        return $this;
    }
}
