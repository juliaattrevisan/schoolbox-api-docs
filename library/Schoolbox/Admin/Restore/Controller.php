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
use Schoolbox\Mapper\RecursiveUndeleter;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\File\Model as File;
use Schoolbox\Learning\Assessment;
use Schoolbox\UserMapper;

class Controller extends Base
{
    /**
     * Period of time that you can restore items, older than this will not be displayed
     */
    private const TIME_FRAME = '-1 weeks';

    /**
     * Recursively undelete all children if they are deleted within this timeframe of the parent in seconds
     */
    public const RESTORE_WINDOW = '120';

    /**
     * Controller constructor.
     *
     * @param FolderMapper                $folderMapper
     * @param Assessment\DataModel\Mapper $assessmentMapper
     * @param UserMapper $userMapper
     * @param FileMapper $fileMapper
     */
    public function __construct(
        private FolderMapper $folderMapper,
        private Assessment\DataModel\Mapper $assessmentMapper,
        private UserMapper $userMapper,
        private FileMapper $fileMapper
    ) {
    }

    /**
     * Main Restore page
     *
     * find all the deleted things
     *
     */
    public function getIndex()
    {
        //Start Range
        $start = \Alaress_Date_Format::newNaturalDate(self::TIME_FRAME);
        $end = \Alaress_Date_Format::now();

        // Folders
        $folderpb = $this->folderMapper->getPredicateBuilder();

        $deletedFolders = $this->folderMapper->findByPredicates($folderpb->betweenDates('deleted_at', $start, $end), ['deleted' => true,'order' => ['deleted_at' => 'DESC']]);

        // Filter folders where the parent is deleted, these cannot be restored
        $deletedFolders = $deletedFolders->filter(function (Folder $node) {
            return !$node->getParent()->isDeleted();
        });

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

        // Courses

        // Units

        // Files
        $filepb = $this->fileMapper->getPredicateBuilder();

        $deletedFiles = $this->fileMapper->findByPredicates($filepb->betweenDates('deleted_at', $start, $end), ['deleted' => true,'order' => ['deleted_at' => 'DESC']]);

        // Filter folders where the parent is deleted, these cannot be restored
        $deletedFiles = $deletedFiles->filter(function (File $node) {
            return !$node->getFolder()->isDeleted();
        });

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
        ]);
    }

    /**
     * Restore Folder
     *
     * @api POST /adminv2/restore/folder
     */
    public function restoreFolder()
    {
        $recursiveUndeleter = new RecursiveUndeleter(new NullLogger(), self::RESTORE_WINDOW);
        $modelByIdByMapperClass = $recursiveUndeleter->findDeletedModels($this->folderMapper, [$this->requestPost['id']]);
        $db = $this->folderMapper->getDatabase();
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
     * Restore Assessment
     *
     * @api POST /adminv2/restore/assessment
     */
    public function restoreAssessment()
    {
        $recursiveUndeleter = new RecursiveUndeleter(new NullLogger(), self::RESTORE_WINDOW);
        $modelByIdByMapperClass = $recursiveUndeleter->findDeletedModels($this->assessmentMapper, [$this->requestPost['id']]);
        $db = $this->assessmentMapper->getDatabase();
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
     * Restore User
     *
     * @since 25.0
     * @api POST /adminv2/restore/user
     */
    public function restoreUser()
    {
        $recursiveUndeleter = new RecursiveUndeleter(new NullLogger(), self::RESTORE_WINDOW);
        $modelByIdByMapperClass = $recursiveUndeleter->findDeletedModels($this->userMapper, [$this->requestPost['id']]);
        $db = $this->userMapper->getDatabase();
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
     * Restore File
     *
     * @since 25.0
     * @api POST /adminv2/restore/file
     */
    public function restoreFile()
    {
        $recursiveUndeleter = new RecursiveUndeleter(new NullLogger(), self::RESTORE_WINDOW);
        $modelByIdByMapperClass = $recursiveUndeleter->findDeletedModels($this->fileMapper, [$this->requestPost['id']]);
        $db = $this->fileMapper->getDatabase();
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
}
