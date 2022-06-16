<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Color\ColorFactory;
use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\RoleAccess;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\HomepageConfig;
use Schoolbox\Resource\HomepageTemplate;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\RoleMapper;
use Schoolbox\YearLevel;

/**
 * Bulk_Folder
 *
 * Upload folder structures from a csv
 */
class Bulk_Folder
{
    /**
     * Default id for HomepageTemplate instance
     *
     * @var int
     */
    private $templateID;

    /**
     * Maps role.id to accessLevel
     * @var int[]
     */
    private $rolePerms;

    /** @var bool */
    private $inherit;

    /**
     * Storage object
     * @var resource
     */
    private $fp;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var RoleAccessMapper */
    private $roleAccessMapper;

    /** @var RoleMapper */
    private $roleMapper;

    /** @var HomepageConfigMapper */
    private $homepageConfigMapper;

    /** @var YearLevel\Mapper */
    private $yearLevelMapper;

    /** @var StorageMapper */
    private $storageMapper;

    /** @var FileMapper */
    private $fileMapper;

    private HomepageTemplateMapper $homepageTemplateMapper;

    /**
     * list of homepage templates to help
     */
    private $homepageTemplateList;

    /**
     * Constructor
     *
     * @param resource $file
     * @param int      $templateID
     * @param array    $rolePerms
     * @param bool     $inherit
     */
    public function __construct($file, int $templateID, array $rolePerms, bool $inherit)
    {
        if (!is_resource($file)) {
            throw new TypeError(sprintf('$file must be a resource, found: %s', gettype($file)));
        }
        $this->templateID = $templateID;
        $this->rolePerms  = array_map('\intval', $rolePerms);
        $this->inherit    = $inherit;
        $this->fp         = (new \Alaress\Csv\ValidCSV($file))->skipBom();

        // TODO inject dependencies
        $this->folderMapper         = Folder\Mapper::getInstance();
        $this->roleAccessMapper     = RoleAccessMapper::getInstance();
        $this->roleMapper           = RoleMapper::getInstance();
        $this->homepageConfigMapper = HomepageConfigMapper::getInstance();
        $this->yearLevelMapper      = YearLevel\Mapper::getInstance();
        $this->storageMapper        = StorageMapper::getInstance();
        $this->fileMapper           = FileMapper::getInstance();
        $this->homepageTemplateMapper = HomepageTemplateMapper::getInstance();
        $this->homepageTemplateList = $this->homepageTemplateMapper->findAll();
    }


