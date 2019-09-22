<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace External\Canvas\Sync\Importer;

use Alaress\Database;
use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use Alaress\DataStructures\ObjectTree;
use Psr\Log\LoggerInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Resource\Folder;
use SplQueue;
use UnexpectedValueException;
use function array_key_exists;
use function array_keys;
use function count;
use function is_array;
use function preg_match;
use function preg_replace;
use function reset;

/**
 * Responsible for importing Course Accounts from Canvas to Schoolbox
 *
 * The folder structure in schoolbox will mimic the course account ownership structure in Canvas.
 *
 * We can then import courses from canvas to these schoolbox folders
 *
 * @todo  : For now, the link between a canvas account and schoolbox folder is via the canvas id
 *        being embedded in the folder name, eg 'History (canvas-123)' where '123' is the canvas account id.
 *        This needs to be moved to folder.externalId when it becomes available
 *
 * @todo  We probably should only import accounts that either have relevant courses, or are parents of such accounts.
 */
class CourseAccounts
{

    /** @var IConnector */
    private $canvasDb;

    /** @var IConnector */
    private $schoolboxDb;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var bool */
    private $persistChanges;

    /** @var LoggerInterface */
    private $log;

    /** @var int */
    private $facultyFolderId;

    // Per-execution counts
    private $countFoldersSynchronised;
    private $countFoldersCreated;
    private $countFoldersUpdated;  // if any non-parent-relationship attribute changes
    private $countFoldersMoved;    // if a folder's parent changes
    private $countFoldersDeleted;


    /**
     * @param IConnector      $canvasDb
     * @param IConnector      $schoolboxDb
     * @param Folder\Mapper   $folderMapper
     * @param ConfigMapper    $configMapper
     * @param bool            $persistChanges
     * @param LoggerInterface $log
     * @throws UnexpectedValueException
     */
    public function __construct(
        IConnector $canvasDb,
        IConnector $schoolboxDb,
        Folder\Mapper $folderMapper,
        ConfigMapper $configMapper,
        bool $persistChanges,
        LoggerInterface $log
    ) {
        $this->canvasDb       = $canvasDb;
        $this->schoolboxDb    = $schoolboxDb;
        $this->folderMapper   = $folderMapper;
        $this->persistChanges = $persistChanges;
        $this->log            = $log;

        $facultyFolderId = preg_replace('/[\D]/', '', $configMapper->get('course_homepage'));
        if (!$facultyFolderId) {
            throw new UnexpectedValueException('Faculty root not configured. Please configure in schoolbox admin');
        }

        $this->facultyFolderId = (int)$facultyFolderId;
    }


    /**
     * @throws Database\Exception
     * @throws UnexpectedValueException
     */
    public function synchronise(): void
    {
        $this->log->info("Synchronising canvas accounts to course folders...");
        $this->resetCounts();

        $canvasAccountTree = $this->buildCanvasCourseAccountTree();
        if (count($canvasAccountTree) !== 1) {
            throw new UnexpectedValueException('Unable to locate root canvas account');
        }
        $rootAccount = reset($canvasAccountTree);

        $folderTree = $this->buildCourseFacultyFolderTree($rootAccount['canvas_id']);

        // Start the sync
        foreach ($rootAccount['children'] as $topLevelAccount) {
            $this->syncAccountWithFolders($topLevelAccount, $folderTree);
        }

        $this->deleteOldSchoolboxCourseAccountFolders($canvasAccountTree, $folderTree);
        $this->outputResults();
    }


    /**
     * Reset counts.
     */
    private function resetCounts(): void
    {
        $this->countFoldersSynchronised = 0;
        $this->countFoldersCreated      = 0;
        $this->countFoldersUpdated      = 0;
        $this->countFoldersMoved        = 0;
        $this->countFoldersDeleted      = 0;
    }


    /**
     * Build and return a fast, in-memory, mutable representation of the schoolbox course folder hierarchy
     *
     * @param string $rootAccountCanvasId
     * @return ObjectTree
     * @throws Database\Exception
     * @throws UnexpectedValueException
     */
    private function buildCourseFacultyFolderTree(string $rootAccountCanvasId): ObjectTree
    {
        // Index folders by their Canvas id so we can process them purely in memory
        $folderTree = new ObjectTree(function (Folder\Model $folder) {
            return $this->getCanvasIdFromFolderName($folder->getName());
        });

        // Ensure the faculty folder aligns
        $sql  = "SELECT name, parent FROM folder WHERE id = :id AND deleted_at IS NULL";
        $rows = $this->schoolboxDb->executeQuery($sql, ['id' => $this->facultyFolderId])->fetchAll();

        if (count($rows) !== 1) {
            throw new UnexpectedValueException("Unable to locate faculty folder");
        }

        $folder = (new Folder\Model())
            ->setId($this->facultyFolderId)
            ->setName($rows[0]['name']);

        // Check the faculty folder name is valid, update it if required.
        try {
            $this->getCanvasIdFromFolderName($folder->getName());
        } catch (UnexpectedValueException $e) {
            $folder->setName($this->generateLinkedFolderName($folder->getName(), $rootAccountCanvasId));
            $this->updateFolder($folder->getId(), $folder->getName(), (int)$rows[0]['parent']);
            $this->log->info("Faculty folder name updated to '{$folder->getName()}'");
        }
        $folderTree->addElement($folder);


        $this->addFoldersToTree($folderTree, [$this->facultyFolderId]);

        return $folderTree;
    }


