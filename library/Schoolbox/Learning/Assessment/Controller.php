<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Lumberjack\Logger;
use Alaress\Web\Controller\Exception as ControllerException;
use Alaress_Date_Format;
use Aura\Router\Exception\RouteNotFound;
use DomainException;
use InvalidArgumentException;
use LearnositySdk\Utils\Uuid;
use LogicException;
use Schoolbox\Admin\LessonPlan\Template\Mapper as LessonPlanTemplateMapper;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper as GradeSchemeMapper;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeScheme;
use Schoolbox\Assessment\Term\Mapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Mapper as LessonPlanDataModelMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\Feedback\Visibility as FeedbackVisibility;
use Schoolbox\Learning\InlineGrader\Kami\Model as KamiModel;
use Schoolbox\Learning\Learnosity\Helper;
use Schoolbox\Learning\Assessment\Participant\DataSource;
use Schoolbox\Learning\Assessment\Seb\Payload;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Assessment\Term;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Mapper\DefinitionException;
use Twig\Error\Error;
use UnexpectedValueException;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Assessment\Views\Mapper as LatestViewMapper;

/**
 * Class Controller
 *
 * This controller contains the Create/Modify screen that's shared among all the IAssessables:
 * DueWork, Task, Project. They were all originally one screen, though their abilities have
 * since expanded and diverged.
 *
 * @package Schoolbox\Learning\Assessment
 */
abstract class Controller extends ListController implements IAssessmentController
{
    /**
     * Controller constructor.
     *
     * @param UserMapper                 $userMapper
     * @param ErrorMapper                $errorMapper
     * @param MapperProxy                $assessmentMapper
     * @param WorkTypeMapper             $workTypeMapper
     * @param Access                     $access
     * @param Transactor                 $transactor
     * @param BreadcrumbFactory          $bakery
     * @param Mapper                     $termMapper
     * @param DataSource                 $participantDataSource
     * @param CourseSpecificationMapper  $courseSpecificationMapper
     * @param UnitSpecificationMapper    $unitSpecificationMapper
     * @param RosettaStone               $rosettaStone
     * @param Replicator                 $replicator
     * @param LatestViewMapper           $latestViewMapper
     * @param Project\ResultCache\Mapper $projectResultCacheMapper
     * @param LessonPlanTemplateMapper   $lessonPlanTemplateMapper
     * @param LessonPlanDataModelMapper  $lessonPlanDataModelMapper
     */
    public function __construct(
        UserMapper $userMapper,
        ErrorMapper $errorMapper,
        DataModel\MapperProxy $assessmentMapper,
        private WorkTypeMapper $workTypeMapper,
        Access $access,
        Transactor $transactor,
        protected BreadcrumbFactory $bakery,
        private Term\Mapper $termMapper,
        Participant\DataSource $participantDataSource,
        private CourseSpecificationMapper $courseSpecificationMapper,
        UnitSpecificationMapper $unitSpecificationMapper,
        RosettaStone $rosettaStone,
        private Replicator $replicator,
        protected LatestViewMapper $latestViewMapper,
        protected Project\ResultCache\Mapper $projectResultCacheMapper,
        protected LessonPlanTemplateMapper $lessonPlanTemplateMapper,
        protected LessonPlanDataModelMapper $lessonPlanDataModelMapper,
        protected ?MimetypeMapper $mimetypeMapper = null,
    ) {
        parent::__construct($userMapper, $errorMapper, $assessmentMapper, $access, $transactor, $participantDataSource, $unitSpecificationMapper, $rosettaStone, $this->projectResultCacheMapper);
    }