    /**
     * Adds folders via csv
     *
     * @param bool $perform
     * @return array
     * @throws DefinitionException
     * @throws InvalidArgumentException
     * @throws InvalidColumnException
     * @throws MapperException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function addFolders(bool $perform): array
    {
        $results = [];

        // Parse Header Row
        $headerRow = fgetcsv($this->fp, 1000, ",");

        // Determine column semantics from header values
        $parentColumn       = $this->determineColumnIndex($headerRow, 'parent');
        $folderNameColumn   = $this->determineColumnIndex($headerRow, 'name');
        $folderColourColumn = $this->determineColumnIndex($headerRow, 'colour', false);
        $folderImageColumn  = $this->determineColumnIndex($headerRow, 'image', false);
        $folderCodeColumns  = $this->determineColumnIndexes($headerRow, 'code');
        $yearLevelColumns   = $this->determineColumnIndexes($headerRow, 'year');
        $templateColumn     = $this->determineColumnIndex($headerRow, 'template', false);

        // Check no overlapping matches
        $this->assertColumnIndexesAreUnique([$parentColumn, $folderNameColumn], $folderCodeColumns, $yearLevelColumns);

        $yearLevels = $this->getAllYearLevels();

        $rowIdx = 2; // We have already processed the header in row 1
        while (($row = fgetcsv($this->fp, 1000, ",")) !== false) {
            $results[] = $this->processCsvRow(
                $rowIdx++,
                $row,
                $parentColumn,
                $folderNameColumn,
                $folderColourColumn,
                $folderImageColumn,
                $folderCodeColumns,
                $yearLevelColumns,
                $yearLevels,
                $perform,
                $templateColumn
            );
        }

        return $results;
    }


    /**
     * Process a single row from the CSV and returns an array describing the processing results.
     *
     * @param int                                 $rowIdx
     * @param array                               $row
     * @param int                                 $parentColumnIndex
     * @param int                                 $folderNameColumnIndex
     * @param int                                 $folderColourColumnIndex
     * @param int                                 $folderImageColumnIndex
     * @param int[]                               $folderCodeColumnIndexes
     * @param int[]                               $yearLevelColumnIndexes
     * @param GenericCollection|YearLevel\Model[] $yearLevels Indexed by yearLevel.name
     * @param bool                                $perform
     * @return array
     * @throws DefinitionException
     * @throws InvalidColumnException
     * @throws MapperException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function processCsvRow(
        int $rowIdx,
        array $row,
        int $parentColumnIndex,
        int $folderNameColumnIndex,
        int $folderColourColumnIndex,
        int $folderImageColumnIndex,
        array $folderCodeColumnIndexes,
        array $yearLevelColumnIndexes,
        GenericCollection $yearLevels,
        bool $perform,
        int $templateColumnIndex
    ): array {

        $parentId   = trim($row[$parentColumnIndex]);
        $folderName = trim($row[$folderNameColumnIndex]);

        // Parent folder and Import Folder name must exist
        if (($parentId === '') || ($folderName === '')) {
            $error = "Parent id and folder name must be provided. Found: parent='$parentId', folderName='$folderName'";
            return $this->buildRowResult($rowIdx, '', '', 'Not Added', $error);
        }

        // Check parent exists
        try {
            $parent = $this->folderMapper->findById($parentId);
        } catch (MapperException $e) {
            return $this->buildRowResult($rowIdx, '', $folderName, 'Not Added', "Could not find parent folder {$parentId}");
        }

        $warnings = [];

        //set template id to default
        $templateId = $this->templateID;
        //if template has been set in the csv
        if ($templateColumnIndex >= 0 && '' !== trim($row[$templateColumnIndex])) {
            try {
                //grab that template if it exists
                $templateObj = $this->homepageTemplateList->getBy('name', trim($row[$templateColumnIndex]));
                $templateId = $templateObj->getId();
            } catch (MapperException $e) {
                //noop csv template does not exist, ignore the template and use the one specified in the webform
                $warnings[] = sprintf('%s is not a homepage template.', trim($row[$templateColumnIndex]));
            }
        }
        //set homepage to the specified template
        $homepageConfig = (new HomepageConfig())->setTemplate((new HomepageTemplate())->setId($templateId));

        // Create new Folder
        $newFolder = (new Folder\Model())
            ->setName($folderName)
            ->setParent($parent)
            ->setHomepage($homepageConfig);
        $homepageConfig->setCategory($newFolder);

        // Folder Colour
        $folderColour = $folderColourColumnIndex >= 0 ? trim($row[$folderColourColumnIndex]) : '';
        if (!empty($folderColour)) {
            try {
                $newFolder->setColour((new ColorFactory())->build($folderColour));
            } catch (InvalidArgumentException $e) {
                $warnings[] = "{$folderColour} is not a valid RGB or HEX colour.";
            }
        }

        // Folder Image
        $folderImageHash = $folderImageColumnIndex >= 0 ? trim($row[$folderImageColumnIndex]) : '';
        if (!empty($folderImageHash)) {
            // Get csv column's string
            // this could be anything

            $file = null;
            try {
                if (preg_match_all('#(send|image)\.php\?.*id=(\d+)#', $folderImageHash, $matches) || preg_match('/^[0-9]+$/', $folderImageHash, $matches)) {
                    // if it was send or cms/image, or a straight numeric this could be an id, we need a filemapper instance
                    // $file load by ID
                    // do basic error checking and exit early
                    $file = $this->fileMapper->findById(end($matches));
                } elseif (preg_match_all('#(image|fetch)\.php\?.*hash=([a-zA-Z0-9]+)#', $folderImageHash, $matches) || preg_match('/^[0-9a-zA-Z]+$/', $folderImageHash, $matches)) {
                    // if it was storage/(image/fetch), or a straight alphanumeric (this could be a hash), we need a StorageMapper instance
                    // $file = load by hash
                    // do basic error checking and exit early
                    $file = $this->storageMapper->findByHash(end($matches));
                } else {
                    // exit early: set the $warning[] string and continue creating the folder
                    // (the format was not correct, and we couldn't get the file identifier)
                    // $warnings[] = 'no identifier detected'
                    $warnings[] = "Unable to find image {$folderImageHash}";
                }
            } catch (\Schoolbox\Mapper\Exception $e) {
                // exit early: set $warning and continue creating folder.
                // (url was correctly formatted, but we couldn't load the file itself)
                $warnings[] = "Unable to find the image {$folderImageHash}";
            }

            // if we got here, there were no errors

            if (null !== $file) {
            // check that $file is an image
                if ($file->getMimetype()->isImage()) {
                    $newFolder->setCoverImage($file);
                } else {
                    $warnings[] = "File {$folderImageHash} is not an image";
                }
            }
        } //end if empty $folderImageHash

        // Folder Codes
        $result = $this->processFolderCodes($rowIdx, $row, $newFolder, $folderCodeColumnIndexes);
        if ($result) {
            return $result;
        }

        // Year Levels
        $result = $this->processYearLevels($rowIdx, $row, $newFolder, $yearLevels, $yearLevelColumnIndexes);
        if ($result) {
            return $result;
        }

        // Persist models to DB.
        $newFolder->setInherit($this->inherit && count($newFolder->getCodes()) === 0);
        if (!$perform) {
            return $this->buildRowResult($rowIdx, '', $newFolder->getNameWithCodes(), 'Would Add', !empty($warnings) ? implode(' ', $warnings) : 'OK');
        }

        try {
            $this->folderMapper->save($newFolder);
        } catch (Throwable $e) {
            // This should never happen, but if it does, log it and try to continue
            trigger_error($e->getMessage(), E_USER_WARNING);
            return $this->buildRowResult($rowIdx, '', $newFolder->getName(), 'Error', $e->getMessage());
        }
        $this->saveHomepageConfig($newFolder->getHomepage());
        $this->saveRolePermissions($newFolder);

        return $this->buildRowResult($rowIdx, (string)$newFolder->getId(), $newFolder->getNameWithCodes(), 'Added', !empty($warnings) ? implode(' ', $warnings) : 'OK');
    }


    /**
     * Checks folder codes and adds them to folder.
     *
     * If an error is detected, returns an array describing the issue.
     *
     * @param int          $rowIdx
     * @param array        $row
     * @param Folder\Model $newFolder
     * @param array        $folderCodeColumnIndexes
     * @return array|null
     * @throws InvalidColumnException
     * @throws PDOException
     */
    private function processFolderCodes(int $rowIdx, array $row, Folder\Model $newFolder, array $folderCodeColumnIndexes): ?array
    {
        $codes = $lowerCaseCodes = [];
        foreach ($folderCodeColumnIndexes as $i) {
            $folderCode = trim($row[$i]);
            if ($folderCode === '') {
                continue;
            }
            $lowerCaseCode = strtolower($folderCode);

            // Have we seen this code before (ignoring case)?
            if (in_array($lowerCaseCode, $lowerCaseCodes, true)) {
                // If a case-sensitive duplicate, we can ignore the duplicate
                if (in_array($folderCode, $codes, true)) {
                    continue;
                }
                // Otherwise if differ only in case, we cannot be sure which one to persist
                $error = "Ambiguous folder code. Same characters but different capitalisation ({$folderCode})";
                return $this->buildRowResult($rowIdx, '', $newFolder->getName(), 'Skipped', $error);
            }

            // Check if code too long
            if (strlen($folderCode) > Folder\Code\Model::CODE_LENGTH_MAX) {
                $error = sprintf("Folder code exceeds %d characters ({$folderCode})", Folder\Code\Model::CODE_LENGTH_MAX);
                return $this->buildRowResult($rowIdx, '', $newFolder->getName(), 'Skipped', $error);
            }

            // Check if folder code already exists
            try {
                $this->folderMapper->findByCode($folderCode);
                return $this->buildRowResult($rowIdx, '', $folderCode, 'Skipped', "Folder code already exists ({$folderCode})");
            } catch (MapperException $e) {
                // noop - folder doesn't exist
                $codes[]          = $folderCode;
                $lowerCaseCodes[] = $lowerCaseCode;
            }
        }

        // Accumulate all folder codes
        $folderCodeModels = Folder\Code\Model::createCollection();
        foreach ($codes as $currentCode) {
            $folderCode = (new Folder\Code\Model())
                ->setFolder($newFolder)
                ->setCode($currentCode);
            $folderCodeModels->add($folderCode);
        }
        $newFolder->setCodes($folderCodeModels);
        return null;
    }


