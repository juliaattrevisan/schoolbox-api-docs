<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
 */

namespace Schoolbox\SportsFixtures\Import;

use Alaress\Web\Controller\Base;
use Alaress\Csv\Reader;
use Schoolbox\Collection;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\SportsFixtures\Sport\Model as Sport;
use Schoolbox\SportsFixtures\Sport\Mapper as SportMapper;
use Schoolbox\SportsFixtures\Season\Model as Season;
use Schoolbox\SportsFixtures\Season\Mapper as SeasonMapper;
use Schoolbox\SportsFixtures\Division\Model as Division;
use Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;
use Schoolbox\SportsFixtures\Team\Model as Team;
use Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;
use Schoolbox\SportsFixtures\Match\Model as Match;
use Schoolbox\SportsFixtures\Match\Mapper as MatchMapper;
use Schoolbox\SportsFixtures\ItineraryItem\Model as ItineraryItem;
use Schoolbox\SportsFixtures\ItineraryItem\Mapper as ItineraryItemMapper;
use Schoolbox\SportsFixtures\Venue\Model as Venue;
use Schoolbox\SportsFixtures\Venue\Mapper as VenueMapper;
use Schoolbox\SportsFixtures\School\Model as School;
use Schoolbox\SportsFixtures\School\Mapper as SchoolMapper;
use Schoolbox\SportsFixtures\Position\Model as Position;
use Schoolbox\SportsFixtures\Position\Mapper as PositionMapper;
use Schoolbox\SportsFixtures\Status\Model as Status;
use Schoolbox\SportsFixtures\Status\Mapper as StatusMapper;
use Schoolbox\SportsFixtures\Coach\Model as Coach;
use Schoolbox\SportsFixtures\Coach\Mapper as CoachMapper;
use Schoolbox\SportsFixtures\Team\Player\Model as TeamPlayer;
use Schoolbox\SportsFixtures\Team\Player\Mapper as TeamPlayerMapper;
use Schoolbox\SportsFixtures\Division\User\Model as DivisionUser;
use Schoolbox\SportsFixtures\Division\User\Mapper as DivisionUserMapper;
use Schoolbox\SportsFixtures\Import\Mapper as ImportMapper;
use Schoolbox\SportsFixtures\Import\Model as Import;
use Schoolbox\UserMapper;

class Controller extends Base
{
    // These are the keys used in the first column of the spreadsheet to denote entity type

    const TYPE_SPORT = 'sport';
    const TYPE_POSITION = 'position';
    const TYPE_VENUE = 'venue';
    const TYPE_SCHOOL = 'school';
    const TYPE_STATUS = 'status';
    const TYPE_SEASON = 'season';
    const TYPE_DIVISION = 'division';
    const TYPE_TEAM = 'team';
    const TYPE_COACH = 'coach';
    const TYPE_FIXTURE = 'fixture';

    const STATUS_RUNNING = 'started';
    const STATUS_FAILED = 'failed';
    const STATUS_COMPLETED = 'completed';

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        $this->setUserMapper(UserMapper::getInstance());
        $this->setMatchMapper(MatchMapper::getInstance());
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
        $this->setImportMapper(ImportMapper::getInstance());
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
            ->setMimeFilter('text/csv')
            ->setReadOnly(false)
            ->setMulti(true);