    /**
     * Redirect the standard assessment URL to a user specific version
     * @api   GET /learning/assessment/{assessmentId}
     * @since 165
     * @param IAssessable $assessment
     * @throws RouteNotFound
     */
    public function getAssessment(IAssessable $assessment): void
    {
        /* Presume a default url.
         *
         * If you're a parent you'll end up at a dead end if you don't have
         * any children which can unlock the summary screen but that would
         * be an unusual scenario (what are you even doing here then?)
         *
         *  - you could be given read access to a class page and view its course outline
         *    but you don't have any children associated with your session
         *
         *  - or you don't have any children participating in them
         *
         *  - the Hide Due Work Details config is activated
         *
         * If you're a student, you'll end up at a dead end if you've been excluded
         * from participating in the assessment but try to get there anyway
         */
        $defaultUrl = $this->routeGenerator->generate('learningAssessment.getSummary', [
            'assessmentId' => $assessment->getId(),
            'studentId' => $this->getUser()->getId()
        ]);

        /* If you're a parent,
         * find the first child who can visit this url
         * and pass that id into the router instead so you can witness details
         *
         * That Hide Due Work Details config is defined in buildAuthRuleCanViewSummary()
         * which uses CanSeeParticipation()
         */
        $parentUrl = null;

        if ($this->getUser()->getRole()->isParent()) {
            foreach ($this->getUser()->getChildren() as $child) {
                $parentUrl = $this->getRouteIfAuthorized('learningAssessment.getSummary', [
                    'assessment' => $assessment,
                    'assessmentId' => $assessment->getId(),
                    'student' => $child,
                    'studentId' => $child->getId()
                ]);

                if ($parentUrl) {
                    break;
                }
            }
        }

        $this->redirect($parentUrl ?: $defaultUrl);
    }


    /**
     * Redirect the standard assessment URL to a user specific version with SEB key
     * @param IAssessable $assessment
     * @param User        $student
     * @throws RouteNotFound
     * @since 211
     * @api   GET /learning/assessments/:assessmentId/:userId/seb
     */
    public function getSummaryViaSeb(IAssessable $assessment, User $student): void
    {
        $defaultUrl = $this->routeGenerator->generate(
            'learningAssessment.getSummary',
            [
                'assessmentId' => $assessment->getId(),
                'studentId'    => $student->getId(),
            ]
        );
        $defaultUrl .= '?' . http_build_query([Payload::SEB_KEY => '']);
        $content    = $this->responseFromTemplateUnthemed('@learningAssessment/seb.twig', [
            'url' => $defaultUrl,
        ]);
        $this->response = $content;
    }