    /**
     * Check yearLevels and add them to folder model.
     *
     * If an error is detected, returns an array describing the issue.
     *
     * @param int               $rowIdx
     * @param array             $row
     * @param Folder\Model      $newFolder
     * @param GenericCollection $yearLevels
     * @param array             $yearLevelColumnIndexes
     * @return array|null
     */
    private function processYearLevels(
        int $rowIdx,
        array $row,
        Folder\Model $newFolder,
        GenericCollection $yearLevels,
        array $yearLevelColumnIndexes
    ): ?array {
        $folderYearLevels = Schoolbox\YearLevel\Model::createCollection();
        foreach ($yearLevelColumnIndexes as $yearLevelColumnIndex) {
            $yearLevelName = trim($row[$yearLevelColumnIndex]);
            if (!$yearLevelName) {
                continue;
            }

            $yearLevel = $yearLevels->getOffset($yearLevelName);
            if (!$yearLevel) {
                return $this->buildRowResult($rowIdx, '', $newFolder->getName(), 'Skipped', "Year Level '$yearLevelName' not found");
            }
            $folderYearLevels->add($yearLevel);
        }
        $newFolder->setYearLevels($folderYearLevels);
        return null;
    }


    /**
     * Saves the homepage config
     *
     * @param HomepageConfig $homepageConfig
     * @throws MapperException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function saveHomepageConfig($homepageConfig): void
    {
        //save the template config when there is a template specified
        if ($homepageConfig->getTemplate()->getId()) {
            $this->homepageConfigMapper->save($homepageConfig);
            $this->homepageConfigMapper->resetToTemplate($homepageConfig);
        }
    }


    /**
     * If folder does not inherit, save new Role Access permissions
     *
     * @param Folder\Model $newFolder
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function saveRolePermissions(Folder\Model $newFolder): void
    {
        if (!$newFolder->getInherit()) {
            foreach ($this->rolePerms as $id => $perm) {
                try {
                    $role       = $this->roleMapper->findById($id);
                    $roleAccess = (new RoleAccess())
                        ->setCategory($newFolder)
                        ->setRole($role)
                        ->setType($perm);
                    $this->roleAccessMapper->save($roleAccess);
                } catch (MapperException $e) {
                    // noop - role not found
                }
            }
        }
    }


    /**
     * Consistently build an array that describes the result of a row's processing.
     *
     * @param int    $rowIdx
     * @param string $id
     * @param string $folderName
     * @param string $status
     * @param string $error
     * @return array
     */
    private function buildRowResult(int $rowIdx, string $id, string $folderName, string $status, string $error): array
    {
        return [
            'row'    => $rowIdx,
            'id'     => $id,
            'name'   => $folderName,
            'status' => $status,
            'error'  => $error,
        ];
    }


