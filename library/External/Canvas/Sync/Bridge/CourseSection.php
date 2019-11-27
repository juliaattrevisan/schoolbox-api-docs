<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace External\Canvas\Sync\Bridge;

use Alaress\Database;
use Alaress\Database\Connector\IConnector;
use Alaress\DataStructures\ObjectTree;
use PDO;
use Psr\Log\LoggerInterface;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\TreeBuilder;
use UnderflowException;
use UnexpectedValueException;
use function array_column;
use function array_keys;
use function count;
use function implode;
use function max;
use function preg_match;
use function preg_replace;


/**
 * Responsible for the logical link between a Canvas CourseSection.SisSourceId and a Schoolbox Folder.FolderCode
 *
 * This encapsulates the link between a logical 'Class' in both systems.
 *
 * The StAlloy scheme appears to be course_section.sis_source_id = CONCAT($year, 'C', $subjectCode, 'NOT')
 *
 * Note that we need to build a tree that contains the 'Calendar Year' folder ancestor of a class, because we need
 * both the class's folderCode, and ancestral year, to match against the SIS Source Id.
 *
 * @todo, snap the scheme into a stateless Bridge\CourseSection class, and the execution and logging into a utility class
 */
class CourseSection
{

    /** @var IConnector */
    private $canvasDb;

    /** @var IConnector */
    private $schoolboxDb;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var Course */
    private $courseBridge;

    private $folderTree;

    /** @var LoggerInterface */
    private $log;

    /**
     * Maps all SB FolderCodes to a Canvas CourseSections.SisSourceIds (if one is found)
     * The folderCode array key is the potentially re-mapped T3-T4 value, which does not equal the value in SB DB.
     * [
     *   2018 => [
     *     'J/abc123' => 'Y2018Cabc123NOT',
     *   ],
     *   2019 => [
     *     'J/abc123' => 'Y2019Cabc123NOT',
     *     'J/xxxxxx' => null,                      // No Canvas CourseSection.SisSourceId found for Year + FolderCode
     *   ],
     * ]
     *
     * @var array
     */
    private $yearAndFolderCodeToSisSourceIdMap = [];

    /**
     * Maps all SB FolderCodes to a SB folder so we can easily fetch the folder by either old folderCode or sisSourceId
     * The folderCode array key is the potentially re-mapped T3-T4 value, which does not equal the value in SB DB.
     * [
     *   2018 => [
     *     'J/abc123' => $folder1,
     *   ],
     *   2019 => [
     *     'J/abc123' => $folder2,
     *     'J/xxxxxx' => $folderX,
     *   ],
     * ]
     *
     * @var array
     */
    private $yearAndFolderCodeToFolderMap = [];

    /**
     * Maps Schoolbox folder ids to SisSourceId
     * [
     *   1 => 'J/abc123',
     *   2 => null,
     *   ...
     * ]
     *
     * @var string[]|null[]
     */
    private $folderIdToSisSourceIdMap = [];


    /**
     * Maps all Canvas CourseSections.SisSourceIds to a SB FolderCode (if a match is found)
     * The folderCode is the potentially re-mapped T3-T4 value, which does not equal the value in SB DB.
     * [
     *   'Y2018Cabc123NOT' => ['2018', 'J/abc123'],
     *   'Y2019Cabc123NOT' => ['2019', 'J/abc123'],
     *   'Y2020Cabc123NOT' => null,                   // No SB FolderCode found for Canvas CourseSection.SisSourceId
     * ]
     *
     * @var array
     */
    private $sisSourceIdToYearAndFolderCodeMap = [];


    /**
     * @param IConnector      $canvasDb
     * @param IConnector      $schoolboxDb
     * @param Folder\Mapper   $folderMapper
     * @param Course          $courseBridge
     * @param LoggerInterface $log
     */
    public function __construct(
        IConnector $canvasDb,
        IConnector $schoolboxDb,
        Folder\Mapper $folderMapper,
        Course $courseBridge,
        LoggerInterface $log
    ) {
        $this->canvasDb     = $canvasDb;
        $this->schoolboxDb  = $schoolboxDb;
        $this->folderMapper = $folderMapper;
        $this->courseBridge = $courseBridge;
        $this->log          = $log;
    }


    /**
     * Load data from databases and perform matching
     *
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnderflowException
     * @throws UnexpectedValueException
     */
    public function execute(): void
    {
        $this->populateAllCanvasSisSourceIds($this->populateAllSchoolboxFolderCodes());
    }