    /**
     * Get the form for creating a new assessment in a particular context
     *
     * @api   GET /learning/{contextType}/{contextId}/assessments/new?type
     * @since 165
     * @param AssessmentContext $context
     * @throws DefinitionException If unable to locate a specified mapper join
     * @throws Deleted
     * @throws DomainException If user has interaction but not participant record
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws RouteNotFound
     * @throws UnexpectedValueException If unable to derive a project's participant collection*@throws \PDOException*@throws \PDOException
     * @throws \PDOException
     */
    public function getCreateAssessmentForm(AssessmentContext $context): void
    {
        $type = $this->requestQuery->get('type');

        // build a stub
        $assessment = AssessmentType::stringToObject($type, $context);

        /* Inject a Learnosity Reference uuid if that's what we are building here
         */
        if (AssessmentType::TYPE_QUIZ === $type) {
            $assessment->setLearnosityReference(Uuid::generate());
        }

        // create a dynamic plan on the assessment to get started
        $assessment->getPlans()->add((new Plan($assessment))->setIsDynamic(true));

        $assessment = $this->participantDataSource->loadParticipationIntoAssessment($assessment);

        $data = $this->getAssessmentAsTwigArray($context, $assessment);

        /* Curriculum Codes prefilled from those associated with context folder
         */
        $data['curriculumNodes'] = $context->getFolder()->getViewableCurriculumNodes();

        /* Default Visibility comes from an LMS Policy setting in config
         */
        $data['markVisibility'] = $this->getConfigLoader()->get('submission_access');

        // Did the caller request a returnUrl?
        $returnUrl = $this->requestQuery->get(Redirect::RETURN_URL);

        if ($returnUrl) {
            $data[Redirect::RETURN_URL] = $returnUrl;
        }

        $data['custom_assessment_participation'] = $this->getConfigLoader()->get('custom_assessment_participation');
        $data['selfAssessmentEnabled'] = $this->getConfigLoader()->get('self_assessment_enabled');

        $data['defaultGradeGroup'] = $this->getConfigLoader()->get('default_grade_group');

        // Relocation is available only for update
        $data['canRelocate'] = false;

        /* Course Managed Field and associated checkbox in the ui
         */
        $courseManagedFields = new CourseManagedFields($this->getUser(), $assessment);

        $data['courseManaged'] = [
            'isVisible' => $courseManagedFields->isVisible(),
            'isMutable' => $courseManagedFields->isMutable(),
            'isReadonly' => $courseManagedFields->isReadonly(),
        ];

        if (AssessmentType::TYPE_DUE_WORK === $type) {
            // To be able to view the current student policy if enabling within the submission type
            $data['lmsStudentPolicy'] = $this->getConfigLoader()->get('lms_student_policy');

            // To determine whether to display the interactive worksheet option
            $data['kami_enabled'] = $this->getConfigLoader()->get(ConfigMapper::NAME_KAMI_ENABLED);

            $kamiFiles = $this->mimetypeMapper
                ->findMimeTypesFromExtensions(
                    array_keys(KamiModel::ALLOWED_FILE_TYPES)
                );

            // To be able to add a file to use as the worksheet
            $data['attachzoneWorksheet'] = (new Zone('worksheet'))
                ->setMulti(false)
                ->setAttachPrompt('Attach a worksheet')
                ->setAttachmentsLabel('')
                ->setMimeFilter(implode(",", $kamiFiles))
                ->setFilesystemContext('worksheet')
                ->setFileVar('worksheet')
                ->getTwigContext();
        }
        /* A list of lesson plans
         * We've literally been piling everything into this one controller method!
         */
        $data['lessonPlanTemplates'] = [
            'recent' => $this->lessonPlanTemplateMapper->findRecentlyUsed($this->getUser()),
            'all' => $this->lessonPlanTemplateMapper->findEnabled()
        ];

        /* Note that the Lesson Plan assessment type has a preceding step which none
         * of the other assessment types utilise: selection of a template. Once that's
         * chosen, only then can you start to actually do something with the Lesson Plan
         */
        if (AssessmentType::TYPE_LESSON_PLAN === $type) {
            /* Lesson Plan creational phase looks at the querystring param.
             * if the querystring param "lpt" exists, then we've chosen
             * our template and can actually start using it
             *
             * Embrace both the asynchronous and synchronous access mechanisms
             */
            $templateId = trim((string)$this->requestQuery->get('lpt'));

            /* Some metadata for the Lesson Plan workflow
             * This encapsulates the myriad of combinations which is really really awkward
             */
            $data['lessonPlanMeta'] = [
                'newKeyword' => LessonPlan\Decorator\Decorator::NEW_KEYWORD,
                'consts' => [
                    'student' => \Schoolbox\Role::STUDENT
                ],
                'template' => null,
                'fields' => [],
                'type' => $type,
                'mageAsync' => false,
                'includeBreadcrumbs' => true,
            ];

            if ('' === $templateId) {
                /* You need to Select an LPT
                 *
                 * If $part-1 is set in the query string param, you came here from the
                 * Activity Selection screen via Course Builder component. This part
                 * loads the LPT list asynchronously and injects it into a parent container
                 *
                 * Otherwise you need to load the container itself because you came here
                 * via the Course Creation process...
                 */
                if ('' === trim((string)$this->requestQuery->get('part-1'))) {
                    if ($this->isXhrRequest()) {
                        $content = $this->responseFromTemplateUnthemed(sprintf(
                            '@learningAssessment/%s/selectionContainer.twig',
                            AssessmentType::TYPE_LESSON_PLAN
                        ), $data);
                    } else {
                        /* TODO where is this even used
                         */
                        $content = $this->responseFromTemplate(sprintf(
                            '@learningAssessment/%s/selectionContainer.twig',
                            AssessmentType::TYPE_LESSON_PLAN
                        ), $data);
                    }
                } else {
                    $data['lessonPlanMeta']['mageAsync'] = true;

                    if ($this->isXhrRequest()) {
                        $content = $this->responseFromTemplateUnthemed(sprintf(
                            '@learningAssessment/%s/selection.twig',
                            AssessmentType::TYPE_LESSON_PLAN
                        ), $data);
                    } else {
                        $content = $this->responseFromTemplate(sprintf(
                            '@learningAssessment/%s/selection.twig',
                            AssessmentType::TYPE_LESSON_PLAN
                        ), $data);
                    }
                }
            } else {
                /* Customise the selected LPT
                 */
                $lessonPlanTemplate = $this->lessonPlanTemplateMapper->findById((int)$templateId);

                $data['lessonPlanMeta']['template'] = $lessonPlanTemplate->getId();

                /* Field list contains the ones from the Lesson Plan Template when creating
                 * When updating, it's actually fields from the Lesson Plan instance itself
                 */
                $data['lessonPlanMeta']['fields'] = $lessonPlanTemplate->getFields();

                if ($this->isXhrRequest()) {
                    $content = $this->responseFromTemplateUnthemed(sprintf(
                        "@learningAssessment/%s/create.twig",
                        AssessmentType::TYPE_LESSON_PLAN
                    ), $data);
                } else {
                    $content = $this->responseFromTemplate('@learningAssessment/create.twig', $data);
                }
            }
        } else {
            /* Legacy Quiz XML Import
             */
            if (AssessmentType::TYPE_QUIZ === $type) {
                $data['attachzone'] = (new Zone('xmlImport'))
                    /* XML Imports should have an expiry date so they can be automatically removed
                     * to save space. While this would be nice to have as a "backup for users" the
                     * number of times they've asked us for a copy of their own data is basically 0
                     */
                    ->setExpiryDate(\Alaress_Date_Format::now()->addHours(1))
                    ->setMulti(false)
                    ->setAttachPrompt('Attach a legacy xml')
                    ->setAttachmentsLabel('')
                    ->setMimeFilter('text/xml,application/xml')
                    ->setFilesystemContext('xml')
                    ->getTwigContext();
            }

            // either output the fragment to the mage, or use the synchronous form
            if ($this->isXhrRequest()) {
                $content = $this->responseFromTemplateUnthemed("@learningAssessment/$type/create.twig", $data);
            } else {
                $content = $this->responseFromTemplate('@learningAssessment/create.twig', $data);
            }
        }

        $this->response = $content;
    }


