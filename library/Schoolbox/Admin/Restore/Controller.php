<?php

/**
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Restore;

use Alaress\Web\Controller\Base;
use Psr\Log\NullLogger;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper;
use Schoolbox\Mapper\RecursiveUndeleter;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Code\Mapper as FolderCodeMapper;
use Schoolbox\Resource\Folder\Code\Model as FolderCode;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Assessment;
use Schoolbox\UserMapper;
use Schoolbox\User;

class Controller extends Base
{
    /**
     * Period of time that you can restore items, older than this will not be displayed
     */
    private const TIME_FRAME = '2week';
    private const VALID_TIME_FRAMES = [
        '1week',
        '2week',
        '1month',
        '3month'
    ];

    /**
     * Recursively undelete all children if they are deleted within this timeframe of the parent in seconds
     */
    public const RESTORE_WINDOW = '120';

    private array $folderParent = [];

    /**
     * Main Restore page
     *
     * find all the deleted things
     *
     */
    public function getIndex()
    {
        /**
         * validate the time range
         */
        $startRange = self::TIME_FRAME;
        if (in_array($this->requestQuery->get('restoreRange'), self::VALID_TIME_FRAMES, true)) {
            $startRange = $this->requestQuery->get('restoreRange');
        }

        //Start Range
        $start = \Alaress_Date_Format::newNaturalDate(sprintf("-%s", $startRange));
        $end = \Alaress_Date_Format::now();

        // Folders
        $folderpb = $this->folderMapper->getPredicateBuilder();

        $deletedFolders = $this->folderMapper->findByPredicates($folderpb->betweenDates('deleted_at', $start, $end), ['deleted' => true,'order' => ['deleted_at' => 'DESC']]);

        // fetch folder codes to pair with the deleted folders (codes will be deleted at the same time)
        $folderCodepb = $this->folderCodeMapper->getPredicateBuilder();
        $deletedFolderCodes = $this->folderCodeMapper->findByPredicates($folderCodepb->betweenDates('deleted_at', $start, $end), ['deleted' => true,'order' => ['deleted_at' => 'DESC']]);

        // Light weight cache of all the active folder codes
        $activeFolderCodesQuery = "
            SELECT code
            FROM folder_code
            WHERE deleted_at IS NULL";
        $activeFolderCodes = $this->folderCodeMapper->getDatabase()->executeQuery($activeFolderCodesQuery)->fetchColumnArray('code');

        /**
         * filter out folders that have:
         * * folder codes that are in use
         * * parent folders that are deleted
         *
         * this is a single filter, to try and reduce complexity
         */
        $deletedFolders = $deletedFolders->filter(function (Folder $folderNode) use ($activeFolderCodes, $deletedFolderCodes) {

            // get the codes for this folder
            $folderCodes = $deletedFolderCodes->filter(function (FolderCode $folderCodesNode) use ($folderNode) {
                return $folderCodesNode->getFolder()->getId() === $folderNode->getId();
            });

            // deleted codes, need us to set the code so we can display it
            $folderNode->setCodes($folderCodes);

            // complete the filter checks, all MUST be true
            return
                // if the folder codes are already in use, we can't restore it
                count(array_intersect($folderCodes->getPropertyValues('code'), $activeFolderCodes)) === 0
                &&
                // Filter folders where the parent is deleted, these cannot be restored
                // due to hydration issues, we need to fetch the parent
                !$this->getParent($folderNode->getParent()->getId())->isDeleted();
        });

        // we don't need the folder caches any more, clear them to help with memory
        unset($this->folderParent, $activeFolderCodes, $deletedFolderCodes);

        // Assessments
        $assessmentpb = $this->assessmentMapper->getPredicateBuilder();
        $deletedAssessments = $this->assessmentMapper->findByPredicates($assessmentpb->betweenDates('deleted_at', $start, $end), ['deleted' => true, 'order' => ['deleted_at' => 'DESC']]);

        // Filter assessments where the context is deleted, these cannot be restored
        $deletedAssessments = $deletedAssessments->filter(function (Assessment\DataModel\Model $node) {
            /* ensure the context actually exists...
             * if it doesn't then just return false
             * we can't just nullcoalesce, as we are inverting the isDeleted call, so we would attempt to show things that had no parent.
             */
            if ($node->getAssessmentContext()) {
                return !$node->getAssessmentContext()->isDeleted();
            }
            return false;
        });

        // Users
        $userspb = $this->userMapper->getPredicateBuilder();

        $deletedUsers = $this->userMapper->findByPredicates($userspb->betweenDates('deleted_at', $start, $end), ['deleted' => true,'order' => ['deleted_at' => 'DESC']]);

        // Grab a lightweight cache of all the active usernames
        $activeUsernamesQuery = "
            SELECT username
            FROM user
            WHERE deleted_at IS NULL";
        $activeUsernamesQuery = $this->userMapper->getDatabase()->executeQuery($activeUsernamesQuery)->fetchColumnArray('username');

        // Filter users where the username is already being used
        $deletedUsers = $deletedUsers->filter(function (User $node) use ($activeUsernamesQuery) {
            return !in_array($node->getUsername(), $activeUsernamesQuery, true);
        });

        // Courses
        // spec
        $deletedCourseSpec = $this->getDeletdFolderContent($this->courseSpecMapper, $start, $end);
        // impl
        $deletedCourseImpl = $this->getDeletdFolderContent($this->courseImplMapper, $start, $end);

        // Units
        // spec
        $unitSpecpb = $this->unitSpecMapper->getPredicateBuilder();

        $deletedUnitSpec = $this->unitSpecMapper->findByPredicates($unitSpecpb->betweenDates('deleted_at', $start, $end), ['deleted' => true,'order' => ['deleted_at' => 'DESC']]);

        // Filter Units where the parent (folder and course) are deleted, these cannot be restored
        $deletedUnitSpec = $deletedUnitSpec->filter(function (UnitSpec\Model $node) {
            return !$node->getCourse()->isDeleted() && !$node->getFolder()->isDeleted();
        });

        // impl
        $unitImplpb = $this->unitImplMapper->getPredicateBuilder();

        $deletedUnitImpl = $this->unitImplMapper->findByPredicates($unitImplpb->betweenDates('deleted_at', $start, $end), ['deleted' => true,'order' => ['deleted_at' => 'DESC']]);

        // Filter Units where the parent (folder and course) are deleted, these cannot be restored
        $deletedUnitImpl = $deletedUnitImpl->filter(function (UnitImpl\Model $node) {
            return !$node->getCourse()->isDeleted() && !$node->getFolder()->isDeleted();
        });

        // Files
        $deletedFiles = $this->getDeletdFolderContent($this->fileMapper, $start, $end);

        $this->response = $this->responseFromTemplate('@restore/index.twig', [
            'pageCurrent'        => $this->routeGenerator->generate('restore.getIndex'),
            'allBreadcrumbs'     => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('restore.getIndex')),
            'deletedFolders'     => $deletedFolders,
            'deletedAssessments' => $deletedAssessments,
            'assessmentTypes'    => Assessment\AssessmentType::getOptions(
                $this->getConfigLoader()->get(ConfigMapper::NAME_LTI_ASSESSMENTS_ENABLE),
            ),
            'deletedUsers'       => $deletedUsers,
            'deletedFiles'       => $deletedFiles,

            // course/unit
            'deletedCourseSpec'  => $deletedCourseSpec,
            'deletedCourseImpl'  => $deletedCourseImpl,
            'deletedUnitSpec'    => $deletedUnitSpec,
            'deletedUnitImpl'    => $deletedUnitImpl,
            'restoreRange'       => $startRange,
        ]);
    }

    /**
     * Controller constructor.
     *
     * @param FolderMapper                  $folderMapper
     * @param FolderCodeMapper              $folderCodeMapper
     * @param Assessment\DataModel\Mapper   $assessmentMapper
     * @param UserMapper                    $userMapper
     * @param FileMapper                    $fileMapper
     * @param CourseSpec\Mapper             $courseSpecMapper
     * @param UnitSpec\Mapper               $unitSpecMapper
     * @param CourseImpl\Mapper             $courseImplMapper
     * @param UnitImpl\Mapper               $unitImplMapper
     */
    public function __construct(
        private FolderMapper $folderMapper,
        private FolderCodeMapper $folderCodeMapper,
        private Assessment\DataModel\Mapper $assessmentMapper,
        private UserMapper $userMapper,
        private FileMapper $fileMapper,
        private CourseSpec\Mapper $courseSpecMapper,
        private UnitSpec\Mapper $unitSpecMapper,
        private CourseImpl\Mapper $courseImplMapper,
        private UnitImpl\Mapper $unitImplMapper,
    ) {
    }

    /**
     * Restore Folder
     *
     * @api POST /adminv2/restore/folder
     */
    public function restoreFolder()
    {
        $this->restore($this->folderMapper);
    }

    /**
     * Restore Assessment
     *
     * @api POST /adminv2/restore/assessment
     */
    public function restoreAssessment()
    {
        $this->restore($this->assessmentMapper);
    }

    /**
     * Restore User
     *
     * @since 25.0
     * @api POST /adminv2/restore/user
     */
    public function restoreUser()
    {
        $this->restore($this->userMapper);
    }

    /**
     * Restore File
     *
     * @since 25.0
     * @api POST /adminv2/restore/file
     */
    public function restoreFile()
    {
        $this->restore($this->fileMapper);
    }

    /**
     * Restore CourseSpec
     *
     * @since 25.0
     * @api POST /adminv2/restore/courseSpec
     */
    public function restoreCourseSpec()
    {
        $this->restore($this->courseSpecMapper);
    }

    /**
     * Restore CourseImpl
     *
     * @since 25.0
     * @api POST /adminv2/restore/courseImpl
     */
    public function restoreCourseImpl()
    {
        $this->restore($this->courseImplMapper);
    }

    /**
     * Restore UnitSpec
     *
     * @since 25.0
     * @api POST /adminv2/restore/unitSpec
     */
    public function restoreUnitSpec()
    {
        $this->restore($this->unitSpecMapper);
    }

    /**
     * Restore UnitImpl
     *
     * @since 25.0
     * @api POST /adminv2/restore/unitImpl
     */
    public function restoreUnitImpl()
    {
        $this->restore($this->unitImplMapper);
    }


    //------------------------------------------------------------------------------------------------------------------
    //------------------------------------------   Helper Functions   --------------------------------------------------
    /**
     * Fetches and filters objects that live on a folder
     *
     * @param Mapper $mapper // what mapper we are restoring the initial object from
     * @param \Alaress_Date_Format $start // when to start searching for deleted objects
     * @param \Alaress_Date_Format $end // when to stop searching for deleted objects
     * @return mixed
     */
    private function getDeletdFolderContent(Mapper $mapper, \Alaress_Date_Format $start, \Alaress_Date_Format $end)
    {
        $pb = $mapper->getPredicateBuilder();
        $deletedNodes = $mapper->findByPredicates($pb->betweenDates('deleted_at', $start, $end), ['deleted' => true,'order' => ['deleted_at' => 'DESC']]);

        // Filter nodes where the folder is deleted, these cannot be restored
        $deletedNodes = $deletedNodes->filter(function ($node) {
            return !$node->getFolder()->isDeleted();
        });
        return $deletedNodes;
    }

    /**
     * Recursively restore the object and all its children
     * also handles the $this->response
     *
     * @param Mapper $mapper // what mapper we are restoring the initial object from
     * @return void
     * @throws \Schoolbox\Mapper\Exception
     */
    private function restore(Mapper $mapper)
    {
        $recursiveUndeleter = new RecursiveUndeleter(new NullLogger(), self::RESTORE_WINDOW);
        $modelByIdByMapperClass = $recursiveUndeleter->findDeletedModels($mapper, [$this->requestPost['id']]);
        $db = $mapper->getDatabase();
        $db->beginTransaction();
        try {
            $recursiveUndeleter->restoreSoftDeletedModels($modelByIdByMapperClass);
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $this->generateException(ErrorMapper::ERROR_PROCESSING, $e, "Unable to restore objects, rolling back.");
        }

        $db->commit();
        $this->response = $this->responseAsJson(['success' => true]);
    }

    /**
     * Due to folders are hydrated, they may not get the parent's deleted check correct, lets do that here...
     * whilst caching records
     *
     * @param int $id
     * @return \Schoolbox\ISoftDeletable|Folder
     * @throws Mapper\Exception
     */
    private function getParent(int $id)
    {
        // if the parent is not in the cache, fetch it
        if (!isset($this->folderParent[$id])) {
            try {
                $this->folderParent[$id] = $this->folderMapper->findById($id);
            } catch (Mapper\Exception\Deleted $e) {
                // we still need the deleted parent...
                $this->folderParent[$id] = $e->getDeletedObject();
            }
        }
        // return the hydrated parent
        return $this->folderParent[$id];
    }
}
