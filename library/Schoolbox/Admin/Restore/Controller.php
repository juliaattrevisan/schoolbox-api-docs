<?php
/**
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Admin\Restore;

use Alaress\Web\Controller\Base;
use Psr\Log\NullLogger;
use Schoolbox\Mapper\RecursiveUndeleter;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;

class Controller extends Base
{
    private const PAGE_CURRENT = 'restore';

    /**
     * @var ErrorMapper
     */
    private $errorMapper;

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
     * @param ErrorMapper  $errorMapper
     * @param FolderMapper $folderMapper
     */
    public function __construct(
        ErrorMapper $errorMapper,
        FolderMapper $folderMapper,
        AssessmentMapper $assessmentMapper
    )
    {
        $this->errorMapper = $errorMapper;
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
        $start = \Alaress_Date_Format::newNaturalDate('-1 weeks');
        $end = \Alaress_Date_Format::now();

        $folderpb = $this->folderMapper->getPredicateBuilder();
        $deletedFolders = $this->folderMapper->findByPredicates($folderpb->betweenDates('deleted_at', $start, $end), ['deleted'=>true,'order'=>['deleted_at'=>'DESC']]);

        $assessmentpb = $this->assessmentMapper->getPredicateBuilder();
        $deletedAssessments = $this->assessmentMapper->findByPredicates($assessmentpb->betweenDates('deleted_at', $start, $end), ['deleted'=>true, 'order'=>['deleted_at'=>'DESC']]);

        $this->getResponse()->content->set($this->buildHtmlOutput('@restore/index.twig', [
            'pageCurrent' => self::PAGE_CURRENT,
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
        $recursiveUndeleter = new RecursiveUndeleter(new NullLogger(), 120);
        $recursiveUndeleter->findDeletedModels($this->folderMapper, [$this->getRequest()->post['id']]);
        $db = $this->folderMapper->getDatabase();
        $db->beginTransaction();
        try {
            $recursiveUndeleter->restoreSoftDeletedModels();
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            $this->sendError("Unable to restore models, rolling back.");
            exit(1);
        }

        $this->getResponse()->content->set($this->buildJsonOutput(['success'=>true]));
    }

    /**
     * Load $templateName
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function restoreAssessment()
    {
        $recursiveUndeleter = new RecursiveUndeleter(new NullLogger(), 120);
        $recursiveUndeleter->findDeletedModels($this->assessmentMapper, [$this->getRequest()->post['id']]);
        $db = $this->assessmentMapper->getDatabase();
        $db->beginTransaction();
        try {
            $recursiveUndeleter->restoreSoftDeletedModels();
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            $this->sendError("Unable to restore models, rolling back.");
            exit(1);
        }

        $this->getResponse()->content->set($this->buildJsonOutput(['success'=>true]));
    }
}