    /**
     * Get the form to update an existing Assessment's details
     *
     * @param IAssessable $assessment
     * @throws Deleted
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws RouteNotFound
     * @throws UnexpectedValueException|Error
     * @api   /learning/assessments/:assessmentId/modify
     * @since 165
     */
    public function getUpdateAssessmentForm(IAssessable $assessment): void
    {
        $data = $this->getAssessmentAsTwigArray($assessment->getContext(), $assessment);

        $breadcrumbs = $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([$assessment->getTitle()]);

        /* Quizzes have two parts to change
         */
        if ($assessment instanceof Quiz\Model) {
            $breadcrumbs->add([
                'Modify Settings',
                null
            ]);
        }

        $data['breadcrumbs'] = $breadcrumbs;
        $data['returnUrlName'] = Redirect::RETURN_URL;
        $data['returnUrl'] = $this->requestQuery->get(Redirect::RETURN_URL);

        /* Curriculum Codes prefilled from those associated with the assessment itself
         hide nodes with no codes
         */
        $data['curriculumNodes'] = $assessment->getViewableCurriculumNodes()->reindex();

        $data['custom_assessment_participation'] = $this->getConfigLoader()->get('custom_assessment_participation');
        $data['selfAssessmentEnabled'] = $this->getConfigLoader()->get('self_assessment_enabled');

        $data['defaultGradeGroup'] = $this->getConfigLoader()->get('default_grade_group');

        $data['canRelocate'] = $assessment->isSpecification();

        /* Course Managed Field and associated checkbox in the ui
         */
        $courseManagedFields = new CourseManagedFields($this->getUser(), $assessment);

        /* Lesson Plan Template metadata, used to construct forms
         */
        if ($assessment instanceof LessonPlan\Model) {
            /* TODO Get the fields separately instead of joining everything in the swamp
             *  because that's just asking for megaquery troubles. We only need the
             *  Lesson Plan fields here
             */
            $lessonPlan = $this->lessonPlanDataModelMapper->findOneByColumn('assessment_id', $assessment->getId());

            $data['lessonPlanMeta'] = [
                'newKeyword' => LessonPlan\Decorator\Decorator::NEW_KEYWORD,
                'consts' => [
                    'student' => \Schoolbox\Role::STUDENT
                ],
                'template' => $lessonPlan->getLessonPlanTemplate(),
                'fields' => $lessonPlan->getFields(),
                'type' => AssessmentType::TYPE_LESSON_PLAN,
                'mageAsync' => false,
                'includeBreadcrumbs' => false
            ];
        }

        $data['courseManaged'] = [
            'isVisible' => $courseManagedFields->isVisible(),
            'isMutable' => $courseManagedFields->isMutable(),
            'isReadonly' => $courseManagedFields->isReadonly(),
        ];

        if ($assessment instanceof DueWork\Model) {
            // To be able to view the current student policy if enabling within the submission type
            $data['lmsStudentPolicy'] = $this->getConfigLoader()->get('lms_student_policy');

            // To determine whether to display the interactive worksheet option
            $data['kami_enabled'] = $this->getConfigLoader()->get(ConfigMapper::NAME_KAMI_ENABLED);

            $kamiFiles = $this->mimetypeMapper
                ->findMimeTypesFromExtensions(
                    array_keys(KamiModel::ALLOWED_FILE_TYPES)
                );


            $zone = (new Zone('worksheet'))
                ->setMulti(false)
                ->setAttachPrompt('Attach a worksheet')
                ->setAttachmentsLabel('')
                ->setMimeFilter(implode(",", $kamiFiles))
                ->setFilesystemContext('worksheet')
                ->setReadOnly($courseManagedFields->isReadonly()) //
                ->setFileVar('worksheet');
            if ($assessment->getWorksheetTemplate()) {
                $zone = $zone->attachFiles([$assessment->getWorksheetTemplate()]);
            }
            $data['attachzoneWorksheet'] = $zone->getTwigContext();
        }
        $this->response = $this->responseFromTemplate("@learningAssessment/update.twig", $data);
    }


