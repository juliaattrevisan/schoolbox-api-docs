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
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UserMapper;

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
     */
    public function postForm(FolderModel $folder): void
    {
        // Build a new rowset, and persist it
        $newRowSet = (new Factory($this->nodeMapper))->buildFromPostData(
            $folder,
            $this->request->post->get('crits', [])
        );

        // If we are modifying the rubric on a course...
        if ($folder->hasCourseSpecification()) {
            // Find its units. Folder's units aren't hydrated here, so we have to use the mapper.
            $unitsForCourse = UserMapper::getInstance()->findAllByCourseFolderId($folder->getId());

            // Get the capabilities for each unit in this course
            $unitCapabilities = [];
            foreach ($unitsForCourse as $unit) {
                $rowSetForUnit = $this->rubricMapper->retrieveRowSet($unit->getFolder(), false);

                foreach ($rowSetForUnit->getCapabilities() as $unitCapability) {
                    $unitCapabilities[$unit->getFolder()->getId()] = $unitCapability->getName();
                }
            }

            // Get the capabilities for this course
            $courseCapabilities = $newRowSet->getCapabilities();

            // Compare capabilities in the course to ones in the unit
            foreach ($courseCapabilities as $courseCapability) {
                // If we validate all capabilities indiscriminately, existing legacy data from before this validation
                // may prevent users from modifying their rubric at all, so make sure we only validate new caps.
                // If capability is newly added, it will have no ID.
                $isNewCapability = null !== $courseCapability->getId();

                // If capability has no indicators, it will be deleted automatically, so we don't need to dedupe it.
                // TODO this is returning indicators even when debug data shows there are none
                $hasIndicators = !empty($courseCapability->getIndicators());

                // Does a capability with this name already exist in a unit?
                $unitCapabilityNames = array_values($unitCapabilities);
                $courseCapabilityName = $courseCapability->getName();
                $isDuplicate =  \in_array($courseCapabilityName, $unitCapabilityNames, false);

                if ($isNewCapability && $hasIndicators && $isDuplicate) {
                    throw new \UnexpectedValueException("Cannot add capability " . $courseCapabilityName . " because it already exists in one or more units in this course");
                }
            }
        }

        $this->rubricMapper->persistRowSet($newRowSet, $this->isCurriculumTaggingEnabled(), $folder->isSpecFolder());

        // Done: redirect back to homepage, or AJAX
        if ($this->request->isXhr()) {
            $this->response->content->set(
                $this->buildJsonOutput([
                    'status'  => 200,
                    'message' => 'Criteria updated',
                ])
            );

        } else {
            $this->response->redirect->to('/homepage/' . $folder->getId());
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