    /**
     * Returns a Folder for a given year and folder code
     *
     * @param string $year
     * @param string $folderCode
     * @return Folder\Model|null
     */
    public function getFolderByYearAndFolderCode(string $year, string $folderCode): ?Folder\Model
    {
        return $this->yearAndFolderCodeToFolderMap[$year][$folderCode] ?? null;
    }


    /**
     * Returns the Schoolbox Folder for specified Canvas CourseSection.SisSourceId
     *
     * @param string $courseSectionSisSourceId
     * @return Folder\Model|null
     */
    public function getFolderByBySisSourceId(string $courseSectionSisSourceId): ?Folder\Model
    {
        $yearAndFolderCode = $this->getYearAndFolderCodeBySisSourceId($courseSectionSisSourceId);

        if ($yearAndFolderCode !== null) {
            return $this->getFolderByYearAndFolderCode($yearAndFolderCode[0], $yearAndFolderCode[1]);
        }
        return null;
    }


    /**
     * Returns a Year+FolderCode for the specified CourseSection.SisSourceId
     *
     * @param string $courseSectionSisSourceId
     * @return string[]|null
     * @internal string return[0]
     * @internal string return[1]
     */
    public function getYearAndFolderCodeBySisSourceId(string $courseSectionSisSourceId): ?array
    {
        return $this->sisSourceIdToYearAndFolderCodeMap[$courseSectionSisSourceId] ?? null;
    }


    /**
     * Returns a CourseSection.SisSourceId for the specified Year+FolderCode
     *
     * @param string $year
     * @param string $folderCode
     * @return string|null
     */
    public function getSisSourceIdByYearAndFolderCode(string $year, string $folderCode): ?string
    {
        return $this->yearAndFolderCodeToSisSourceIdMap[$year][$folderCode] ?? null;
    }


    /**
     * Returns the Canvas CourseSection.SisSource id for specified folder id
     *
     * @param int $folderId
     * @return string|null
     */
    public function getSisSourceIdForFolderId(int $folderId): ?string
    {
        return $this->folderIdToSisSourceIdMap[$folderId] ?? null;
    }


    /**
     * Returns all Canvas CourseSection.SisSourceIds that can be mapped to a Schoolbox folder
     *
     * @return string[]
     */
    public function getMappedSisSourceIds(): array
    {
        $mappedSisSourceIds = [];
        foreach ($this->sisSourceIdToYearAndFolderCodeMap as $sisSourceId => $yearAndFolderCode) {
            if ($yearAndFolderCode !== null) {
                $mappedSisSourceIds[] = $sisSourceId;
            }
        }
        return $mappedSisSourceIds;
    }


    /**
     * Fetches all current and historical folder codes
     *
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnderflowException
     * @throws UnexpectedValueException
     * @return string[][] Remapped folderCodes, indexed by [$year][$originalFolderCode]
     */
    private function populateAllSchoolboxFolderCodes(): array
    {
        $hashFunction = static function (Folder\Model $folder) {
            return $folder->getId();
        };
        $treeBuilder  = new TreeBuilder($this->folderMapper);

        $this->log->notice(sprintf('Building folder tree from root %d', $this->getClassFolder()->getId()));
        $this->folderTree = $treeBuilder->buildTree($this->getClassFolder()->getId(), $hashFunction);

        // Get all folders that ARE or WERE classes
        $innerPb = $this->folderMapper->getPredicateBuilder()
                                      ->notNull('fc.folder_id')
                                      ->regex('f.name', '.+\[.+\]$');

        $outerPb = $this->folderMapper->getPredicateBuilder()
                                      ->isNull('f.deleted_at')
                                      ->any($innerPb);

        $whereClause = implode(' AND ', $outerPb->getWhere());

        $sql = "
            SELECT f.id 'f.id', f.name 'f.name', fc.code 'fc.code' 
            FROM folder f 
            LEFT JOIN folder_code fc ON f.id = fc.folder_id AND fc.deleted_at IS NULL
            WHERE {$whereClause}";

        $this->log->notice("Fetching Schoolbox folders that ARE or WERE classes");
        $statement = $this->schoolboxDb->executeQuery($sql, $outerPb->getParams());

        while ($row = $statement->fetch()) {
            $folderId   = (int)$row['f.id'];
            $folderName = $row['f.name'];

            /** @var Folder\Model $folder */
            $folder = $this->folderTree->getElementByHash($folderId);

            $folder->setCodes(new GenericCollection(Folder\Code\Model::class));

            if ($row['fc.code'] !== null) {
                $folderCode = $row['fc.code'];
                // Add folderCode model to folder so we can retrieve it later
                $folderCodeModel = (new Folder\Code\Model())->setFolder($folder)->setCode($folderCode);
                $folder->getCodes()->setOffset($folderCode, $folderCodeModel);
            } else {
                $folderCode = $this->parseRolledOverFolderCodeFromFolderName($folderName);
            }

            $year = $this->getFolderYearFromAncestors($folder, $this->folderTree);

            // Note that if year not found, it will still be inserted here with key ''
            $this->yearAndFolderCodeToSisSourceIdMap[$year][$folderCode] = null;
            $this->yearAndFolderCodeToFolderMap[$year][$folderCode]      = $folder;
        }

        return $this->reMapFolderCodesForRollOver();
    }