    /**
     * Save the data when creating a specific kind of Assessment
     *
     * This happens after the common details are saved and after
     * you've chosen one of project, due work, task, or quiz
     *
     * @api   POST /learning/assessments/:assessmentId
     * @since 165
     *
     * @param IAssessable $assessment
     * @throws ControllerException
     * @throws RouteNotFound
     * @throws \Exception
     */
    public function postUpdateAssessment(IAssessable $assessment): void
    {
        /* Regular update while you're creating the assessment, otherwise
         */
        try {
            $assessmentInfo = $this->persistAssessment(
                $this->getUser(),
                $this->requestPost,
                $assessment
            );
        } catch (DomainException | InvalidArgumentException $e) {
            $this->sendError('Could not save assessment. ', [$e->getMessage()]);

            /* Return Early to let mage deal with error messages
             */
            return;
        }

        $postReturnUrl = $this->requestPost->get(Redirect::RETURN_URL);

        // we're done here
        $this->redirect($this->determineRedirect(
            $assessmentInfo['assessment'],
            $postReturnUrl,
            $assessmentInfo[Redirect::RETURN_URL],
            $assessmentInfo['relocated']
        ));
    }


    /**
     * Gets a list of units and projects, to which the assessment can be moved
     *
     * @api   /learning/assessments/:assessmentId/locations
     * @since 201
     * @param IAssessable $assessment
     * @throws LogicException
     * @throws UnexpectedValueException
     * @throws InvalidColumnException
     */
    public function getLocationForm(IAssessable $assessment): void
    {
        if ($assessment->isImplementation()) {
            // not interested
            return;
        }

        $currentUnitSpec = $assessment->getBaseContext();
        try {
            $currentCourseSpec = $this->courseSpecificationMapper->findByFolderId($currentUnitSpec->getFolder()->getParent()->getId());
        } catch (MapperException $e) {
            return;
        }
        $allUnitSpecs = $currentCourseSpec->getUnits();

        $unitProjects = new Collection();

        // if this assessment is not a project, add projects tp relocatable locations
        if (!$assessment instanceof Project) {
            foreach ($allUnitSpecs as $unitSpec) {
                $projects    = $this->getAssessmentMapper()->createCollection();
                $assessables = $this->getAssessmentMapper()->findAllByUnitSpecificationId($unitSpec->getId());
                foreach ($assessables as $assessable) {
                    if (!$assessable instanceof Project) {
                        continue;
                    }
                    $projects->add($assessable);
                }
                $unitProjects->offsetSet($unitSpec->getId(), $projects);
            }
        }
        $this->response = $this->responseDependentOnRequest(
            '@learningAssessment/fragments/assessmentLocationList.twig',
            [
                'units'        => $allUnitSpecs,
                'unitProjects' => $unitProjects,
                'current'      => $assessment->getContext()->getLocationId()
            ]
        );
    }


