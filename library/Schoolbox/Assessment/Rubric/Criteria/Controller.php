<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Criteria;

use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Assessment\Rubric\RowSet\Factory;
use Schoolbox\Assessment\Rubric\RowSet\RowSet;
use Schoolbox\Curriculum\Node\Mapper as NodeMapper;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Controller for the rubric criteria management form.
 */
class Controller extends BaseController
{
    /**
     * @var NodeMapper
     */
    private $nodeMapper;


    /**
     * @var RubricMapper
     */
    private $rubricMapper;


    /**
     * @var RosettaStone
     */
    private $rosettaStone;


    /**
     * Constructor
     *
     * @param NodeMapper   $nodeMapper
     * @param RubricMapper $rubricMapper
     * @param RosettaStone $rosettaStone
     */
    public function __construct(
        NodeMapper $nodeMapper,
        RubricMapper $rubricMapper,
        RosettaStone $rosettaStone
    ) {
        $this->nodeMapper   = $nodeMapper;
        $this->rubricMapper = $rubricMapper;
        $this->rosettaStone = $rosettaStone;
    }


    /**
     * View the rubric criteria form for a folder.
     *
     * @api   GET /rubric/criteria/{folderId}
     * @level 181
     * @param FolderModel $folder
     */
    public function getForm(FolderModel $folder): void
    {
        $this->getResponse()->content->set(
            $this
                ->buildHtmlOutput(
                    '@rubricCriteria/form.twig',
                    $this->buildTwigDataForForm($this->rubricMapper->retrieveRowSet($folder, true))
                )
                ->setTitle(
                    sprintf(
                        'Manage Rubric %s for %s',
                        $this->rosettaStone->translate('rubricGroup', true, true),
                        $folder->getName()
                    )
                )
        );
    }


    /**
     * Save the rubric criteria for a folder.
     *
     * @api   POST /rubric/criteria/{folderId}
     * @level 181
     * @param FolderModel $folder
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function postForm(FolderModel $folder): void
    {
        // Build a new rowset, and persist it
        $newRowSet = (new Factory($this->nodeMapper))->buildFromPostData(
            $folder,
            $this->request->post->get('crits', [])
        );

        // Done: redirect back to homepage, or AJAX
        if ($this->request->isXhr()) {
            try {
                $this->rubricMapper->persistRowSet($newRowSet, $this->isCurriculumTaggingEnabled(), $folder->isSpecFolder());

                $this->response->content->set(
                    $this->buildJsonOutput([
                        'status'  => 200,
                        'message' => 'Criteria updated',
                        'redirect' => '/homepage/' . $folder->getId()
                    ])
                );

            }
            catch (\UnexpectedValueException $e) {
                $this->response->content->set(
                    $this->buildJsonOutput([
                        'status'  => 400,
                        'message' => $e->getMessage()
                    ])
                );
            }
        }
    }


    /**
     * Build Twig data for the form.
     *
     * @param RowSet $rowSet
     * @return array
     */
    private function buildTwigDataForForm(RowSet $rowSet): array
    {
        $folder   = $rowSet->getContext()->getBaseContext()->getFolder();
        $groupIds = $rowSet->retrieveCapabilityIds();
        $critIds  = $rowSet->retrieveIndicatorIds();
        return [
            'folder'                   => $folder,
            'rowSet'                   => $rowSet,
            'maxGroupId'               => !empty($groupIds) ? max($groupIds) + 1 : 1,
            'maxCritId'                => !empty($critIds)  ? max($critIds)  + 1 : 1,
            'curriculumTaggingEnabled' => $this->isCurriculumTaggingEnabled(),
            // If the folder is a spec folder: capabilities and indicators that
            // are in use are allowed to be modified.
            // This is because spec folders can't/shouldn't contain marked
            // assessments, and changing caps/inds in use would invalidate all
            // marks given.
            'allowModifyUsed'          => $folder->isSpecFolder(),
        ];
    }


    /**
     * Is curriculum tagging enabled?
     *
     * If not: don't save curriculum nodes, or show them in the form.
     *
     * @return bool
     */
    private function isCurriculumTaggingEnabled(): bool
    {
        return (bool)$this->getConfigLoader()->get('curriculum_tagging_enabled');
    }
}
