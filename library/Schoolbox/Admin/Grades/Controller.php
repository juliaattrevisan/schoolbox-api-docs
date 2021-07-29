<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Grades;

use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel;
use Schoolbox\Collection;

class Controller extends Base
{
    /**
     * @var GradeLevel\Mapper
     */
    private $gradeLevelMapper;

    /**
     * @var GradeLevel\Group\Mapper
     */
    private $gradeLevelGroupMapper;

    /**
     * Constructor
     *
     * @param GradeLevel\Mapper       $gradeLevelMapper
     * @param GradeLevel\Group\Mapper $gradeLevelGroupMapper
     */
    public function __construct(GradeLevel\Mapper $gradeLevelMapper, GradeLevel\Group\Mapper $gradeLevelGroupMapper)
    {
        $this->gradeLevelMapper      = $gradeLevelMapper;
        $this->gradeLevelGroupMapper = $gradeLevelGroupMapper;
    }

    /**
     * Get index for modify grades form.
     *
     * @api   GET /adminv2/grades
     * @since 20.2
     */
    public function getIndex(): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@adminGrades/index.twig',
                [
                    'pageCurrent'    => $this->router->generate('adminGrades.getIndex'),
                    'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminGrades.getIndex')),
                    'gradeGroups'    => $this->retrieveAllGradeGroups(),
                ]
            )
        );
    }

    /**
     * @param GradeLevel\Group\Model $gradeGroup
     * @api   DELETE /adminv2/grades/group/{groupId}
     * @route adminGrades.deleteGradeGroup
     */
    public function deleteGradeGroup(GradeLevel\Group\Model $gradeGroup): void
    {
        /**
         * don't forget to track those changes!
         */
        $gradeGroup->setUpdatedBy($this->getUser());

        /**
         * its important to remove any grade levels before the main grade group;
         * so that they are not left in an orphaned state
         */
        $this->gradeLevelMapper->delete($gradeGroup->getGradeLevels());
        $this->gradeLevelGroupMapper->delete($gradeGroup);

        $this->response->content->set(
            $this->buildJsonOutput($this->retrieveAllGradeGroups())
        );
    }

    public function deleteGradeLevel(GradeLevel\Model $gradeLevel): void
    {
        /**
         * don't forget to track those changes!
         */
        $gradeLevel->setUpdatedBy($this->getUser());

        $this->gradeLevelMapper->delete($gradeLevel);

        $this->response->content->set(
            $this->buildJsonOutput($this->retrieveAllGradeGroups())
        );
    }

    /**
     * Modify admin grades.
     *
     * @api   POST /adminv2/grades
     * @since 20.2
     */
    public function postSaveGrades(): void
    {
        // Ignore the return type: this is an array of stdclass objects
        /** @var iterable&\stdClass[] $payload */
        $payload          = $this->request->content->get();
        $gradeLevelGroups = $this->gradeLevelGroupMapper->createCollection();

        foreach ($payload as $gradeLevelGroupPayload) {
            // Convert this to a grade group and its levels
            $gradeLevelGroup = $this->gradeLevelGroupMapper
                ->createModel()
                ->setId($gradeLevelGroupPayload->id)
                ->setName($gradeLevelGroupPayload->name)
                ->setUpdatedBy($this->getUser())
                ->setGradeLevels($this->gradeLevelMapper->createCollection());

            foreach ($gradeLevelGroupPayload->levels as $gradeLevelPayload) {
                $gradeLevel = $this->gradeLevelMapper
                    ->createModel()
                    ->setId($gradeLevelPayload->id)
                    ->setGroup($gradeLevelGroup)
                    ->setRawGrade((float)$gradeLevelPayload->rawGrade)
                    ->setLowerBound((float)$gradeLevelPayload->lowerBound)
                    ->setUpdatedBy($this->getUser())
                    ->setCaption($gradeLevelPayload->caption);
                $gradeLevelGroup->getGradeLevels()->add($gradeLevel);
            }

            $gradeLevelGroups->add($gradeLevelGroup);
        }

        // Save all of the grade groups
        foreach ($gradeLevelGroups as $gradeLevelGroup) {
            /** @var GradeLevel\Group\Model $gradeLevelGroup */
            $this->gradeLevelGroupMapper->save($gradeLevelGroup);
            $this->gradeLevelMapper->save($gradeLevelGroup->getGradeLevels());
        }

        $this->response->content->set(
            $this->buildJsonOutput($this->retrieveAllGradeGroups())
        );
    }

    /**
     * Helper method for returning all grade groups, and translating them to
     * something that can be output in a JSON response.
     *
     * @return Collection|array[]
     */
    private function retrieveAllGradeGroups(): Collection
    {
        // Workaround for cache of both grade level group and grade level
        $gradeLevelGroups = $this->gradeLevelGroupMapper->findAll(['cache' => false]);
        foreach ($gradeLevelGroups as $gradeLevelGroup) {
            $gradeLevelGroup->setGradeLevels(
                $this->gradeLevelMapper
                    ->findByColumn('submission_grade_group_id', $gradeLevelGroup->getId(), ['cache' => false])
                    ->sortBy('lowerBound', 'asc')
            );
        }
        return $gradeLevelGroups->map(new Translator\GradeGroup($this->router, $this->authorizer, $this->getUser()));
    }
}
