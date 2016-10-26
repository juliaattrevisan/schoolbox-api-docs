<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\File as StudentSubmission;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\FileMapper as StudentSubmissionMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper as SubmissionReturnMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Course\Unit\ImplementationMapperProxy as UnitImplMapperProxy;
use Schoolbox\Learning\Course\Unit\SpecificationMapperProxy as UnitSpecMapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkSubmission;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Role;
use \Twig_Loader_Chain;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningAssessment';


    /**
     * @inheritdoc
     */
    public function initRoutes(Router $router, $basePath)
    {
        $routes = [
            'GET' => [

                /* Get 'Create Assessment' form in a specific context. Optionally declare Assessment type
                 */
                '/assessment/folder/{folderId}{/assessmentTypeTitle}'     => 'getSelectAssessmentInFolder',

                // May contain a 'returnUrl' GET parameter to return to project modification page
                '/assessment/project/{projectId}{/assessmentTypeTitle}'   => 'getSelectAssessmentInProject',
                '/assessment/unit/{unitId}{/assessmentTypeTitle}'         => 'getSelectAssessmentInUnitImplementation',
                '/assessment/unitspec/{unitSpecId}{/assessmentTypeTitle}' => 'getSelectAssessmentInUnitSpecification',

                /* Get a 'Modify Assessment' form to edit an existing assessment
                 */
                '/assessment/{assessmentId}/update' => 'getUpdateAssessment',

                /* Redirect url which sends people to either Status or
                 * Summary screens depending on Role of the current user
                 */
                '/assessment/{assessmentId}' => 'getAssessment',

                /* Assessment Summary screen
                 */
                '/assessment/{assessmentId}/{studentId}' => 'getSummary',

                /* Submit a Response to a Due Work
                 */
                '/assessment/{assessmentId}/submit/{studentId}' => 'getCreateSubmission',

                /* Staff-specific Status screens
                 */
                '/assessment/{assessmentId}/status' => 'getStatus',

                /* Miscellaneous routes
                 */
//                '/assessment/{assessmentId}/{studentId}'        => 'getStudentAssessment',


            ],
            'POST' => [

                /* Return a HTML fragment for the AssessmentType selected in Step-1
                 */
                '/assessment/select' => 'postSelectAssessment',

                /* Save the Assessment's data + redirect somewhere appropriate (context or returnUrl)
                 */
                '/assessment/create' => 'postCreateAssessment',

                /* Update an existing Assessment + redirect somewhere appropriate (context or returnUrl)
                 */
                '/assessment/{assessmentId}/update' => 'postUpdateAssessment',

                /* Delete an assessment
                 */
                '/assessment/{assessmentId}/delete' => 'postDeleteAssessment',

                /* Save a Response to a Due Work
                 */
                '/assessment/{assessmentId}/submit/{studentId}' => 'postCreateSubmission'
            ]
        ];

        foreach ($routes['GET'] as $path => $alias) {
            $this->addGetRoute($router, $alias, $basePath, $path)->addTokens([
                'assessmentId' => '\d+',
                'assessmentType' => '\d+',
                'assessmentTypeTitle' => '('.implode('|', AssessmentType::getStringValues()).')',
                'studentId' => '\d+',
            ]);
        }

        foreach ($routes['POST'] as $path => $alias) {
            $this->addPostRoute($router, $alias, $basePath, $path);
        }
    }

    /**
     * @inheritdoc
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload(
        /**
         * @param $params
         * @return array
         */
            $matcher,
            '(?:getSelectAssessmentInFolder|getSelectAssessmentInProject|getSelectAssessmentInUnitImplementation|getSelectAssessmentInUnitSpecification)',
            function ($params) {

                $args = [];

                // TODO add mapper error handling
                $context = null;
                if(array_key_exists('folderId', $params)){
                    $context = $this->getFolderMapper()->findById($params['folderId']);
                    $args['folder'] = $context;
                } elseif(array_key_exists('projectId', $params)){
                    $context = $this->getAssessmentMapperProxy()->findById($params['projectId']);
                    $context = ($context instanceof Project) ? $context : null;
                    $args['project'] = $context;
                } elseif(array_key_exists('unitId', $params)){
                    $context = $this->getUnitImplMapperProxy()->findById($params['unitId']);
                    $args['unitImpl'] = $context;
                } elseif(array_key_exists('unitSpecId', $params)){
                    $context = $this->getUnitSpecMapperProxy()->findById($params['unitSpecId']);
                    $args['unitSpec'] = $context;
                }

                if(is_null($context)){
                    // TODO - needs more informative Exception
                    throw new \DomainException('Unable to determine context');
                }

                if($params['assessmentTypeTitle']){
                    $args['assessment'] = AssessmentType::stringToObject($params['assessmentTypeTitle']);
                    $args['assessment']->setContext($context);
                    // Is this a DueWork ?
                    if($args['assessment'] instanceof DueWork){
                        // Create an empty Rubric with appropriate Indicators
                        $folderIds = [$context->getFolder()->getId()];
                        if($context instanceof UnitSpecification){
                            $folderIds[] = $context->getCourse()->getFolder()->getId();
                        }
                        $rubric = RubricMapper::getInstance()->createForFolder($folderIds);
                        $rubric->setSubmissionBox(new SubmissionBox());
                        $args['assessment']->setRubric($rubric);
                    }
                } else {
                    $args['assessment'] = null;
                }

                return $args;
            }
        );


        $this->addRoutePreload(
            $matcher,
            '(?:getUpdateAssessment|postUpdateAssessment)',
            function ($params) {
                return [
                    'assessment' => $this->getAssessmentMapperProxy()->findById($params['assessmentId'])
                ];
            }
        );

        $this->addRoutePreload(
            $matcher,
            '(?:postDeleteAssessment|getAssessment)',
            function ($params) {
                try {
                    $assessment = $this->getAssessmentMapperProxy()->findById($params['assessmentId']);
                } catch (MapperException\Deleted $e){
                    $assessment = $e->getDeletedObject();
                }
                return ['assessment' => $assessment];
            }
        );

        $this->addRoutePreload(
            $matcher,
            '(?:getSummary)',
            function ($params) {
                try {
                    $args = [];

                    $args['participant'] = $this->getParticipantMapperProxy()->findForAssessmentAndUser(
                        $this->getAssessmentMapperProxy()->findById($params['assessmentId']),
                        $this->getUserMapper()->findById($params['studentId'])
                    );

                    return $args;
                } catch (\OutOfBoundsException $e) {
                    throw new NotFound($e->getMessage());
                } catch (\Exception $e) {
                    throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
                }
            }
        );

        $this->addRoutePreload(
            $matcher,
            '(?:getStatus|getStudentAssessment)',
            function ($params) {

                $args['assessment'] = $this->getAssessmentMapperProxy()->findById($params['assessmentId']);

                if (array_key_exists('studentId', $params)) {
                    try {
                        $args['student'] = $this->getUserMapper()->findById($params['studentId']);
                    } catch (MapperException $e) {
                        $args['student'] = $this->getUserMapper()->createModel();
                    }
                }
                return $args;
            }
        );

        $this->addRoutePreload(
            $matcher,
            ['getCreateSubmission', 'postCreateSubmission'],
            function ($params) {

                /** @var DueWork $assessment */
                $assessment = $this->getAssessmentMapperProxy()->findById($params['assessmentId']);

                // Route is only valid for DueWork.
                // TODO - if $assessment exists, redirect to more appropriate page. For now just error out.
                if(!$assessment instanceof DueWork){
                    throw new \DomainException('TODO - You can only submit to DueWork. Found: '.get_class($assessment));
                }

                $student = $this->getUserMapper()->findById($params['studentId']);

                $participant = new Participant($student, $assessment);
                $assessment->getAllParticipants()->add($participant);

                // Get all Student Submissions (IResponses)
                $studentSubmissionMapper = $this->getStudentSubmissionMapper();

                // TODO - replace 'box_id' with 'assessment_id' once migration tasks are done
                $columnList = [
                    'box_id' => $assessment->getId(),
                    'owner' => $student->getId(),
                ];


                /** @var StudentSubmission[] $studentSubmissions */
                $studentSubmissions = $studentSubmissionMapper->findByColumnList($columnList);

                // TODO - This translation from 'StudentSubmission' to 'DueWorkResponse' should
                // TODO - not be necessary. A StudentSubmission should BE AN IResponse
                // TODO - Update the mappers to return the new objects
                foreach($studentSubmissions as $studentSubmission){
                    $response = (new DueWorkSubmission())
                        ->setId($studentSubmission->getId())
                        ->setSubmittedBy($student)
                        ->setSubmittedOnBehalfOf($student)
                        ->setCreatedAt($studentSubmission->getCreatedAt())
                        ->setDeletedAt(new \Alaress_Date_NullDate())
                        ->setUpdatedAt($studentSubmission->getCreatedAt());

                    if($hash = $studentSubmission->getHash()){
                        $response->getAllFiles()->add($this->getStorageMapper()->findByHash($hash));
                    }
                    $response->setAssessable($assessment);
                    $assessment->getResponses()->add($response);
                    $participant->getAllResponses()->add($response);
                }

                // Get all Student Returns (IFeedbacks)
                $submissionReturnMapper = $this->getSubmissionReturnMapper();

                // TODO - replace 'box_id' with 'assessment_id' once migration tasks are done
                $columnList = [
                    'box_id' => $assessment->getId(),
                    'owner' => $student->getId(),
                ];
                /** @var ReturnedWork[] $submissionReturns */
                $submissionReturns = $submissionReturnMapper->findByColumnList($columnList);

                // TODO - This translation from 'ReturnedWork' to 'DueWorkFeedback' should
                // TODO - not be necessary. A ReturnedWork should BE AN IFeedback
                // TODO - Update the mappers to return the new objects
                foreach($submissionReturns as $submissionReturn){
                    $feedback = (new DueWorkFeedback())
                        ->setId($submissionReturn->getId())
                        ->setComment($submissionReturn->getComment())
                        ->setNormalisedMark($submissionReturn->getNormalizedMark())
                        ->setHumanReadableMark($submissionReturn->getMark())
                        ->setReviewer($submissionReturn->getMarker())
                        ->setCreatedAt($submissionReturn->getCreatedAt())
                        ->setUpdatedAt($submissionReturn->getCreatedAt())
                        ->setPublishedAt($submissionReturn->getCreatedAt())
                        ->setDeletedAt($submissionReturn->getDeletedAt());

                    if($hash = $submissionReturn->getHash()){
                        $feedback->getAllFiles()->add($this->getStorageMapper()->findByHash($hash));
                    }
                    $feedback->setAssessable($assessment);
                    $assessment->getFeedbacks()->add($feedback);
                    $participant->getAllFeedbacks()->add($feedback);
                }

                return [
                    'assessment' => $assessment,
                    'student' => $student
                ];
            }
        );

        $this->addRoutePreload(
            $matcher,
            '(?:postCreateSubmission)',
            function ($params) {
                return [
                    'assessment' => $this->getAssessmentMapperProxy()->findById($params['assessmentId'])
                ];
            }
        );
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(Controller::class, [
                'folderMapper'                  => $this->getMapperFactory()->getLazy(FolderMapper::class),
                'errorMapper'                   => $this->getMapperFactory()->getLazy(ErrorMapper::class),
                'submissionFileMapper'          => $this->getMapperFactory()->getLazy(StudentSubmissionMapper::class),
                'assessmentMapperProxy'         => $injector->lazyGet('assessmentDataSource'),
                'unitSpecificationMapperProxy'  => $injector->lazyGet('unitSpecificationDataSource'),
                'unitImplementationMapperProxy' => $injector->lazyGet('unitImplementationDataSource'),
                'participantDataSource'         => $injector->lazyGet('participantDataSource'),
            ])
        );
    }

    /**
     * These pages may be accessed by:
     *
     * * superusers
     * * staff
     * * the child themselves
     * * the child's parents
     *
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $ruleRegexp = '(?:getSummary|getCreateSubmission)';
        $this->addRoutePermission($authorizer, $ruleRegexp,
            new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\HasRole(Role::STUDENT),
                    new Rule\HasRole(Role::STAFF),
                    new Rule\IsSuperuser()
                )
            )
        );

        $staffOnly = [
            'getSummaryStaff','postCreateAssessment','postUpdateAssessment','postDeleteAssessment','getStatus', 'getSelectAssessmentInFolder',
            'getSelectAssessmentInProject','getSelectAssessmentInUnitImplementation','getSelectAssessmentInUnitSpecification', 'getUpdateAssessment'
        ];

        // TODO: postCreateAssessment|postUpdateAssessment need more logic
        $ruleRegexp = '(?:' . implode('|', $staffOnly) . ')';
        $this
            ->addRoutePermission($authorizer, $ruleRegexp, new Rule\IsLoggedIn())
            ->addRoutePermission(
                $authorizer,
                $ruleRegexp,
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF)
                )
            );
    }


    /* Getters / Setters
     */

    /**
     * @return UserMapper
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }

    /**
     * @return AssessmentMapperProxy
     */
    protected function getAssessmentMapperProxy()
    {
        return $this->injector->get('assessmentDataSource');
    }

    /**
     * @return UnitImplMapperProxy
     */
    protected function getUnitImplMapperProxy()
    {
        return $this->injector->get('unitImplementationDataSource');
    }

    /**
     * @return UnitSpecMapperProxy
     */
    protected function getUnitSpecMapperProxy()
    {
        return $this->injector->get('unitSpecificationDataSource');
    }

    /**
     * @return StudentSubmissionMapper
     */
    protected function getStudentSubmissionMapper()
    {
        return $this->getMapperFactory()->get(StudentSubmissionMapper::class);
    }

    /**
     * @return SubmissionReturnMapper
     */
    protected function getSubmissionReturnMapper()
    {
        return $this->getMapperFactory()->get(SubmissionReturnMapper::class);
    }

    /**
     * @return StorageMapper
     */
    protected function getStorageMapper()
    {
        return $this->getMapperFactory()->get(StorageMapper::class);
    }

    /**
     * @return FolderMapper
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }

    /**
     * @return Participant\DataSource
     */
    protected function getParticipantMapperProxy()
    {
        return $this->injector->get('participantDataSource');
    }
}