    /**
     * Update particular fields of an existing IAssessable
     *
     * Default Abstract implementation does nothing, Concrete class to override.
     *
     * @param IAssessable $assessment
     * @return void
     * @throws \Exception
     */
    public function patchUpdateAssessment(IAssessable $assessment): void
    {
        throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
    }


    /**
     * Delete the given assessment.
     *
     * Deletion is considered to be an idempotent method, so do not return an error on repeat attempts to delete an entity.
     * On failure, respond with '4XX' or '5XX' as appropriate
     *
     * @api   POST /learning/assessments/{assessmentId}/delete
     * @since 165
     * @param IAssessable $assessment
     * @return void
     * @throws DefinitionException
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws MapperException
     * @throws Deleted
     * @throws RouteNotFound
     * @throws UnexpectedValueException
     * @throws \PDOException
     */
    public function postDeleteAssessment(IAssessable $assessment): void
    {
        /* Deleting assessments from inside a project always affects
         * its outcome; update the cache accordingly
         */
        $cacheValid = true;

        if ($assessment->getContext() instanceof Project) {
            $cacheValid = false;
        }

        // If not already deleted?
        if (!$assessment->isDeleted()) {
            $notifier = $this->getAssessmentMapper()->getNotifier();
            $before   = $notifier->dehydrate($assessment);
            $this->getAssessmentMapper()->delete($assessment);
            $notifier->notify($assessment, $this->getUser(), $before);
        }

        /* Delete corresponding Learnosity items
         */
        if ($assessment instanceof \Schoolbox\Learning\Assessment\Quiz\Model && $assessment->getLearnosityReference()) {
            $api = new Api(
                (new \Schoolbox\Url\Factory($this->getConfigLoader()))->buildFullyQualified()->getHost(),
                $this->getConfigLoader()->get('learnosity_version'),
                $this->getConfigLoader()->get('learnosity_consumer_key'),
                $this->getConfigLoader()->get('learnosity_consumer_secret'),
                new Logger(STDOUT, STDOUT, \Monolog\Logger::DEBUG, [], true, 'learnosity')
            );

            (new Helper($api))->archive($assessment->getLearnosityReference());
        }

        $folder = $assessment->getContext()->getFolder();
        $postReturnUrl = $this->requestQuery->get(Redirect::RETURN_URL);

        $returnUrl = $this->determineRedirect($assessment, $postReturnUrl, "/homepage/{$folder->getId()}");

        /* Deleting assessments from inside a project always affects
         * its outcome; update the cache accordingly
         */
        if (!$cacheValid) {
            (new Project\ResultCache\ResultCache($this->projectResultCacheMapper))->refresh($this->getAssessmentMapper(), $assessment);
        }

        if ($this->isXhrRequest()) {
            $this->response = $this->responseAsJson([
                Redirect::RETURN_URL => $returnUrl
            ]);
        } else {
            $this->redirect($returnUrl);
        }
    }


    /**
     * @param IAssessable $assessment
     * @return array
     */
    abstract protected function getAssessmentTwigData(IAssessable $assessment): array;