    /**
     * Returns the 0-based index of the column that contains the specified search string.
     *
     * @param array  $headerRow
     * @param string $search
     * @param bool   $required
     * @return int
     * @throws UnexpectedValueException
     */
    private function determineColumnIndex(array $headerRow, string $search, bool $required = true): int
    {
        $parentIdColumns = [];
        foreach ($headerRow as $idx => $value) {
            if (stripos($value, $search) !== false) {
                $parentIdColumns[] = $idx;
            }
        }
        if (count($parentIdColumns) === 1) {
            return reset($parentIdColumns);
        }

        if (!$required) {
            return -1;
        }

        $headerRowValues = implode(',', $headerRow);
        throw new UnexpectedValueException("Unable to locate unique '$search' column from headers: '$headerRowValues'");
    }


    /**
     * Returns the 0-based index of the columns that contains the specified search string.
     *
     * @param array  $headerRow
     * @param string $search
     * @return int[]
     */
    private function determineColumnIndexes(array $headerRow, string $search): array
    {
        $columnIndexes = [];
        foreach ($headerRow as $idx => $value) {
            if (stripos($value, $search) !== false) {
                $columnIndexes[] = $idx;
            }
        }
        return $columnIndexes;
    }


    /**
     * Assert that there are no duplicate values across a set of arrays
     *
     * @param array $columnIndexes
     * @throws UnexpectedValueException
     */
    private function assertColumnIndexesAreUnique(array ...$columnIndexes): void
    {
        $counts = [];
        foreach (array_merge(...$columnIndexes) as $index) {
            $counts[$index] ??= 0;
            $counts[$index]++;
        }
        $duplicates = [];
        foreach ($counts as $index => $count) {
            if ($count > 1) {
                $duplicates[] = $index;
            }
        }
        if (count($duplicates)) {
            throw new UnexpectedValueException("Ambiguous columns: " . implode(',', $duplicates));
        }
    }


    /**
     * Returns all year level names known to system, indexed by name
     *
     * @return GenericCollection|YearLevel\Model[]
     * @throws InvalidArgumentException
     */
    private function getAllYearLevels(): GenericCollection
    {
        $nameProperty = $this->yearLevelMapper->getPropertyFromColumn($this->yearLevelMapper::COL_NAME);
        return $this->yearLevelMapper->findAll()->indexBy($nameProperty);
    }
}