    /**
     * Optionally remaps some folder codes to account for T3 and T4 rollover.
     *
     * Returns any re-mappings that were applied
     *
     * We need to be able to map classes between Canvas:Schoolbox:Synergetic during the rollover period.
     * STALOY has two roll ever events:
     * 1. At the end of T3.
     *    Yr 11 students begin Yr12 classes.
     *    In Synergetic, these have FileYear=2019 and classCode~=11Bio1
     *    In Schoolbox, all Yr 11 classes are rolled over
     *    In Schoolbox, early-start class folders are created below the next calendar year, eg 2020 > 11 > 11Bio1
     * 2. At the end of T4
     *    Yr 11 folder (if it exists in SB) is renamed to '12'
     *    All folder codes of early start classes are incremented, eg 'S/11Bio1' => 'S/12Bio1'
     *    All non-early start class folders are created, eg 2020 > 11 > 11Bio1
     *
     * We need to be able to:
     * 1. Map the early start classes to a Canvas courses after the T3 event
     * 2. Continue to map classes after the T4 event
     *
     * To do so, we need to sometimes increment the year level in the folder code on the fly.
     * We will do so if the data indicates we are between the T3 and T4 events.
     * @return string[][]
     */
    private function reMapFolderCodesForRollOver(): array
    {
        // Examine latest data to see if we need to tweak the map indexes
        $reMappedFolderCodes = [];
        $years               = array_keys($this->yearAndFolderCodeToFolderMap);
        if (count($years)) {

            $latestYear = max($years);
            $this->log->notice("Examining $latestYear folder codes to determine if we are in T3-T4 period");
            $latestFolderCodes = $this->yearAndFolderCodeToFolderMap[$latestYear];
            $folderCodes       = array_keys($latestFolderCodes);

            $countYear11 = 0;
            foreach ($folderCodes as $folderCode) {
                if (preg_match("/\D?11\D+/", $folderCode, $matches)) {
                    $countYear11++;
                }
            }
            $countFolderCodes = count($folderCodes);
            $this->log->info("Count $latestYear folder codes: $countFolderCodes");
            $this->log->info("Count $latestYear Yr 11 folder codes: $countYear11");

            // We will assume we are in the T3-T4 period if we have more than 10 folderCodes, and > 90% of for year 11
            $reMap = $countFolderCodes > 10 && ($countYear11 / $countFolderCodes) > 0.9;

            $this->log->notice(($reMap ? '' : "NOT ") . "Remapping $latestYear Year 11 folder codes to Year 12");

            if ($reMap) {
                foreach ($folderCodes as $folderCode) {
                    $reMapped = preg_replace("/(\D?)11(\D+)/", '${1}12${2}', $folderCode, 1);
                    if ($reMapped !== $folderCode) {
                        $this->log->debug("Remapping '$folderCode' to '$reMapped'");
                        $reMappedFolderCodes[$latestYear][$folderCode] = $reMapped;
                        $sisSourceId                                   = $this->yearAndFolderCodeToSisSourceIdMap[$latestYear][$folderCode];
                        $folder                                        = $this->yearAndFolderCodeToFolderMap[$latestYear][$folderCode];
                        unset(
                            $this->yearAndFolderCodeToSisSourceIdMap[$latestYear][$folderCode],
                            $this->yearAndFolderCodeToFolderMap[$latestYear][$folderCode]
                        );
                        $this->yearAndFolderCodeToSisSourceIdMap[$latestYear][$reMapped] = $sisSourceId;
                        $this->yearAndFolderCodeToFolderMap[$latestYear][$reMapped]      = $folder;
                    } else {
                        $this->log->warning("NOT remapping '$folderCode'");
                    }
                }
            }
        }
        return $reMappedFolderCodes;
    }


