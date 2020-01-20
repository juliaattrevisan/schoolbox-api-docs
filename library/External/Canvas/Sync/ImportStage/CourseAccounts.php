<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace External\Canvas\Sync\ImportStage;

use Alaress\Database;
use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use Alaress\DataStructures\ObjectTree;
use External\Canvas\Sync\Bridge;
use PDOException;
use Psr\Log\LoggerInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\TreeBuilder;
use SplQueue;
use UnderflowException;
use UnexpectedValueException;
use function array_key_exists;
use function array_keys;
use function count;
use function is_array;
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
 *        being embedded in the folder name, eg 'History (canvas-account-123)' where '123' is the canvas account id.
 *        This needs to be moved to folder.externalId when it becomes available
 */
class CourseAccounts
{

    /** @var IConnector */
    private $canvasDb;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var Bridge\Account */
    private $accountBridge;

    /** @var LoggerInterface */
    private $log;

    /** @var int */
    private $facultyFolderId;

    // Per-execution counts
    private $countCanvasAccountsSynchronised;

    private $countFoldersCreated;
    private $countFoldersUpdated;  // if any non-parent-relationship attribute changes
    private $countFoldersMoved;    // if a folder's parent changes
    private $countFoldersDeleted;


    /**
     * @param IConnector      $canvasDb
     * @param Folder\Mapper   $folderMapper
     * @param IConfigLoader   $config
     * @param LoggerInterface $log
     * @param Bridge\Account  $accountBridge
     * @throws UnexpectedValueException
     */
    public function __construct(
        IConnector $canvasDb,
        Folder\Mapper $folderMapper,
        IConfigLoader $config,
        LoggerInterface $log,
        Bridge\Account $accountBridge
    ) {
        $this->canvasDb      = $canvasDb;
        $this->folderMapper  = $folderMapper;
        $this->accountBridge = $accountBridge;
        $this->log           = $log;

        $facultyFolderId = preg_replace('/[\D]/', '', $config->get('course_homepage'));
        if (!$facultyFolderId) {
            throw new UnexpectedValueException('Faculty root not configured. Please configure in schoolbox admin');
        }

        $this->facultyFolderId = (int)$facultyFolderId;
    }


    /**
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnderflowException
     * @throws UnexpectedValueException
     * @throws PDOException
     */
    public function synchronise(): void
    {
        $this->log->notice("Synchronising canvas accounts to course folders...");
        $this->resetCounts();

        $canvasAccountTree = $this->buildCanvasCourseAccountTree();
        if (count($canvasAccountTree) !== 1) {
            throw new UnexpectedValueException('Unable to locate root canvas account');
        }

        $folderTree = $this->buildCourseFacultyFolderTree();
        $this->syncAccountsWithFolders(reset($canvasAccountTree), $folderTree);
        $this->deleteOldSchoolboxCourseAccountFolders($canvasAccountTree, $folderTree);
        $this->outputResults();
    }


    /**
     * Reset counts.
     */
    private function resetCounts(): void
    {
        $this->countCanvasAccountsSynchronised = 0;
        $this->countFoldersCreated             = 0;
        $this->countFoldersUpdated             = 0;
        $this->countFoldersMoved               = 0;
        $this->countFoldersDeleted             = 0;
    }


    /**
     * Build and return a fast, in-memory, mutable representation of the schoolbox course folder hierarchy
     *
     * @return ObjectTree
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws UnexpectedValueException
     * @throws Mapper\Exception
     * @throws UnderflowException
     */
    private function buildCourseFacultyFolderTree(): ObjectTree
    {
        // Index folders by their Canvas id so we can process them purely in memory
        $hashFunction = function (Folder\Model $folder) {
            if ($folder->getId() === $this->facultyFolderId) {
                /* Real CanvasIds are > 0.
                 * Let's give the faculty folder a fake CanvasId that falls outside the real range
                 * so we can use this hash function to index the faculty folder, but not collide
                 * with any real CanvasIds.
                 */
                return -1;
            }
            return $this->accountBridge->getAccountCanvasIdFromFolder($folder);
        };

        $treeBuilder = new TreeBuilder($this->folderMapper);

        // We only want canvas account folders in our tree
        $pb = $this->accountBridge->equalsAnyAccountFolder();

        return $treeBuilder->buildTree($this->facultyFolderId, $hashFunction, $pb);
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
     * Recursively sync the canvas course account structure to the schoolbox course folder structure
     *
     * @param array      $canvasAccount
     * @param ObjectTree $sbFolderTree
     * @throws Database\Exception
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     * @internal string $canvasAccount['id']                        '19400000000000217'
     * @internal string $canvasAccount['parent_account_id']         '19400000000000001'
     * @internal string $canvasAccount['canvas_id']                 '217'
     * @internal string $canvasAccount['parent_canvas_id']          '1'
     * @internal string $canvasAccount['name']                      'Training'
     * @internal array  $canvasAccount['children']
     */
    private function syncAccountsWithFolders(array $canvasAccount, ObjectTree $sbFolderTree): void
    {
        $this->countCanvasAccountsSynchronised++;

        // Is this a root account?
        if (($canvasAccount['parent_canvas_id'] ?? null) === null) {
            // Place it in the faculty folder.
            $parent = (new Folder\Model())->setId($this->facultyFolderId);
        } else {
            /** @var Folder\Model $parent */
            $parent = $sbFolderTree->getElementByHash($canvasAccount['parent_canvas_id']);
        }

        $folder     = $sbFolderTree->getElementByHash($canvasAccount['canvas_id']);
        $folderName = $this->accountBridge->generateLinkedFolderName($canvasAccount['name'], $canvasAccount['canvas_id']);

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
            $this->folderMapper->save($folder);
        }

        foreach ($canvasAccount['children'] as $childAccount) {
            $this->syncAccountsWithFolders($childAccount, $sbFolderTree);
        }
    }


    /**
     * Create a new Schoolbox folder
     *
     * @param string       $name
     * @param Folder\Model $parent
     * @return Folder\Model
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     * @throws PDOException
     */
    private function createFolder(string $name, Folder\Model $parent): Folder\Model
    {
        $folder = (new Folder\Model())
            ->setName($name)
            ->setParent($parent);
        $this->folderMapper->save($folder);
        return $folder;
    }


    private function outputResults(): void
    {
        $this->log->notice($this->countCanvasAccountsSynchronised . ' Canvas Accounts synchronised');
        $this->log->notice($this->countFoldersCreated . ' Schoolbox folders created');
        $this->log->notice($this->countFoldersUpdated . ' Schoolbox folders updated');
        $this->log->notice($this->countFoldersMoved . ' Schoolbox folders moved');
        $this->log->notice($this->countFoldersDeleted . ' Schoolbox folders deleted');
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
                $canvasId = $this->accountBridge->getAccountCanvasIdFromFolder($folder);
            } catch (UnexpectedValueException $e) {
                // Not a canvas-synced folder, don't delete this!
                continue;
            }

            if (!array_key_exists($canvasId, $canvasIds)) {
                $this->log->notice("Deleting folder '{$folder->getName()}'");
                $this->countFoldersDeleted++;
                $this->folderMapper->deleteByColumn('id', $folder->getId());
            }
        }
    }
}