        if ($attachments != null) {
            foreach ($attachments as $attachment) {
                $zone->attachFiles(array($attachment->getHash()));
            }
        }

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
     */
    public function getImportProgress($id)
    {
        try {
            $import = $this->getImportMapper()->findById($id);

            $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesImport/importProgress.twig', [
                'import' => $import
            ]));
        } catch (MapperException $e) {

            // Requested import didn't exist, just go to the upload page

            $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesImport/upload.twig', [
                'attachzone' => $this->getAttachZone()
            ]));
        }
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

            $log = new Import();
            $log->setOwnerId($this->getUser()->getId());
            $log->setStatus(Controller::STATUS_RUNNING);
            $log->setResult('Import in progress');
            $log->setCreatedAt(\Alaress_Date_Format::now());
            $log->setUpdatedAt(\Alaress_Date_Format::now());
            $this->getImportMapper()->save($log);

            $this->getResponse()->content->set([
                'template' => '@sportsfixturesImport/importing.twig',
                'data'     => array(
                    'id' => $log->getId()
                ),
                'postOutput' => [

                    function() use ($post, $log) {

                        // Give the script a chance to finish

                        set_time_limit(60 * 15);

                        // Get all uploaded files

                        $attachmentObjects = (new CollectorFactory())->build($post['attachment'])->getAll();

                        // Create blank arrays to hold everything

                        $sports = [];
                        $positions = [];
                        $divisions = [];
                        $venues = [];
                        $schools = [];
                        $statuses = [];
                        $seasons = [];
                        $teams = [];
                        $fixtures = [];
                        $coaches = [];

                        // Begin the import looking for entities that aren't dependent on others

                        try {
                            /** @var \Schoolbox\Resource\Storage\Model $storageObject */
                            foreach ($attachmentObjects as $storageObject) {
                                $sports = array_merge($sports, $this->loadSports($storageObject));
                                $seasons = array_merge($seasons, $this->loadSeasons($storageObject));
                                $venues = array_merge($venues, $this->loadVenues($storageObject));
                                $schools = array_merge($schools, $this->loadSchools($storageObject));
                                $statuses = array_merge($statuses, $this->loadStatuses($storageObject));
                            }

                            // Now we can load positions and division

                            /** @var \Schoolbox\Resource\Storage\Model $storageObject */
                            foreach ($attachmentObjects as $storageObject) {
                                $positions = array_merge($positions, $this->loadPositions($storageObject, $sports));
                                $divisions = array_merge($divisions, $this->loadDivisions($storageObject, $sports, $seasons));
                            }

                            // Now we can load teams

                            /** @var \Schoolbox\Resource\Storage\Model $storageObject */
                            foreach ($attachmentObjects as $storageObject) {
                                $teams = array_merge($teams, $this->loadTeams($storageObject, $divisions, $sports, $seasons));
                            }

                            // Now we can load coaches

                            /** @var \Schoolbox\Resource\Storage\Model $storageObject */
                            foreach ($attachmentObjects as $storageObject) {
                                $coaches = array_merge($coaches, $this->loadCoaches($storageObject, $teams, $seasons, $sports, $divisions));
                                $fixtures = array_merge($fixtures, $this->loadFixtures($storageObject, $divisions, $seasons, $venues, $teams, $schools));
                            }

                            // Success, update the status

                            $log->setResult("{$fixtures['matchCount']} matches imported successfully");
                            $log->setStatus(Controller::STATUS_COMPLETED);
                            $log->setUpdatedAt(\Alaress_Date_Format::now());
                            $this->getImportMapper()->save($log);
                        }
                        catch (\Exception $e) {

                            // Failed, record the error message

                            $log->setResult("CSV Import failed. " . $e->getMessage());
                            $log->setStatus(Controller::STATUS_FAILED);
                            $log->setUpdatedAt(\Alaress_Date_Format::now());
                            $this->getImportMapper()->save($log);
                        }

                        // All done!
                    }
                ],
            ]);

        } else {

            // Nothing was attached, not much to do but stare at our navels

            $this->getResponse()->redirect->afterPost("/sportsfixtures/upload");
        }
    }

    /**
     * Trim a string to remove whitespace and convert it all to lowercase
     * @param $text1
     * @param $text2
     * @return string
     */
    private function compareKey($text1, $text2)
    {
        return $this->makeKey($text1) == $this->makeKey($text2);
    }

    /**
     * Turn a text string into a usable key for comparison
     * @param $text
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
     * @return \Alaress_Date_Format
     */
    private function makeDateTime($date, $time = "00:00:00")
    {
        if (empty($date)) {
            return null;
        }

        try {
            $dateObject = new \Alaress_Date_Format();
            $dateObject->setFromFormat("{$date} {$time}", ['d/m/y H:i:s', 'd/m/Y H:i:s']);
        } catch (\Exception $e) {
            throw new \Exception("Invalid date format, please ensure all dates are entered in DD/MM/YY format.");
        }

        return $dateObject;
    }

    /**
     * Iterate a CSV file looking for sports
     * @param \Schoolbox\Resource\Storage\Model $storageObject
     * @return array
     */
    private function loadSports($storageObject)
    {
        // Go through the CSV and extract all of the information into an array

        $sports = [];

        $reader = new Reader($storageObject->readStream());

        while ($reader->valid()) {
            $reader->next();
            $row = $reader->current();

            $sport_key = $this->makeKey($row[1]);

            if ($this->compareKey($row[0], Controller::TYPE_SPORT)) {

                if (empty(trim($row[1]))) {
                    throw new \Exception('Sport names cannot be blank');
                }

                if (!empty($row[2])) {
                    $url = new \Url($row[2]);

                    if (!$url->isUrl()) {
                        throw new \Exception("Invalid URL ({$row[2]}) specified on sport ({$row[1]})");
                    }
                }

                $sports[$sport_key] = [
                    'name' => $row[1],
                    'url' => $row[2]
                ];
            }
        }

        // We've now got all of the data out of the csv, we need to link the entities to existing database records so we don't create
        // duplicates during the import process, and while we are at it we will update any existing entries we find in the database
        // with the data from the CSV import

        // Get all sports from the database

        $databaseSports = $this->getSportMapper()->findAll();

        // Iterate CSV records

        foreach ($sports as &$sport) {

            // Iterate all database records looking for a name match

            /** @var Sport $databaseSport */
            foreach ($databaseSports as $databaseSport) {

                // Look for an exact name match

                if ($this->compareKey($databaseSport->getName(), $sport['name'])) {

                    // We found a sport with the same name in the database, grab its ID

                    $sport['id'] = $databaseSport->getId();

                    // Update database records URL with the details from the CSV

                    $databaseSport->setUrl($sport['url']);
                    $this->getSportMapper()->save($databaseSport);

                    break;
                }
            }

            // If the sport did not exist in the database

            if (!isset($sport['id'])) {

                // Sport did not exist in the database, we need to create a new record

                $databaseSport = new Sport();
                $databaseSport->setName($sport['name']);
                $databaseSport->setUrl($sport['url']);
                $this->getSportMapper()->save($databaseSport);

                // Grab the ID of the new record for later use

                $sport['id'] = $databaseSport->getId();

                // Add it to the collection so we don't create it again if the user was smart enough to add it to the CSV twice

                $databaseSports->add($databaseSport);
            }
        }

        // Add any database items that weren't already in the CSV to the array

        foreach ($databaseSports as $databaseSport) {

            $found = false;

            foreach ($sports as &$sport) {
                if ($sport['id'] == $databaseSport->getId()) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {

                // Item was not in the CSV, whack it into the array

                $sports_key = $databaseSport->getName();

                $sports[$sports_key] = [
                    'name' => $databaseSport->getName(),
                    'url' => $databaseSport->getUrl(),
                    'id' => $databaseSport->getId()
                ];
            }
        }

        // Return all of the possible sports

        return $sports;
    }

    /**
     * Iterate a CSV file looking for positions
     * @param \Schoolbox\Resource\Storage\Model $storageObject
     * @param array $sports Array of sports with their ID populated
     * @return array
     * @throws \Exception
     */
    private function loadPositions($storageObject, $sports)
    {
        // Go through the CSV and extract all of the information into an array

        $positions = [];

        $reader = new Reader($storageObject->readStream());

        while ($reader->valid()) {

            $reader->next();
            $row = $reader->current();

            if ($this->makeKey($row[0]) == Controller::TYPE_POSITION) {

                if (empty(trim($row[2]))) {
                    throw new \Exception('Sport names cannot be blank');
                }

                // Positions are sport specific so we need to load in the sport ID for later use

                $sport_id = null;

                foreach ($sports as $sport_key => $sport) {
                    if ($this->compareKey($sport_key, $row[1])) {
                        $sport_id = $sport['id'];
                    }
                }

                // We can only use the position if it has a valid sport

                if ($sport_id != null) {

                    $sport_key = $this->makeKey($row[1]);
                    $position_key = $this->makeKey($row[2]);

                    $positions[$sport_key][$position_key] = [
                        'sport_id' => $sport_id,
                        'sport_key' => $sport_key,
                        'name' => $row[2]
                    ];

                } else {

                    // The sport didn't exist in the database or CSV file- we have a problem with this one

                    throw new \Exception("Position ({$row[2]}) requires a sport that does not exist ({$row[1]})");
                }
            }
        }

        // We've now got all of the data out of the csv, we need to link the entities to existing database records so we don't create
        // duplicates during the import process

        $databasePositions = $this->getPositionMapper()->findAll();

        foreach ($positions as $sport_key => $sportPositions) {

            foreach ($sportPositions as &$position) {

                /** @var Position $databasePosition */
                foreach ($databasePositions as $databasePosition) {

                    // Look for an exact name match within the sport

                    if ($this->compareKey($databasePosition->getName(), $position['name']) &&          // Position names must match
                        $databasePosition->getSport()->getId() == $position['sport_id']
                    ) {
                        // Sport ID must match
                        // Position already existed in the database, grab its ID

                        $position['id'] = $databasePosition->getId();
                    }
                }

                // Check if we found a database record

                if (!isset($position['id'])) {

                    // Create a placeholder sport for saving

                    $sport = new Sport();
                    $sport->setId($position['sport_id']);

                    // Position did not exist in the database, we need to create a new record

                    $databasePosition = new Position();
                    $databasePosition->setName($position['name']);
                    $databasePosition->setSport($sport);
                    $this->getPositionMapper()->save($databasePosition);

                    // Grab the ID of the new record for later use

                    $position['id'] = $databasePosition->getId();

                    // Add it to the collection so we don't create it again

                    $databasePositions->add($databasePosition);
                }
            }
        }

        // Add in any database items that weren't already in the CSV

        foreach ($databasePositions as $databasePosition) {

            // Looks like the mapper automatically creates a blank position- we don't want to pull those in

            if (!empty($databasePosition->getName())) {

                $found = false;

                foreach ($positions as &$position) {
                    if ($position['id'] == $databasePosition->getId()) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {

                    // Item was not in the CSV, whack it into the array because we might need it later

                    // Need to load sport to get its name and ID

                    $sport = $this->getSportMapper()->findById($databasePosition->getSport()->getId());

                    $sport_key = $this->makeKey($sport->getName());
                    $position_key = $this->makeKey($databasePosition->getName());

                    $positions[$sport_key][$position_key] = [
                        'sport_id' => $sport->getId(),
                        'sport_key' => $sport_key,
                        'name' => $databasePosition->getName(),
                        'id' => $databasePosition->getId()
                    ];
                }
            }
        }

        return $positions;
    }

    /**
     * Iterate a CSV file looking for divisions
     * @param \Schoolbox\Resource\Storage\Model $storageObject
     * @param array $sports Array of sports with their ID populated
     * @param array $seasons Array of seasons with their ID populated
     * @return array
     * @throws \Exception
     */
    private function loadDivisions($storageObject, $sports, $seasons)
    {
        // Go through the CSV and extract all of the information into an array

        $reader = new Reader($storageObject->readStream());
        $reader->setLoadFromFirst(true);

        $divisions = [];

        while ($reader->valid()) {

            $reader->next();
            $row = $reader->current();

            if ($this->makeKey($row[0]) == Controller::TYPE_DIVISION) {

                if (empty(trim($row[3]))) {
                    throw new \Exception('Division names cannot be blank');
                }

                // Divisions can have any number of players added to it put their ID's into an array

                $players = [];

                for ($i = 5; $i < count($row); $i++) {
                    if (is_numeric($row[$i])) {
                        $players[] = $row[$i];
                    }
                }

                if (!empty($row[4])) {
                    $url = new \Url($row[4]);

                    if (!$url->isUrl()) {
                        throw new \Exception("Invalid URL ({$row[4]}) specified on division ({$row[3]})");
                    }
                }

                $season_key = $this->makeKey($row[1]);
                $sport_key = $this->makeKey($row[2]);
                $division_key = $this->makeKey($row[3]);

                $divisions[$season_key][$sport_key][$division_key] = [
                    'name' => $row[3],
                    'url' => $row[4],
                    'players' => $players
                ];
            }
        }

        // We've now got all of the data out of the csv, we need to link the entities to existing database records so we don't create
        // duplicates during the import process

        $databaseDivisions = $this->getDivisionMapper()->findAll();

        foreach ($divisions as $season_key => &$divisionsSeasons) {

            foreach ($divisionsSeasons as $sport_key => &$divisionsCurrent) {

                foreach ($divisionsCurrent as $division_key => &$division) {

                    // For a division to exist it needs to have a valid season and sport

                    if (isset($sports[$sport_key]) && isset($seasons[$season_key])) {

                        /** @var Position $databasePosition */
                        foreach ($databaseDivisions as $databaseDivision) {

                            if ($this->compareKey($databaseDivision->getName(), $division_key) &&                  // Names must match
                                $databaseDivision->getSport()->getId() == $sports[$sport_key]['id'] &&          // Sports must match
                                $databaseDivision->getSeason()->getId() == $seasons[$season_key]['id']          // Seasons must match
                            ) {

                                // Position already existed in the database, grab its ID

                                $division['id'] = $databaseDivision->getId();
                            }
                        }

                        // Check if we found a database record

                        if (!isset($division['id'])) {

                            // Division did not exist in the database, we need to create a new record

                            // Create a placeholder sport

                            $sport = new Sport();
                            $sport->setId($sports[$sport_key]['id']);

                            // Create a placeholder season

                            $season = new Season();
                            $season->setId($seasons[$season_key]['id']);

                            $databaseDivision = new Division();
                            $databaseDivision->setName($division['name']);
                            $databaseDivision->setUrl($division['url']);
                            $databaseDivision->setSport($sport);
                            $databaseDivision->setSeason($season);
                            $this->getDivisionMapper()->save($databaseDivision);

                            // Grab the ID of the new record for later use

                            $division['id'] = $databaseDivision->getId();
                        }
                    } else {

                        if (!isset($sports[$sport_key])) {
                            throw new \Exception("Unknown sport ({$sport_key}) on division ({$division['name']})");
                        } else {
                            throw new \Exception("Unknown season ({$season_key}) on division ({$division['name']})");
                        }
                    }
                }
            }
        }

        // Load in divisions from the database that weren't defined in the CSV

        /** @var Division $databaseDivision */
        foreach ($databaseDivisions as $databaseDivision) {

            $found = false;

            $season_key = $this->makeKey($databaseDivision->getSeason()->getName());
            $sport_key = $this->makeKey($databaseDivision->getSport()->getName());
            $division_key = $this->makeKey($databaseDivision->getName());

            $divisionsCurrent = $divisions[$season_key][$sport_key];

            foreach ($divisionsCurrent as $division) {

                if ($division['id'] == $databaseDivision->getId()) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {

                // Get players for this division from the database

                $databasePlayers = $this->getDivisionUserMapper()->findByColumn('division_id', $databaseDivision->getId());

                $players = [];

                /** @var DivisionUser $playerCurrent */
                foreach ($databasePlayers as $playerCurrent) {
                    $players[] = $playerCurrent->getUser()->getExternalId();
                }

                $divisions[$season_key][$sport_key][$division_key] = [
                    'id' => $databaseDivision->getId(),
                    'name' => $databaseDivision->getName(),
                    'url' => $databaseDivision->getUrl(),
                    'players' => $players
                ];
            }
        }

        return $divisions;
    }

    /**
     * Iterate a CSV file looking for venues
     * @param \Schoolbox\Resource\Storage\Model $storageObject
     * @return array
     */
    private function loadVenues($storageObject)
    {
        $reader = new Reader($storageObject->readStream());

        $venues = [];

        while ($reader->valid()) {
            $reader->next();
            $row = $reader->current();

            if ($this->makeKey($row[0]) == Controller::TYPE_VENUE) {

                if (empty(trim($row[1]))) {
                    throw new \Exception('Venue names cannot be blank');
                }

                $venue_key = $this->makeKey($row[1]);

                $venues[$venue_key] = [
                    'name' => $row[1],
                    'address' => $row[2],
                    'latitude' => $row[3],
                    'longitude' => $row[4]
                ];
            }
        }

        // We've now got all of the data out of the csv, we need to link the entities to existing database records so we don't create
        // duplicates during the import process, and while we are at it we will update any existing entries we find in the database
        // with the data from the CSV import

        $databaseVenues = $this->getVenueMapper()->findAll();

        foreach ($venues as &$venue) {

            if ((!empty($venue['latitude']) && !is_numeric($venue['latitude'])) || (!empty($venue['longitude']) && !is_numeric($venue['longitude']))) {
                throw new \Exception("Venue ({$venue['name']}) latitude and longitude must both be numeric values");
            }

            /** @var Venue $databaseVenue */
            foreach ($databaseVenues as $databaseVenue) {

                // Look for an exact name match

                if ($this->compareKey($databaseVenue->getName(), $venue['name'])) {

                    // Venue already existed in the database, grab its ID

                    $venue['id'] = $databaseVenue->getId();

                    // Update URL with the details from the CSV

                    $databaseVenue->setName($venue['name']);
                    $databaseVenue->setAddress($venue['address']);

                    $databaseVenue->setLatitude($venue['latitude']);
                    $databaseVenue->setLongitude($venue['longitude']);
                    $this->getVenueMapper()->save($databaseVenue);
                }
            }

            // Check if we found a database record

            if (!isset($venue['id'])) {

                // Venue did not exist in the database, we need to create a new record

                $databaseVenue = new Venue();
                $databaseVenue->setName($venue['name']);
                $databaseVenue->setAddress($venue['address']);
                $databaseVenue->setLatitude($venue['latitude']);
                $databaseVenue->setLongitude($venue['longitude']);
                $this->getVenueMapper()->save($databaseVenue);

                // Grab the ID of the new record for later use

                $venue['id'] = $databaseVenue->getId();

                // Add it to the collection so we don't create it again

                $databaseVenues->add($databaseVenue);
            }
        }

        // Get details of venues from the database that didn't appear in the CSV

        foreach ($databaseVenues as $databaseVenue) {

            $found = false;

            foreach ($venues as &$venue) {
                if ($venue['id'] == $databaseVenue->getId()) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {

                $venue_key = $this->makeKey($databaseVenue->getName());

                $venues[$venue_key] = [
                    'name' => $databaseVenue->getName(),
                    'address' => $databaseVenue->getAddress(),
                    'latitude' => $databaseVenue->getLatitude(),
                    'longitude' => $databaseVenue->getLongitude(),
                    'id' => $databaseVenue->getId()
                ];
            }
        }

        return $venues;
    }

    /**
     * Iterate a CSV file looking for schools
     * @param \Schoolbox\Resource\Storage\Model $storageObject
     * @return array
     */
    private function loadSchools($storageObject)
    {
        // Go through the CSV and extract all of the information into an array

        $reader = new Reader($storageObject->readStream());

        $schools = [];

        while ($reader->valid()) {
            $reader->next();
            $row = $reader->current();

            $school_key = $this->makeKey($row[1]);

            if ($this->makeKey($row[0]) == Controller::TYPE_SCHOOL) {

                if (empty(trim($row[1]))) {
                    throw new \Exception("School names cannot be blank");
                }

                $schools[$school_key] = [
                    'name' => $row[1]
                ];
            }
        }

        // We've now got all of the data out of the csv, we need to link the entities to existing database records so we don't create
        // duplicates during the import process

        $databaseSchools = $this->getSchoolMapper()->findAll();

        foreach ($schools as &$school) {

            /** @var School $databaseSchool */
            foreach ($databaseSchools as $databaseSchool) {

                // Look for an exact name match

                if ($this->compareKey($databaseSchool->getName(), $school['name'])) {

                    // School already existed in the database, grab its ID

                    $school['id'] = $databaseSchool->getId();
                }
            }

            // Check if we found a database record

            if (!isset($school['id'])) {

                // School did not exist in the database, we need to create a new record

                $databaseSchool = new School();
                $databaseSchool->setName($school['name']);
                $this->getSchoolMapper()->save($databaseSchool);

                // Grab the ID of the new record for later use

                $school['id'] = $databaseSchool->getId();

                // Add it to the collection so we don't create it again

                $databaseSchools->add($databaseSchool);
            }
        }

        // Get details of schools from the database that didn't appear in the CSV

        /** @var School $databaseSchool */
        foreach ($databaseSchools as $databaseSchool) {

            $found = false;

            foreach ($schools as &$school) {
                if ($school['id'] == $databaseSchool->getId()) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {

                $school_key = $this->makeKey($databaseSchool->getName());

                $schools[$school_key] = [
                    'name' => $databaseSchool->getName(),
                    'id' => $databaseSchool->getId()
                ];
            }
        }

        return $schools;
    }

    /**
     * Iterate a CSV file looking for match statuses
     * @param \Schoolbox\Resource\Storage\Model $storageObject
     * @return array
     */
    private function loadStatuses($storageObject)
    {
        // Go through the CSV and extract all of the information into an array

        $reader = new Reader($storageObject->readStream());

        $statuses = [];

        while ($reader->valid()) {
            $reader->next();
            $row = $reader->current();

            if ($this->makeKey($row[0]) == Controller::TYPE_STATUS) {

                if (empty(trim($row[1]))) {
                    throw new \Exception("Match statuses cannot be blank");
                }

                $status_key = $this->makeKey($row[1]);

                $statuses[$status_key] = [
                    'name' => $row[1]
                ];
            }
        }

        // We've now got all of the data out of the csv, we need to link the entities to existing database records so we don't create
        // duplicates during the import process

        $databaseStatuses = $this->getStatusMapper()->findAll();

        foreach ($statuses as &$status) {

            /** @var Status $databaseStatus */
            foreach ($databaseStatuses as $databaseStatus) {

                // Look for an exact name match

                if ($this->compareKey($databaseStatus->getName(), $status['name'])) {

                    // Status already existed in the database, grab its ID

                    $status['id'] = $databaseStatus->getId();
                }
            }

            // Check if we found a database record

            if (!isset($status['id'])) {

                // Status did not exist in the database, we need to create a new record

                $databaseStatus = new Status();
                $databaseStatus->setName($status['name']);
                $this->getStatusMapper()->save($databaseStatus);

                // Grab the ID of the new record for later use

                $status['id'] = $databaseStatus->getId();

                // Add it to the collection so we don't create it again

                $databaseStatuses->add($databaseStatus);
            }
        }

        // Get details of statuses from the database that didn't appear in the CSV

        /** @var Status $databaseStatus */
        foreach ($databaseStatuses as $databaseStatus) {

            $found = false;

            foreach ($statuses as &$status) {
                if ($status['id'] == $databaseStatus->getId()) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {

                $status_key = $this->makeKey($databaseStatus->getName());

                $statuses[$status_key] = [
                    'name' => $databaseStatus->getName(),
                    'id' => $databaseStatus->getId()
                ];
            }

        }

        return $statuses;
    }

    /**
     * Iterate a CSV file looking for seasons
     * @param \Schoolbox\Resource\Storage\Model $storageObject
     * @return array
     */
    private function loadSeasons($storageObject)
    {
        // Go through the CSV and extract all of the information into an array

        $seasons = [];

        $reader = new Reader($storageObject->readStream());

        while ($reader->valid()) {
            $reader->next();
            $row = $reader->current();

            $season_key = $this->makeKey($row[1]);

            if ($this->makeKey($row[0]) == Controller::TYPE_SEASON) {

                if (empty(trim($row[1]))) {
                    throw new \Exception("Season names cannot be blank");
                }

                $seasons[$season_key] = [
                    'name' => $row[1],
                    'start' => $row[2],
                    'end' => $row[3]
                ];
            }
        }

        // We've now got all of the data out of the csv, we need to link the entities to existing database records so we don't create
        // duplicates during the import process, and while we are at it we will update any existing entries we find in the database
        // with the data from the CSV import

        // Get all seasons from the database

        $databaseSeasons = $this->getSeasonMapper()->findAll();

        foreach ($seasons as &$season) {

            // We need the date/time in a format usable by the model

            $dateStart = $this->makeDateTime($season['start']);
            $dateEnd = $this->makeDateTime($season['end'], '23:59:59');

            // Look for an exact name match

            /** @var Season $databaseSeason */
            foreach ($databaseSeasons as $databaseSeason) {

                if ($this->compareKey($databaseSeason->getName(), $season['name'])) {

                    // Season already existed in the database, grab its ID

                    $season['id'] = $databaseSeason->getId();

                    // Update season with the details from the CSV

                    if ($dateStart != null && $dateEnd != null) {
                        $databaseSeason->setStart($dateStart);
                        $databaseSeason->setEnd($dateEnd);
                        $this->getSeasonMapper()->save($databaseSeason);
                    } else {
                        throw new \Exception("Seasons must have a start and end date specified");
                    }
                }
            }

            // Check if we found a database record

            if (!isset($season['id'])) {

                // Season did not exist in the database, we need to create a new record

                $databaseSeason = new Season();
                $databaseSeason->setName($season['name']);
                $databaseSeason->setStart($dateStart);
                $databaseSeason->setEnd($dateEnd);
                $this->getSeasonMapper()->save($databaseSeason);

                // Grab the ID of the new record for later use

                $season['id'] = $databaseSeason->getId();

                // Add it to the collection so we don't create it again

                $databaseSeasons->add($databaseSeason);
            }
        }

        // Get details of seasons from the database that didn't appear in the CSV

        /** @var Season $databaseSeason */
        foreach ($databaseSeasons as $databaseSeason) {

            $found = false;

            foreach ($seasons as &$season) {
                if ($season['id'] == $databaseSeason->getId()) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $seasons[strtolower(trim($databaseSeason->getName()))] = [
                    'name' => $databaseSeason->getName(),
                    'id' => $databaseSeason->getId(),

                    // I don't think we need these at any point during the rest of the import process, but they're in there for completion

                    'start' => $databaseSeason->getStart()->getLocalShort(),
                    'end' => $databaseSeason->getEnd()->getLocalShort()
                ];
            }

        }

        return $seasons;
    }

    /**
     * Iterate a CSV file looking for teams
     * @param \Schoolbox\Resource\Storage\Model $storageObject
     * @param array $divisions Array of divisions with their ID populated
     * @param array $sports Array of sports with their ID populated
     * @param array $seasons Array of seasons with their ID populated
     * @return array
     * @throws \Exception
     */
    private function loadTeams($storageObject, $divisions, $sports, $seasons)
    {
        // Go through the CSV and extract all of the information into an array

        $reader = new Reader($storageObject->readStream());
        $reader->setLoadFromFirst(true);

        $teams = [];

        while ($reader->valid()) {

            $reader->next();
            $row = $reader->current();

            if ($this->makeKey($row[0]) == Controller::TYPE_TEAM) {

                if (empty(trim($row[4]))) {
                    throw new \Exception("Team names cannot be blank");
                }

                // Get the player ID's from the end of the row

                $players = [];

                for ($i = 6; $i < count($row); $i++) {
                    if (is_numeric($row[$i])) {
                        $players[] = $row[$i];
                    }
                }

                $season_key = $this->makeKey($row[1]);
                $sport_key = $this->makeKey($row[2]);
                $division_key = $this->makeKey($row[3]);
                $team_key = $this->makeKey($row[4]);

                if (!empty($row[5])) {
                    $url = new \Url($row[5]);

                    if (!$url->isUrl()) {
                        throw new \Exception("Invalid URL ({$row[5]}) specified on team ({$row[4]})");
                    }
                }

                $teams[$season_key][$sport_key][$division_key][$team_key] = [
                    'name' => $row[4],
                    'url' => $row[5],
                    'players' => $players,

                    // This is a placeholder for the coaches we will populate below (these are located on a different row in the CSV)

                    'coaches' => []
                ];

                // Load in division ID to make things easier later

                if (!isset($sports[$sport_key])) {
                    throw new \Exception("Unknown sport ({$sport_key}) when importing team ({$team_key}");
                }

                if (!isset($seasons[$season_key])) {
                    throw new \Exception("Unknown season ({$season_key}) when importing team ({$team_key}");
                }

                if (!isset($divisions[$season_key][$sport_key][$division_key])) {
                    throw new \Exception("Unknown division ({$division_key}) when importing team ({$team_key}");
                }

                $teams[$season_key][$sport_key][$division_key][$team_key]['division_id'] = $divisions[$season_key][$sport_key][$division_key]['id'];
            }
        }

        // Load coaches into these teams, these are on other rows in the CSV

        $reader = new Reader($storageObject->readStream());
        $reader->setLoadFromFirst(true);

        while ($reader->valid()) {

            $reader->next();
            $row = $reader->current();

            if ($this->makeKey($row[0]) == Controller::TYPE_COACH) {

                $season_key = $this->makeKey($row[1]);
                $sport_key = $this->makeKey($row[2]);
                $division_key = $this->makeKey($row[3]);
                $team_key = $this->makeKey($row[4]);

                for ($i = 4; $i < count($row); $i++) {
                    if (!empty($row[$i])) {
                        $teams[$season_key][$sport_key][$division_key][$team_key]['coaches'][] = $row[$i];
                    }
                }
            }
        }

        // We've now got all of the data out of the csv, we need to link the entities to existing database records so we don't create
        // duplicates during the import process

        $databaseTeams = $this->getTeamMapper()->findAll();

        foreach ($teams as $season_key => &$teamsSeasons) {
            foreach ($teamsSeasons as $sport_key => &$teamsSports) {
                foreach ($teamsSports as $division_key => &$teamsDivisions) {
                    foreach ($teamsDivisions as $team_key => &$team) {

                        // Make sure the division exists

                        if (isset($divisions[$season_key][$sport_key][$division_key])) {

                            /** @var Team $databaseTeam */
                            foreach ($databaseTeams as $databaseTeam) {

                                // Look for a match based on sport ID and exact name match of team

                                if ($this->compareKey($databaseTeam->getName(), $team['name']) &&
                                    $databaseTeam->getDivision()->getId() == $divisions[$season_key][$sport_key][$division_key]['id']
                                ) {
                                    // Team already existed in the database, grab its ID

                                    $team['id'] = $databaseTeam->getId();
                                    $team['object'] = $databaseTeam;

                                    $division = new Division();
                                    $division->setId($divisions[$season_key][$sport_key][$division_key]["id"]);

                                    // Update its details while we are there

                                    $databaseTeam->setDivision($division);
                                    $databaseTeam->setUrl($team['url']);
                                    $this->getTeamMapper()->save($databaseTeam);
                                }
                            }

                            // Check if we found a database record

                            if (!isset($team['id'])) {

                                // Team did not exist in the database, we need to create a new record

                                // Create a placeholder division

                                $division = new Division();
                                $division->setId($divisions[$season_key][$sport_key][$division_key]["id"]);

                                $databaseTeam = new Team();
                                $databaseTeam->setName($team['name']);
                                $databaseTeam->setDivision($division);
                                $databaseTeam->setUrl($team['url']);
                                $this->getTeamMapper()->save($databaseTeam);

                                // Grab the ID of the new record for later use

                                $team['id'] = $databaseTeam->getId();

                                // We need to store any new players in the division and team as well

                                if (is_array($team['players'])) {
                                    foreach ($team['players'] as $player) {

                                        $user = $this->getUserMapper()->findByExternalId($player);

                                        // Load player into division

                                        $divisionUser = new DivisionUser();
                                        $divisionUser->setUser($user);
                                        $divisionUser->setDivision($division);
                                        $divisionUser->setSequence(0);
                                        $this->getDivisionUserMapper()->save($divisionUser);

                                        // Load player into team

                                        $teamPlayer = new TeamPlayer();
                                        $teamPlayer->setTeam($databaseTeam);
                                        $teamPlayer->setUser($user);
                                        $teamPlayer->setSequence(0);
                                        $this->getTeamPlayerMapper()->save($teamPlayer);
                                    }
                                }

                            } else {

                                // We need to add any players in the division and team that didn't already exist

                                if (is_array($team['players'])) {
                                    foreach ($team['players'] as $player) {

                                        $user = $this->getUserMapper()->findByExternalId($player);

                                        /** @var Team $teamObject */
                                        $teamObject = $team['object'];

                                        try {

                                            // Try to find existing player/division combination

                                            $this->getDivisionUserMapper()->findOneByColumnList([
                                                'user_id' => $user->getId(),
                                                'division_id' => $teamObject->getDivision()->getId()
                                            ]);

                                        } catch (\Exception $e) {

                                            // Load player into division if it couldn't be found

                                            $divisionUser = new DivisionUser();
                                            $divisionUser->setUser($user);
                                            $divisionUser->setDivision($teamObject->getDivision());
                                            $divisionUser->setSequence(0);
                                            $this->getDivisionUserMapper()->save($divisionUser);
                                        }

                                        try {

                                            // Try to find existing player/team combination

                                            $this->getTeamPlayerMapper()->findOneByColumnList([
                                                'user_id' => $user->getId(),
                                                'team_id' => $team['id']
                                            ]);

                                        } catch (\Exception $e) {

                                            // Load player into team if it couldn't be found

                                            $teamPlayer = new TeamPlayer();
                                            $teamPlayer->setTeam($databaseTeam);
                                            $teamPlayer->setUser($user);
                                            $teamPlayer->setSequence(0);
                                            $this->getTeamPlayerMapper()->save($teamPlayer);
                                        }
                                    }
                                }
                            }

                        } else {

                            // The division didn't exist- "Houston... we've got a problem"
                            throw new \Exception("Unknown division ({$division_key}) when creating team ({$team_key})");
                        }
                    }
                }
            }
        }

        // Get details of teams from the database that didn't appear in the CSV

        /** @var Team $databaseTeam */
        foreach ($databaseTeams as $databaseTeam) {

            $found = false;

            $division = $this->getDivisionMapper()->findById($databaseTeam->getDivision()->getId());

            $season_key = $this->makeKey($division->getSeason()->getName());
            $sport_key = $this->makeKey($division->getSport()->getName());
            $division_key = $this->makeKey($division->getName());

            if (is_array($teams[$season_key][$sport_key][$division_key])) {
                foreach ($teams[$season_key][$sport_key][$division_key] as &$team) {
                    if ($team['id'] == $databaseTeam->getId()) {
                        $found = true;
                        break;
                    }
                }
            }

            if (!$found) {
                /** @var Division $division */
                $division = $this->getDivisionMapper()->findById($databaseTeam->getDivision()->getId());

                $season_key = $this->makeKey($division->getSeason()->getName());
                $division_key = $this->makeKey($division->getName());
                $sport_key = $this->makeKey($division->getSport()->getName());
                $team_key = $this->makeKey($databaseTeam->getName());

                $teams[$season_key][$sport_key][$division_key][$team_key] = [
                    'name' => $databaseTeam->getName(),
                    'url' => $databaseTeam->getUrl(),
                    'id' => $databaseTeam->getId(),
                    'object' => $databaseTeam,
                    'division_id' => $division->getId(),

                    // Not going to need these going forward so not going to bother grabbing from the DB

                    'players' => [],
                    'coaches' => []
                ];
            }
        }

        return $teams;
    }

    /**
     * Iterate a CSV file looking for coaches
     * @param \Schoolbox\Resource\Storage\Model $storageObject
     * @param array $teams Array of teams with their ID populated
     * @param array $seasons Array of seasons with their ID populated
     * @param array $sports Array of sports with their ID populated
     * @param array $divisions Array of divisions with their ID populated
     * @return array
     * @throws \Exception
     */
    private function loadCoaches($storageObject, $teams, $seasons, $sports, $divisions)
    {
        $reader = new Reader($storageObject->readStream());
        $reader->setLoadFromFirst(true);

        $coaches = [];

        while ($reader->valid()) {
            $reader->next();
            $row = $reader->current();

            if ($this->makeKey($row[0]) == Controller::TYPE_COACH) {

                $coachesArray = [];

                for ($i = 4; $i < count($row); $i++) {
                    if (is_numeric($row[$i])) {
                        $coachesArray[] = $row[$i];
                    }
                }

                $season_key = $this->makeKey($row[1]);
                $sport_key = $this->makeKey($row[2]);
                $division_key = $this->makeKey($row[3]);
                $team_key = $this->makeKey($row[4]);

                if (!isset($sports[$sport_key])) {
                    throw new \Exception("Unknown sport ({$sport_key}) when importing team ({$team_key}");
                }

                if (!isset($seasons[$season_key])) {
                    throw new \Exception("Unknown season ({$season_key}) when importing coach");
                }

                if (!isset($divisions[$season_key][$sport_key][$division_key])) {
                    throw new \Exception("Unknown division ({$division_key}) when importing coach");
                }

                if (!isset($teams[$season_key][$sport_key][$division_key][$team_key])) {
                    throw new \Exception("Unknown team ({$team_key}) when importing coach");
                }

                $coaches[$season_key][$sport_key][$division_key][$team_key] = [
                    'coaches' => $coachesArray,
                    'team_id' => $teams[$season_key][$sport_key][$division_key][$team_key]['id']
                ];
            }
        }


        // We've now got all of the data out of the csv, we need to link the entities to existing database records so we don't create
        // duplicates during the import process

        $databaseCoaches = $this->getCoachMapper()->findAll();
        $databaseTeams = $this->getTeamMapper()->findAll();

        foreach ($coaches as $season_key => &$sportsSeasons) {
            foreach ($sportsSeasons as $sport_key => &$sportsTeams) {
                foreach ($sportsTeams as $division_key => &$coachesTeam) {
                    foreach ($coachesTeam as $team_key => &$team) {

                        $coachCollection = new Collection();

                        foreach ($team["coaches"] as $coach) {

                            try {

                                // First we have to make sure all of the coaches have records in the coach table

                                $user = $this->getUserMapper()->findByExternalId($coach);

                                $found = false;

                                /** @var Coach $coachCurrent */
                                foreach ($databaseCoaches as $coachCurrent) {

                                    $coachCurrent->setUser($this->getUserMapper()->findById($coachCurrent->getUser()->getId()));

                                    if ($coachCurrent->getUser()->getExternalId() == $coach) {
                                        $coachCollection->add($coachCurrent);
                                        $found = true;
                                        break;
                                    }
                                }

                                if (!$found) {

                                    $coachCurrent = new Coach();
                                    $coachCurrent->setUser($user);
                                    $coachCurrent->setName($user->getFullName());
                                    $this->getCoachMapper()->save($coachCurrent);

                                    $coachCollection->add($coachCurrent);
                                }

                            } catch (\Exception $e) {

                                // Couldn't find the coach by their ID, gotta fail

                                throw new \Exception("Unable to locate coach by ID ({$coach})");
                            }
                        }

                        // Save the coaches onto the team

                        /** @var Team $databaseTeam */
                        foreach ($databaseTeams as $databaseTeam) {
                            if ($databaseTeam->getId() == $team["team_id"]) {
                                $databaseTeam->setCoaches($coachCollection);
                                $this->getTeamMapper()->save($databaseTeam);
                            }
                        }
                    }
                }
            }
        }

        return $coaches;
    }

    /**
     * Iterate a CSV file looking for match fixtures
     * @param \Schoolbox\Resource\Storage\Model $storageObject
     * @param array $divisions Array of divisions with their ID populated
     * @param array $seasons Array of seasons with their ID populated
     * @param array $venues Array of venues with their ID populated
     * @param array $teams Array of teams with their ID populated
     * @param array $schools Array of schools with their ID populated
     * @return array
     * @throws \Exception
     */
    private function loadFixtures($storageObject, $divisions, $seasons, $venues, $teams, $schools)
    {
        $count = 0;

        $reader = new Reader($storageObject->readStream());
        $reader->setLoadFromFirst(true);

        $fixtures = [];

        while ($reader->valid()) {
            $reader->next();
            $row = $reader->current();

            if (strtolower(trim($row[0])) == Controller::TYPE_FIXTURE) {

                $itinerary = [];

                for ($i = 11; $i < count($row); $i += 3) {
                    if (!empty($row[$i])) {
                        $itinerary[] = [
                            'date' => $row[$i],
                            'time' => $row[$i + 1],
                            'description' => $row[$i + 2]
                        ];
                    }
                }

                $season_key = $this->makeKey($row[1]);
                $sport_key = $this->makeKey($row[2]);
                $division_key = $this->makeKey($row[3]);
                $venue_key = $this->makeKey($row[7]);
                $home_team_key = $this->makeKey($row[8]);
                $away_team_key = $this->makeKey($row[9]);

                $fixtures[$season_key][$sport_key][$division_key][] = [
                    'venue_key' => $venue_key,
                    'home_team_key' => $home_team_key,
                    'away_team_key' => $away_team_key,
                    'name' => $row[4],
                    'date' => $row[5],
                    'time' => $row[6],
                    'note' => $row[9],
                    'itinerary' => $itinerary
                ];
            }
        }

        // Delete any existing matches that have a combination of division, season and venue matching those found in the CSV- not sure
        // if this is the best logic...

        foreach ($fixtures as $season_key => &$fixturesSeasons) {
            foreach ($fixturesSeasons as $sport_key => &$fixturesSports) {
                foreach ($fixturesSports as $division_key => &$fixturesDivisions) {
                    foreach ($fixturesDivisions as &$fixture) {

                        // Make sure the dependencies exist

                        if (!isset($divisions[$season_key][$sport_key][$division_key])) {
                            throw new \Exception("Unable to find division when creating match ({$division_key})");
                        }

                        if (!isset($seasons[$season_key])) {
                            throw new \Exception("Unable to find season when creating match ({$season_key})");
                        }

                        if (!isset($venues[$fixture['venue_key']])) {
                            throw new \Exception("Unable to find season when creating match ({$fixture['venue_key']})");
                        }

                        $division_id = $divisions[$season_key][$sport_key][$division_key]['id'];
                        $season_id = $seasons[$season_key]['id'];
                        $venue_id = $venues[$fixture['venue_key']]['id'];

                        $matches = $this->getMatchMapper()->deleteByColumnList([
                            'division_id' => $division_id,
                            'season_id' => $season_id,
                            'venue_id' => $venue_id
                        ]);
                    }
                }
            }
        }

        foreach ($fixtures as $season_key => &$fixturesSeasons) {
            foreach ($fixturesSeasons as $sport_key => &$fixturesSports) {
                foreach ($fixturesSports as $division_key => &$fixturesDivisions) {
                    foreach ($fixturesDivisions as &$fixture) {

                        // Make sure the dependencies exist

                        if (!isset($divisions[$season_key][$sport_key][$division_key])) {
                            throw new \Exception("Unable to find division when creating match ({$division_key})");
                        }

                        if (!isset($seasons[$season_key])) {
                            throw new \Exception("Unable to find season when creating match ({$season_key})");
                        }

                        if (!isset($venues[$fixture['venue_key']])) {
                            throw new \Exception("Unable to find season when creating match ({$fixture['venue_key']})");
                        }

                        $fixture['division_id'] = $divisions[$season_key][$sport_key][$division_key]['id'];
                        $fixture['season_id'] = $seasons[$season_key]['id'];
                        $fixture['venue_id'] = $venues[$fixture['venue_key']]['id'];
                        $fixture['start_at'] = $this->makeDateTime($fixture['date'], $fixture['time']);

                        if ($fixture['start_at'] == null) {
                            throw new \Exception("Fixtures must have a start date specified");
                        }

                        if (isset($teams[$season_key][$sport_key][$division_key][$fixture['home_team_key']])) {
                            $fixture['home_team_id'] = $teams[$season_key][$sport_key][$division_key][$fixture['home_team_key']]['id'];
                        } else {
                            if (isset($schools[$fixture['home_team_key']])) {
                                $fixture['home_school_id'] = $schools[$fixture['home_team_key']]['id'];
                            } else {
                                // The school/team didn't exist- "Houston... we've got a problem"
                                throw new \Exception("Unable to find home team when creating match ({$fixture['home_team_key']})");
                            }
                        }

                        if (isset($teams[$season_key][$sport_key][$division_key][$fixture['away_team_key']])) {
                            $fixture['away_team_id'] = $teams[$season_key][$sport_key][$division_key][$fixture['away_team_key']]['id'];
                        } else {
                            if (isset($schools[$fixture['away_team_key']])) {
                                $fixture['away_school_id'] = $schools[$fixture['away_team_key']]['id'];
                            } else {
                                // The school/team didn't exist- "Houston... we've got a problem"
                                throw new \Exception("Unable to find home team when creating match ({$fixture['away_team_key']})");
                            }
                        }

                        foreach ($fixture['itinerary'] as &$item) {
                            $item['start_at'] = $this->makeDateTime($item['date'], $item['time']);

                            if ($item['start_at'] == null) {
                                throw new \Exception("Itinerary items must have a date specified");
                            }
                        }

                        // Create the database record

                        $match = new Match();

                        $match->setName($fixture["name"]);
                        $match->setStartAt($fixture["start_at"]);
                        $match->setNotes($fixture["note"]);

                        $division = new Division();
                        $division->setId($fixture["division_id"]);
                        $match->setDivision($division);

                        $season = new Season();
                        $season->setId($fixture["season_id"]);
                        $match->setSeason($season);

                        $venue = new Venue();
                        $venue->setId($fixture["venue_id"]);
                        $match->setVenue($venue);

                        if (isset($fixture['home_team_id'])) {
                            $team = new Team();
                            $team->setId($fixture["home_team_id"]);
                            $match->setHomeTeam($team);
                        } else {
                            $school = new School();
                            $school->setId($fixture["home_school_id"]);
                            $match->setHomeSchool($school);
                        }

                        if (isset($fixture['away_team_id'])) {
                            $team = new Team();
                            $team->setId($fixture["away_team_id"]);
                            $match->setAwayTeam($team);
                        } else {
                            $school = new School();
                            $school->setId($fixture["away_school_id"]);
                            $match->setAwaySchool($school);
                        }

                        $this->getMatchMapper()->save($match);

                        $itineraryItems = new Collection();

                        foreach ($fixture['itinerary'] as $itemCurrent) {
                            $itineraryItem = new ItineraryItem();
                            $itineraryItem->setMatch($match);
                            $itineraryItem->setDescription($itemCurrent['description']);
                            $itineraryItem->setStartAt($itemCurrent['start_at']);
                            $this->getItineraryItemMapper()->save($itineraryItem);
                        }

                        $match->setItinerary($itineraryItems);
                        $this->getMatchMapper()->save($match);
                        $count++;
                    }
                }
            }
        }

        $fixtures['matchCount'] = $count;

        return $fixtures;
    }

    /* Getters/Setters
     */

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
     * @var MatchMapper
     */
    protected $matchMapper;

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
     * @var TeamPlayerMapper
     */
    protected $teamPlayerMapper;

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
     * @return MatchMapper
     */
    public function getMatchMapper()
    {
        return $this->matchMapper;
    }

    /**
     * @param MatchMapper $matchMapper
     * @return static
     */
    public function setMatchMapper($matchMapper)
    {
        $this->matchMapper = $matchMapper;
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
}