    /**
     * Returns the root of the class folder tree
     *
     * @return Folder\Model
     * @throws Database\Exception
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     */
    private function getClassFolder(): Folder\Model
    {
        $sql = "
            SELECT *
            FROM folder
            WHERE parent = 0 AND deleted_at IS NULL AND name = 'Classes'
        ";

        $rows = $this->schoolboxDb->executeQuery($sql)->fetchAll();
        if(!count($rows)){
            throw new UnexpectedValueException("Unable to locate class root folder");
        }
        if(count($rows) > 1){
            throw new UnexpectedValueException("Ambiguous class root folders:" . implode(',', array_column($rows, 'id')));
        }
        return $this->folderMapper->hydrate($rows[0]);
    }


    /**
     * Returns a folder code that was rolled over and embedded in folder name
     *
     * @param string $folderName
     * @return string
     * @throws UnexpectedValueException
     */
    private function parseRolledOverFolderCodeFromFolderName(string $folderName): string
    {
        // search for square bracket suffix
        if (preg_match('/.+\[(.+)]$/', $folderName, $matches)) {
            return $matches[1];
        }
        throw new UnexpectedValueException("Unable to parse rolled-over folder code from folder name '$folderName'");
    }


    /**
     * Attempt to determine the specified folder's year by examining its ancestors in the folder tree
     *
     * @param Folder\Model $folder
     * @param ObjectTree   $folderTree
     * @return string|null
     */
    private function getFolderYearFromAncestors(Folder\Model $folder, ObjectTree $folderTree): ?string
    {
        /** @var Folder\Model|null $parent */
        $parent = $folderTree->getParent($folder);
        while ($parent) {
            if (preg_match('/(\d{4})/', $parent->getName(), $matches)) {
                return $matches[1];
            }
            $parent = $folderTree->getParent($parent);

        }
        return null;
    }


    /**
     * Fetch Canvas SisSourceIds and match up to Schoolbox folder codes
     *
     * @param string[][] $remappedFolderCodes
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     */
    private function populateAllCanvasSisSourceIds(array $remappedFolderCodes): void
    {
        // It appears the SisSourceId is made up of ('Y' | $year | 'C' | $subjectCode), with no campus code.
        // The schoolbox folder code has the campus code prefixed, we need to therefore strip it off to compare across the systems.

        // Make a local copy and remove any folder codes that are ambiguous once campus code is removed
        $yearAndFolderCodeToFolderMap = $this->yearAndFolderCodeToFolderMap;
        foreach ($yearAndFolderCodeToFolderMap as $year => $folderCodeToFolderMap) {
            $unPrefixedToOriginalMap = [];
            foreach (array_keys($folderCodeToFolderMap) as $originalCode) {
                $unPrefixed                             = preg_replace("#^[^/]+/#", '', $originalCode);
                $unPrefixedToOriginalMap[$unPrefixed][] = $originalCode;
            }
            foreach ($unPrefixedToOriginalMap as $unPrefixed => $originalCodes) {
                if (count($originalCodes) > 1) {
                    $ambiguousFolderCodes = implode(', ', $originalCodes);
                    $this->log->debug("Ignoring ambiguous folder codes '$ambiguousFolderCodes' for year '$year'");
                    foreach ($originalCodes as $originalCode) {
                        unset($yearAndFolderCodeToFolderMap[$year][$originalCode]);
                    }
                }
            }
        }

        // Now strip the campus prefix
        /** @var Folder\Model[][] $yearAndUnPrefixedCodeToFolderMap */
        $yearAndUnPrefixedCodeToFolderMap = [];
        foreach ($yearAndFolderCodeToFolderMap as $year => $folderCodeToFolderMap) {
            foreach ($folderCodeToFolderMap as $folderCode => $folder) {
                $yearAndUnPrefixedCodeToFolderMap[$year][preg_replace("#^[^/]+/#", '', $folderCode)] = $folder;
            }
        }

        // Fetch all relevant Canvas CourseSection SisSourceIds
        // Initially set mapping to NULL
        $this->sisSourceIdToYearAndFolderCodeMap = [];
        foreach ($this->getSectionSisSourceIds() as $sisSourceId) {
            $this->sisSourceIdToYearAndFolderCodeMap[$sisSourceId] = null;
        }

        // Try to match against the unPrefixed year+folderCodes
        $regexNotMatch                  = [];
        $regexMatchButNoFolderCodeMatch = [];
        $regexMatchAndFolderCodeMatch   = [];

        foreach (array_keys($this->sisSourceIdToYearAndFolderCodeMap) as $sisSourceId) {
            $year        = $this->parseYearFromSisSourceId($sisSourceId) ?? '';
            $subjectCode = $this->parseSubjectCodeFromSisSourceId($sisSourceId);
            if ($subjectCode === null) {
                $regexNotMatch[] = $sisSourceId;
                continue;
            }

            $folder = $yearAndUnPrefixedCodeToFolderMap[$year][$subjectCode] ?? null;

            // No match found in Schoolbox?
            if (!$folder) {
                $regexMatchButNoFolderCodeMatch[] = $sisSourceId;
                continue;
            }

            // Match found, link up maps
            $regexMatchAndFolderCodeMatch[] = $sisSourceId;

            if ($folder->getCodes()->count()) {
                /** @var Folder\Code\Model $firstCodeModel */
                $firstCodeModel = $folder->getCodes()->first();
                $folderCode     = $firstCodeModel->getCode();
            } else {
                $folderCode = $this->parseRolledOverFolderCodeFromFolderName($folder->getName());
            }

            if (isset($remappedFolderCodes[$year][$folderCode])) {
                $folderCode = $remappedFolderCodes[$year][$folderCode];
            }

            $this->yearAndFolderCodeToSisSourceIdMap[$year][$folderCode] = $sisSourceId;
            $this->sisSourceIdToYearAndFolderCodeMap[$sisSourceId]       = [$year, $folderCode];
            $this->folderIdToSisSourceIdMap[$folder->getId()]            = $sisSourceId;
        }

        $this->log->notice(count($this->sisSourceIdToYearAndFolderCodeMap) . ' distinct Canvas CourseSection.SisSourceId processed');
        $this->log->notice(count($regexNotMatch) . ' Canvas CourseSection.SisSourceId were not able to be parsed');
        foreach ($regexNotMatch as $notMatch) {
            $this->log->debug($notMatch);
        }
        $this->log->notice(count($regexMatchButNoFolderCodeMatch) . ' Canvas CourseSection.SisSourceId did not have a folderCode match found');
        foreach ($regexMatchButNoFolderCodeMatch as $noFolderCodeMatch) {
            $this->log->debug($noFolderCodeMatch);
        }
        $this->log->notice(count($regexMatchAndFolderCodeMatch) . ' Canvas CourseSection.SisSourceId were matched to a folderCode');
    }