    /**
     * Recursively build and return a representation of the canvas account hierarchy
     *
     * @param array|null $parentIds
     * @return array
     * @throws Database\Exception
     */
    private function buildCanvasCourseAccountTree(array $parentIds = null): array
    {
        // Base Case:
        if (is_array($parentIds) && !count($parentIds)) {
            return [];
        }

        // Recursive Case:
        if ($parentIds === null) {
            $whereClause = 'parent_account_id IS NULL';
            $params      = [];
        } else {
            $bindings    = Builder::getBindings($parentIds, 'pai');
            $whereClause = "parent_account_id IN ({$bindings->getPlaceholder()})";
            $params      = $bindings->getParams();
        }
        $sql = "SELECT id, parent_account_id, canvas_id, name FROM account_dim WHERE {$whereClause}";

        $accounts = [];
        foreach ($this->canvasDb->executeQuery($sql, $params)->fetchAll() as $row) {
            $row['children']      = [];
            $accounts[$row['id']] = $row;
        }

        foreach ($this->buildCanvasCourseAccountTree(array_keys($accounts)) as $childId => $child) {
            $child['parent_canvas_id']                                   = $accounts[$child['parent_account_id']]['canvas_id'];
            $accounts[$child['parent_account_id']]['children'][$childId] = $child;
        }

        return $accounts;
    }


    /**
     * Recursively add schoolbox folders to folder tree structure
     *
     * @param ObjectTree $folderTree
     * @param array      $parentIds
     * @throws Database\Exception
     * @throws UnexpectedValueException
     */
    private function addFoldersToTree(ObjectTree $folderTree, array $parentIds): void
    {
        // Base Case:
        if (!count($parentIds)) {
            return;
        }

        // Recursive case
        $bindings = Builder::getBindings($parentIds, 'pi');

        $sql  = "
            SELECT f.id 'f.id', f.name 'f.name', p.name 'p.name'
            FROM folder f
            JOIN folder p ON f.parent = p.id
            WHERE p.id IN ({$bindings->getPlaceholder()}) AND f.deleted_at IS NULL";
        $rows = $this->schoolboxDb->executeQuery($sql, $bindings->getParams())->fetchAll();

        $folderIds = [];
        foreach ($rows as $row) {
            $folderIds[]          = $row['f.id'];
            $parentFolderCanvasId = $this->getCanvasIdFromFolderName($row['p.name']);
            /** @var Folder\Model|null $parent */
            $parent = $folderTree->getElementByHash($parentFolderCanvasId);
            $folder = (new Folder\Model())
                ->setId((int)$row['f.id'])
                ->setName($row['f.name'])
                ->setParent($parent);
            $folderTree->addElement($folder);
        }

        $this->addFoldersToTree($folderTree, $folderIds);
    }


    /**
     * Recursively sync the canvas course account structure to the schoolbox course folder structure
     *
     * @param array      $canvasAccount
     * @param ObjectTree $sbFolderTree
     * @throws Database\Exception
     * @throws UnexpectedValueException
     * @internal string $canvasAccount['id']                        '19400000000000217'
     * @internal string $canvasAccount['parent_account_id']         '19400000000000001'
     * @internal string $canvasAccount['canvas_id']                 '217'
     * @internal string $canvasAccount['parent_canvas_id']          '1'
     * @internal string $canvasAccount['name']                      'Training'
     * @internal array  $canvasAccount['children']
     */
    private function syncAccountWithFolders(array $canvasAccount, ObjectTree $sbFolderTree): void
    {
        $this->countFoldersSynchronised++;

        /** @var Folder\Model $parent */
        $parent     = $sbFolderTree->getElementByHash($canvasAccount['parent_canvas_id']);
        $folder     = $sbFolderTree->getElementByHash($canvasAccount['canvas_id']);
        $folderName = $this->generateLinkedFolderName($canvasAccount['name'], $canvasAccount['canvas_id']);

        // Does folder not exist yet in SB?
        if (!$folder) {
            $this->countFoldersCreated++;
            $this->log->debug("Creating new folder '$folderName' in parent '{$parent->getName()}'");
            $folder = $this->createFolder($folderName, $parent);
            $sbFolderTree->addElement($folder, $parent);
        }

        $updateNeeded = false;
        if ($folder->getName() !== $folderName) {
            $updateNeeded = true;
            $this->countFoldersUpdated++;
            $this->log->debug("Updating folder name '{$folder->getName()}' to '$folderName'");
            $folder->setName($folderName);
        }
        if ($folder->getParent()->getId() !== $parent->getId()) {
            $updateNeeded = true;
            $this->countFoldersMoved++;
            $this->log->debug("Moving folder '{$folderName}' from '{$folder->getParent()->getId()}' to '{$parent->getId()}'");
            $folder->setParent($parent);
            $sbFolderTree->moveElement($folder, $parent);
        }
        if ($updateNeeded) {
            $this->updateFolder($folder->getId(), $folder->getName(), $folder->getParent()->getId());
        }

        foreach ($canvasAccount['children'] as $childAccount) {
            $this->syncAccountWithFolders($childAccount, $sbFolderTree);
        }
    }