    /**
     * @param AssessmentContext $context
     * @param IAssessable       $assessment
     * @return array
     * @throws InvalidArgumentException
     * @throws RouteNotFound
     * @throws UnexpectedValueException
     */
    private function getAssessmentAsTwigArray(AssessmentContext $context, IAssessable $assessment): array
    {
        if (!$assessment->getId()) {
            // new assessment: set default values
            $due = new Alaress_Date_Format(new \DateTime("+1 day"));
            $due->endOfDay();
            $due->setTimeFromString($this->getConfigLoader()->get('lms_duetime_default'));
            $assessment
                ->setDueAt($due)
                ->setCloseAt(new Alaress_Date_Format(new \DateTime('+2 day 23:59:59')))
                ->setWeight(0);

            if ($assessment instanceof Quiz\Model) {
                $assessment
                    ->setPublishAt(new Alaress_Date_Format(new \DateTime('+1 hour')))
                    ->setOpenAt(new Alaress_Date_Format(new \DateTime('+1 hour')));
            } else {
                $assessment
                    ->setPublishAt(new Alaress_Date_Format(new \DateTime('00:00:00')))
                    ->setOpenAt(new Alaress_Date_Format(new \DateTime('00:00:00')));
            }
        }

        $contextType = AssessmentContextType::objectToString($context) . 's';

        /* The usual info everything requires
         */
        $data = [
            'context'                  => $context,
            'contextType'              => $contextType,
            'baseContextType'          => AssessmentContextType::objectToString($context->getBaseContext()),
            'breadcrumbs'              => $this->getBakery()->produceFromContext($context, $assessment, $this->getUser()),
            'assessment'               => $assessment,
            'folder'                   => $context->getFolder(),
            'assessmentType'           => AssessmentType::objectToString($assessment),
            'markVisibility'           => $assessment->getMarkVisibility(),
            'availableVisibilities'    => FeedbackVisibility::getAvailableVisibilities($this->getConfigLoader()->get('submission_visibility_max')),
            'allWorkTypes'             => $this->workTypeMapper->findAll(['order' => ['sequence' => 'asc']]),

            /* Capabilities happen to have the same mark types as its DueWork IAssessable container,
             * so we don't need a different method than the existing getAllMarkTypes
             */
            'capabilityMarkTypes'      => $this->getAllMarkTypes($assessment),
            'capabilityGradingSchemes' => $this->getAllGradeSchemes()
        ];

        $data = array_merge($data, $this->getAssessmentTwigData($assessment));

        return $data;
    }


    /**
     * Get an overview of rubric marks
     *
     * @param IAssessable $assessment
     * @throws \Exception
     */
    public function getOverview(IAssessable $assessment): void
    {
        // For now, only DueWork uses this route.
        throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
    }


    /**
     * Download overview of rubric marks
     *
     * @param IAssessable $assessment
     * @throws \Exception
     */
    public function downloadOverview(IAssessable $assessment): void
    {
        // For now, only DueWork uses this route.
        throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
    }


    /**
     * All the possible Mark Types to choose from for this particular $assessmentType
     *
     * @param IAssessable $assessable
     * @return array
     * @throws UnexpectedValueException
     */
    protected function getAllMarkTypes(IAssessable $assessable)
    {
        return MarkSchemeFactory::getAvailableMarkTypes($assessable);
    }


    /**
     * Gets all grading schemes.
     *
     * TODO DI this if you feel like it
     *
     * @return GradeScheme[]|Collection
     */
    protected function getAllGradeSchemes()
    {
        return GradeSchemeMapper::getInstance()->findAllWithGradeLevels();
    }


    /**
     * Decide which URL to send the client back to
     *
     * @param IAssessable $assessment
     * @param string|null $postReturnUrl an encoded URL
     * @param string      $defaultReturnUrl
     * @param bool        $relocated
     * @param bool        $isCreate
     * @return string
     * @throws RouteNotFound
     */
    public function determineRedirect(IAssessable $assessment, $postReturnUrl = null, $defaultReturnUrl = '', bool $relocated = false, bool $isCreate = false): string
    {
        /**
         * First priority is a the post return URL
         * Then if this is in a project, and if so then go to project status
         * Otherwise go to default return url
         */
        if ($postReturnUrl) {
            $returnUrl = Redirect::decode($postReturnUrl);
            if ($relocated && strpos($returnUrl, '/homepage/') === 0) {
                return '/homepage/' . $assessment->getBaseContext()->getFolder()->getId();
            }
            return $returnUrl;
        } elseif ($assessment->getContext() instanceof Project) {
            return $this->routeGenerator->generate('learningAssessment.getStatus', ['assessmentId' => $assessment->getContext()->getId()]);
        }

        return $defaultReturnUrl;
    }


    /**
     * @return Term\Mapper
     */
    protected function getTermMapper(): \Schoolbox\Assessment\Term\Mapper
    {
        return $this->termMapper;
    }
}
