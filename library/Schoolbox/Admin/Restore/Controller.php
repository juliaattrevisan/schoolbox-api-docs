<?php

/**
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Restore;

use Alaress\Web\Controller\Base;
use Psr\Log\NullLogger;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\RecursiveUndeleter;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Learning\Assessment;

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
     */
    public function __construct(
        private FolderMapper $folderMapper,
        private Assessment\DataModel\Mapper $assessmentMapper
    ) {
    }

    /**
     * Load $templateName
     *
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    public function getIndex()
    {
        //Start Range
        $start = \Alaress_Date_Format::newNaturalDate(self::TIME_FRAME);
        $end = \Alaress_Date_Format::now();

        $folderpb = $this->folderMapper->getPredicateBuilder();

        $deletedFolders = $this->folderMapper->findByPredicates($folderpb->betweenDates('deleted_at', $start, $end), ['deleted' => true,'order' => ['deleted_at' => 'DESC']]);

        // Filter folders where the parent is deleted, these cannot be restored
        $deletedFolders = $deletedFolders->filter(function (Folder $node) {
            return !$node->getParent()->isDeleted();
        });

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

        $this->response = $this->responseFromTemplate('@restore/index.twig', [
            'pageCurrent'        => $this->routeGenerator->generate('restore.getIndex'),
            'allBreadcrumbs'     => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('restore.getIndex')),
            'deletedFolders'     => $deletedFolders,
            'deletedAssessments' => $deletedAssessments,
            'assessmentTypes'    => Assessment\AssessmentType::getOptions(
                $this->getConfigLoader()->get(ConfigMapper::NAME_LTI_ASSESSMENTS_ENABLE),
            ),
        ]);
    }

    /**
     * Load $templateName
     *
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
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
     * Load $templateName
     *
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
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
}
