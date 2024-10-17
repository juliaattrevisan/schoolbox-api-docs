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
use Schoolbox\Mapper\Exception;

class Controller extends Base
{
    /**
     * @var GradeLevel\Mapper
     */
    private GradeLevel\Mapper $gradeLevelMapper;

    /**
     * @var GradeLevel\Group\Mapper
     */
    private GradeLevel\Group\Mapper $gradeLevelGroupMapper;

    /**
     * Constructor
     *
     * @param GradeLevel\Mapper       $gradeLevelMapper
     * @param GradeLevel\Group\Mapper $gradeLevelGroupMapper
     */
    public function __construct(GradeLevel\Mapper $gradeLevelMapper, GradeLevel\Group\Mapper $gradeLevelGroupMapper)
    {
        $this->gradeLevelGroupMapper = $gradeLevelGroupMapper;
        $this->gradeLevelMapper      = $gradeLevelMapper;
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
        $this->response = $this->responseFromTemplate(
            '@adminGrades/index.twig',
            [
                'pageCurrent'    => $this->routeGenerator->generate('adminGrades.getIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getAuthorizer(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminGrades.getIndex')),
                'gradeGroups'    => $this->retrieveAllGradeGroups(),
            ]
        );
    }

    /**
     * Delete a Grade Group and it's associated Grade Levels
     *
     * @param GradeLevel\Group\Model $gradeGroup
     * @throws Exception
     * @api   DELETE /grades/group/{groupId:\d+}
     */
    public function deleteGradeGroup(GradeLevel\Group\Model $gradeGroup): void
    {
        /**
         * track who performed this update against the group
         */
        $gradeGroup->setUpdatedBy($this->getUser());
        $this->gradeLevelGroupMapper->save($gradeGroup);

        /**
         * track who performed this update against all the levels
         */
        $updatedGradeLevels = $this->gradeLevelMapper->createCollection();
        foreach ($gradeGroup->getGradeLevels() as $gradeLevel) {
            $gradeLevel->setUpdatedBy($this->getUser());
            $updatedGradeLevels->add($gradeLevel);
        }
        $this->gradeLevelMapper->save($updatedGradeLevels);

        /**
         * it's important to remove any grade levels before the main grade group
         * so that they are not left in an orphaned state
         */
        $this->gradeLevelMapper->delete($gradeGroup->getGradeLevels());
        $this->gradeLevelGroupMapper->delete($gradeGroup);

        $this->response = $this->responseAsJson($this->retrieveAllGradeGroups());
    }

    /**
     * Delete a single Grade Level
     *
     * @param GradeLevel\Model $gradeLevel
     * @throws Exception
     * @api   DELETE /grades/level/{levelId:\d+}
     */
    public function deleteGradeLevel(GradeLevel\Model $gradeLevel): void
    {
        /**
         * don't forget to track those changes!
         */
        $gradeLevel->setUpdatedBy($this->getUser());
        $this->gradeLevelMapper->save($gradeLevel);

        $this->gradeLevelMapper->delete($gradeLevel);

        $this->response = $this->responseAsJson($this->retrieveAllGradeGroups());
    }

    /**
     * Modify admin grades.
     *
     * @throws Exception
     * @since 20.2
     * @api   POST /adminv2/grades
     */
    public function postSaveGrades(): void
    {
        $payload          = $this->request->getParsedBody();
        $gradeLevelGroups = $this->gradeLevelGroupMapper->createCollection();

        foreach ($payload as $gradeLevelGroupPayload) {
            // Convert this to a grade group and its levels
            $gradeLevelGroup = $this->gradeLevelGroupMapper
                ->createModel()
                ->setId($gradeLevelGroupPayload['id'])
                ->setName($gradeLevelGroupPayload['name'])
                ->setUpdatedBy($this->getUser())
                ->setGradeLevels($this->gradeLevelMapper->createCollection());

            foreach ($gradeLevelGroupPayload['levels'] as $gradeLevelPayload) {
                $gradeLevel = $this->gradeLevelMapper
                    ->createModel()
                    ->setId($gradeLevelPayload['id'])
                    ->setGroup($gradeLevelGroup)
                    ->setRawGrade((float)$gradeLevelPayload['rawGrade'])
                    ->setLowerBound((float)$gradeLevelPayload['lowerBound'])
                    ->setUpdatedBy($this->getUser())
                    ->setCaption($gradeLevelPayload['caption']);
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

        $this->response = $this->responseAsJson($this->retrieveAllGradeGroups());
    }

    /**
     * Helper method for returning all grade groups, and translating them to
     * something that can be output in a JSON response.
     *
     * @return Collection|array[]
     */
    private function retrieveAllGradeGroups(): Collection
    {
        /**
         * disable caching on Grade Group and Grade Level to ensure we always have fresh content
         */
        $gradeLevelGroups = $this->gradeLevelGroupMapper->findAll(['cache' => false]);
        foreach ($gradeLevelGroups as $gradeLevelGroup) {
            $gradeLevelGroup->setGradeLevels(
                $this->gradeLevelMapper
                    ->findByColumn('submission_grade_group_id', $gradeLevelGroup->getId(), ['cache' => false])
                    ->sortBy('lowerBound', 'asc')
            );
        }
        return $gradeLevelGroups->map(new Translator\GradeGroup($this->routeGenerator, $this->authorizer, $this->getUser()));
    }
}