    /**
     * Returns the Canvas CourseSection.SisSourceIds to match to Schoolbox folder codes
     *
     * @return string[]
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws UnderflowException
     */
    private function getSectionSisSourceIds(): array
    {
        $pb          = $this->courseBridge->getCanvasCoursesToImport();
        $whereClause = $pb->getWhere()[0];
        $sql         = "
            SELECT cs.sis_source_id
            FROM course_dim c
            JOIN course_section_dim cs ON cs.course_id = c.id
            WHERE {$whereClause}
        ";
        return $this->canvasDb->executeQuery($sql, $pb->getParams())->fetchAll(PDO::FETCH_COLUMN);
    }


    /**
     * Attempt to parse a year from a sisSourceId, eg:
     *
     * 'Y2014C09DRA'    => '2014'
     * 'xxxY2014C09DRA' => '2014'
     * 'xxx'            => null
     *
     * @param string $sisSourceId
     * @return string|null
     */
    private function parseYearFromSisSourceId(string $sisSourceId): ?string
    {
        if (preg_match("/^Y(\d{4})C.+$/", $sisSourceId, $canonicalYearMatch)) {
            // This is the canonical form.
            return $canonicalYearMatch[1];
        }
        if (preg_match("/(\d{4})/", $sisSourceId, $fuzzyYearMatch)) {
            // Fall back to any four digits
            return $fuzzyYearMatch[1];
        }
        return null;
    }


    /**
     * Attempt to parse a Subject Code from a sisSourceId, eg:
     *
     * 'Y2014C09DRANOT' => '09DRA'
     * 'Y2014C09DRA'    => '09DRA'
     * 'C09DRA'         => null
     *
     * @param string $sisSourceId
     * @return string|null
     */
    private function parseSubjectCodeFromSisSourceId(string $sisSourceId): ?string
    {
        // Parse subject code (exact match only, no fuzzy fall back for now)
        if (preg_match("/^Y\d{4}C(.+)$/", $sisSourceId, $canonicalSubjectMatch)) {
            // This is the canonical form.
            return preg_replace("/NOT$/", '', $canonicalSubjectMatch[1]);
        }
        return null;
    }
}
