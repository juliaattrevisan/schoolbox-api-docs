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
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\RecursiveUndeleter;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Assessment\DataModel\Model as Assessment;

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
     * @var AssessmentMapper
     */
    private $assessmentMapper;

    /**
     * @var FolderMapper
     */
    private $folderMapper;


    /**
     * Controller constructor.
     *
     * @param FolderMapper       $folderMapper
     * @param AssessmentMapper   $assessmentMapper
     */
    public function __construct(
        FolderMapper $folderMapper,
        AssessmentMapper $assessmentMapper
    ) {
        $this->folderMapper = $folderMapper;
        $this->assessmentMapper = $assessmentMapper;
    }

    /**
     * Load $templateName
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
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
        $deletedAssessments = $deletedAssessments->filter(function (Assessment $node) {
            return !$node->getAssessmentContext()->isDeleted();
        });

        $this->getResponse()->content->set($this->buildHtmlOutput('@restore/index.twig', [
            'pageCurrent' => $this->router->generate('restore.getIndex'),
            'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('restore.getIndex')),
            'deletedFolders' => $deletedFolders,
            'deletedAssessments' => $deletedAssessments,
        ]));
    }

    /**
     * Load $templateName
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function restoreFolder()
    {
        $recursiveUndeleter = new RecursiveUndeleter(new NullLogger(), self::RESTORE_WINDOW);
        $recursiveUndeleter->findDeletedModels($this->folderMapper, [$this->getRequest()->post['id']]);
        $db = $this->folderMapper->getDatabase();
        $db->beginTransaction();
        try {
            $recursiveUndeleter->restoreSoftDeletedModels();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $this->generateException(ErrorMapper::ERROR_PROCESSING, $e, "Unable to restore objects, rolling back.");
        }

        $db->commit();
        $this->getResponse()->content->set($this->buildJsonOutput(['success' => true]));
    }

    /**
     * Load $templateName
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function restoreAssessment()
    {
        $recursiveUndeleter = new RecursiveUndeleter(new NullLogger(), self::RESTORE_WINDOW);
        $recursiveUndeleter->findDeletedModels($this->assessmentMapper, [$this->getRequest()->post['id']]);
        $db = $this->assessmentMapper->getDatabase();
        $db->beginTransaction();
        try {
            $recursiveUndeleter->restoreSoftDeletedModels();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $this->generateException(ErrorMapper::ERROR_PROCESSING, $e, "Unable to restore objects, rolling back.");
        }

        $db->commit();
        $this->getResponse()->content->set($this->buildJsonOutput(['success' => true]));
    }
}