    /**
     * Create a new Schoolbox folder
     *
     * @param string       $name
     * @param Folder\Model $parent
     * @return Folder\Model
     * @throws Database\Exception
     */
    private function createFolder(string $name, Folder\Model $parent): Folder\Model
    {
        $sql = "INSERT INTO folder (name, parent, inherit) VALUES (:name, :parent, 1)";
        $this->schoolboxDb->executeQuery($sql, ['name' => $name, 'parent' => $parent->getId()]);
        return (new Folder\Model())
            ->setId((int)$this->schoolboxDb->lastInsertId())
            ->setName($name)
            ->setParent($parent);
    }


    /**
     * Update a schoolbox folder
     *
     * @param int    $folderId
     * @param string $folderName
     * @param int    $parentId
     * @throws Database\Exception
     */
    private function updateFolder(int $folderId, string $folderName, int $parentId): void
    {
        $sql = "UPDATE folder SET name = :name, parent = :parent WHERE id = :id";
        $this->schoolboxDb->executeQuery($sql, [
            'name'   => $folderName,
            'parent' => $parentId,
            'id'     => $folderId,
        ]);
    }


    private function outputResults(): void
    {
        $this->log->info($this->countFoldersSynchronised . ' accounts/folders synchronised');
        $this->log->info($this->countFoldersCreated . ' folders created');
        $this->log->info($this->countFoldersUpdated . ' folders updated');
        $this->log->info($this->countFoldersMoved . ' folders moved');
        $this->log->info($this->countFoldersDeleted . ' folders deleted');
    }


    /**
     * @param string $folderNamePrefix
     * @param string $canvasId
     * @return string
     * @todo replace when folder.externalId becomes available
     */
    private function generateLinkedFolderName(string $folderNamePrefix, string $canvasId): string
    {
        return "$folderNamePrefix (canvas-{$canvasId})";
    }


    /**
     * Returns the canvas-id embedded inside the folderName
     *
     * @param string $folderName
     * @return string
     * @throws UnexpectedValueException
     * @todo replace when folder.externalId becomes available
     */
    private function getCanvasIdFromFolderName(string $folderName): string
    {
        if (preg_match('/\(canvas-(\d+)\)/', $folderName, $matches)) {
            return $matches[1];
        }
        throw new UnexpectedValueException("Unable to determine canvas.id from folder name '$folderName'");
    }


    /**
     * Deletes any folders that were imported from Canvas in the past, but are no longer required
     *
     * @param array      $canvasAccountTree
     * @param ObjectTree $folderTree
     */
    private function deleteOldSchoolboxCourseAccountFolders(array $canvasAccountTree, ObjectTree $folderTree): void
    {
        // Flatten canvas accounts using a depth-first iteration
        $queue = new SplQueue();
        foreach ($canvasAccountTree as $child) {
            $queue->enqueue($child);
        }
        $canvasIds = [];
        while (!$queue->isEmpty()) {
            $node                          = $queue->dequeue();
            $canvasIds[$node['canvas_id']] = true;
            foreach ($node['children'] as $child) {
                $queue->enqueue($child);
            }
        }

        /** @var Folder\Model $folder */
        foreach ($folderTree->toFlatArray() as $folder) {
            try {
                $canvasId = $this->getCanvasIdFromFolderName($folder->getName());
            } catch (UnexpectedValueException $e) {
                // Not a canvas-synced folder, don't delete this!
                continue;
            }

            if (!array_key_exists($canvasId, $canvasIds)) {
                $this->log->info("Deleting folder '{$folder->getName()}'");
                $this->countFoldersDeleted++;
                if ($this->persistChanges) {
                    $this->folderMapper->deleteByColumn('id', $folder->getId());
                }
            }
        }
    }
}
