<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\LessonPlan\Template;

use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Role;

class Controller extends Base
{
    /**
     * An arbitrarily chosen prefix for new Field rows in the
     * create/update screens, which distinguishes new rows
     * from pre-existing ones since the ui lets you spawn new
     * rows and mix-and-match them with existing ones in some
     * arbitrary user-chosen order.
     */
    private const NEW_KEYWORD = 'new';


    /**
     * Controller constructor.
     *
     * @param ErrorMapper  $errorMapper
     * @param Mapper       $templateMapper
     * @param Field\Mapper $fieldMapper
     */
    public function __construct(
        private ErrorMapper $errorMapper,
        private Mapper $templateMapper,
        private Field\Mapper $fieldMapper
    ) {
    }


    /**
     * Provide form
     */
    public function getList(): void
    {
        $this->response = $this->responseFromTemplate(
            '@adminLessonPlanTemplate/list.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminLessonPlanTemplate.getList'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getAuthorizer(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminLessonPlanTemplate.getList')),
                'lessonPlanTemplates' => $this->templateMapper->findAll()->sortNaturally('title')
            ]
        );
    }


    /**
     * Create a new lesson plan template
     *
     * @return void
     * @throws RouteNotFound
     */
    public function getCreate(): void
    {
        $this->response = $this->responseFromTemplate(
            '@adminLessonPlanTemplate/create.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminLessonPlanTemplate.getList'),
                'allBreadcrumbs' => array_merge(
                    (new Subnav($this->getConfigLoader(), $this->getAuthorizer(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminLessonPlanTemplate.getList')),
                    [['Create', null]]
                ),
                'lessonPlanTemplate' => null,
                'studentRole' => Role::STUDENT,
                'newKeyword' => self::NEW_KEYWORD
            ]
        );
    }


    /**
     * Persist a new lesson plan template
     *
     * @return void
     * @throws Exception
     * @throws RouteNotFound
     * @throws \PDOException
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \UnexpectedValueException
     */
    public function postCreate(): void
    {
        $postData = $this->requestPost->getArrayCopy();
        $sequence = 1;

        /* This didn't exist before so I guess we spawn it
         */
        $template = $this->templateMapper->createModel()
            ->setTitle($postData['title'])
            ->setEnabled(true)
            ->setCreatedBy($this->getUser())
            ->setUpdatedBy($this->getUser());

        $this->templateMapper->save($template);

        /* Figure out the combined set of new rows and existing rows
         *
         * This POST key won't exist if the user just tried to save a template
         * without spawning any new fields
         */
        if (isset($postData['field'])) {
            foreach ($postData['field'] as $fieldId => $fieldData) {
                /* Determine visibility setting, translating ui bool into schema's enum
                 */
                $visibility = 'on' === ($fieldData['visibility'] ?? 'off') ? Role::STUDENT : Role::STAFF;

                /* Update all the pieces
                 * This is the Create endpoint so we can presume new models are always required
                 */
                $field = $this->fieldMapper->createModel()
                    ->setTitle($fieldData['title'])
                    ->setBody($fieldData['body'])
                    ->setLessonPlanTemplate($template)
                    ->setVisibility($visibility)
                    ->setSequence($sequence++);

                $this->fieldMapper->save($field);
            }
        } else {
            /* Mandate at least one field existing at all times
             * If 'field' is empty/unset, surmise that the user happened to
             * remove all fields...but we don't allow that anymore
             */
            throw $this->errorMapper->generateException(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
        }

        /* Go back to list
         */
        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('adminLessonPlanTemplate.getList'));
    }


    /**
     * Update an existing lesson plan template
     *
     * @param Model $lessonPlanTemplate
     * @return void
     * @throws RouteNotFound
     */
    public function getUpdate(Model $lessonPlanTemplate): void
    {
        $this->response = $this->responseFromTemplate(
            '@adminLessonPlanTemplate/create.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminLessonPlanTemplate.getList'),
                'allBreadcrumbs' => array_merge(
                    (new Subnav($this->getConfigLoader(), $this->getAuthorizer(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminLessonPlanTemplate.getList')),
                    [['Modify', null]]
                ),
                'lessonPlanTemplate' => $lessonPlanTemplate,
                'studentRole' => Role::STUDENT,
                'newKeyword' => self::NEW_KEYWORD
            ]
        );
    }


    /**
     * Persist changes to an existing lesson plan template
     *
     * @param Model $lessonPlanTemplate
     * @return void
     * @throws Exception
     * @throws InvalidColumnException
     * @throws RouteNotFound
     * @throws \PDOException
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    public function postUpdate(Model $lessonPlanTemplate): void
    {
        $postData = $this->requestPost->getArrayCopy();
        $sequence = 1;

        /* Remember this list of ids that we have either updated
         * or persisted to the db; anything outside this set needs
         * to be deleted, since they weren't seen in this payload
         */
        $seenFieldIds = [];

        /* Figure out the combined set of new rows and existing rows
         *
         * (This POST key won't exist if the user removed all the fields,
         * at which point we can presume they actually meant to do that)
         */
        if (isset($postData['field'])) {
            foreach ($postData['field'] as $fieldId => $fieldData) {
                /* Determine visibility setting, translating ui bool into schema's enum
                 */
                $visibility = 'on' === ($fieldData['visibility'] ?? 'off') ? Role::STUDENT : Role::STAFF;

                /* This is either a new row that was spawned by user,
                 * or it's a pre-existing row we can get from $lessonPlanTemplate
                 */
                if (str_contains((string)$fieldId, self::NEW_KEYWORD)) {
                    $field = $this->fieldMapper->createModel();
                } else {
                    $field = $lessonPlanTemplate->getFields()->getBy('id', $fieldId);
                }

                /* Update all the pieces
                 */
                $field->setTitle($fieldData['title'])
                    ->setBody($fieldData['body'])
                    ->setLessonPlanTemplate($lessonPlanTemplate)
                    ->setVisibility($visibility)
                    ->setSequence($sequence++);

                $this->fieldMapper->save($field);

                $seenFieldIds[] = $field->getId();
            }

            /* Anything that wasn't seen needs to be removed
             */
            $pb = $this->fieldMapper->getPredicateBuilder()
                ->equals('lesson_plan_template_id', $lessonPlanTemplate->getId())
                ->notIn('id', $seenFieldIds);

            $this->fieldMapper->deleteByPredicates($pb);
        } else {
            /* Mandate at least one field existing at all times
             * If 'field' is empty/unset, surmise that the user happened to
             * remove all fields...but we don't allow that anymore
             */
            throw $this->errorMapper->generateException(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
        }

        /* Then update the template's title in case that might have changed
         * Technically you, the updater, might be different to the guy who
         * originally created this LPT
         */
        if (trim((string)$postData['title']) !== trim($lessonPlanTemplate->getTitle())) {
            $lessonPlanTemplate->setTitle($postData['title'])
                ->setUpdatedBy($this->getUser());

            $this->templateMapper->save($lessonPlanTemplate);
        }

        /* Go back to list
         */
        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('adminLessonPlanTemplate.getList'));
    }


    /**
     * Delete a LPT and also its Fields
     *
     * @param Model $lessonPlanTemplate
     * @return void
     * @throws Exception
     */
    public function delete(Model $lessonPlanTemplate): void
    {
        $this->templateMapper->delete($lessonPlanTemplate);
    }


    /**
     * Toggle the Enabled state of an LPT
     *
     * @param Model $lessonPlanTemplate
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function postToggleEnableState(Model $lessonPlanTemplate): void
    {
        if ($lessonPlanTemplate->getEnabled()) {
            $lessonPlanTemplate->setEnabled(false);
        } else {
            $lessonPlanTemplate->setEnabled(true);
        }

        /* TODO should changing enabledness also change the Updated by?
         */
        $this->templateMapper->save($lessonPlanTemplate);
    }
}
