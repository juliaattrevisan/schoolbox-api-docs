<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Response;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Role;
use \Twig_Loader_Chain;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use \External_UserInterface;


use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use \Schoolbox\Learning\Assessment\Participant;
use \Schoolbox\Learning\Assessment\DueWork\TemporaryDataDecorator;
use \Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use \Schoolbox\Learning\Assessment\DueWork\Submission\Model as Submission;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\Model as Feedback;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as SubmissionFile;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\File\Model as FeedbackFile;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan as Plagscan;


class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningResponse';

    /**
     * @inheritdoc
     */
    public function initRoutes(Router $router, $basePath)
    {
        $routes = [
            'GET' => [

                /* Get a list of Responses for a particular Student and Assessment
                 * Presented as a more succinct Submission History
                 */
                '/assessment/{assessmentId}/{studentId}/responses' => 'getResponseList',

                /* View a Response in the Inline Grader
                 */
                '/responses/{responseId}'                          => 'getParticularResponse',

                /* View a Response File's plagscan report
                 */
                '/responses/plagscan/{plagscanId}'                 => 'getPlagscanReport',

                /* Download an annotated crocodoc
                 */
                '/responses/{responseId}/annotated{/fileId}'       => 'getCrocodocDownload',
            ],
            'POST' => []
        ];

        foreach ($routes['GET'] as $path => $alias) {
            $this->addGetRoute($router, $alias, $basePath, $path);
        }

        foreach ($routes['POST'] as $path => $alias) {
            $this->addPostRoute($router, $alias, $basePath, $path);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $ruleRegexp = '(?:getResponseList|getResponse|getCrocodocDownload)';
        $this
            ->addRoutePermission($authorizer, $ruleRegexp, new Rule\IsLoggedIn())
            ->addRoutePermission($authorizer, $ruleRegexp, new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF)
            ));
    }

    /**
     * @inheritdoc
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload($matcher, '(?:getResponseList)', function ($params) {
            try {
                $student = $this->getUserMapper()->findById($params['studentId']);
            } catch (MapperException $e) {
                $student = $this->getUserMapper()->createModel();
            }

            return [
                'assessment' => $this->getAssessmentMapperProxy()->findById($params['assessmentId']),
                'student'    => $student,
            ];
        });


        /* TODO: Arbitrarily assume 326 for now
         * TODO: Generate a populated response for now
         */
        $this->addRoutePreload($matcher, '(?:getParticularResponse)', function ($params) {
            $assessment = $this->getAssessmentMapperProxy()->findById(326);

            $this->loadParticipantsIntoAssessment($assessment);

            $response = $this->generateResponse1()->setAssessable($assessment);

            return [
                'response' => $response,
            ];
        });

        /* TODO: Arbitrarily assume 326 for now
         * TODO: Generate a populated response for now
         */
        $this->addRoutePreload($matcher, '(?:getCrocodocDownload)', function ($params) {
            $response = $this->generateResponse1();

            /* Factory to figure out which fragment is required
             */
            foreach ($response->getAllFiles() as $currentFile) {
                if ($currentFile->getId() == $params['fileId']) {
                    return [
                        'response' => $response,
                        'file' => $currentFile
                    ];
                }
            }

            return [
                'response' => $response,
                'file' => $response->getAllFiles()->first()
            ];
        });

        $this->addRoutePreload($matcher, '(?:getPlagscanReport)', function ($params) {

            /* @var Plagscan $document */
            try {
                $plagscanDocument = \Plagscan_DocumentMapper::getInstance()->findOneByColumn('plagscan_id', $params['plagscanId']);
            }
            catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
            }

            return [
                'plagscan' => $plagscanDocument,
            ];
        });
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
                'errorMapper' => $this->getMapperFactory()->getLazy(ErrorMapper::class),
            ])
        );
    }

    /**
     * Recursive function for loading participation
     * TODO put this in the mapper
     *
     * @param IAssessable $a
     */
    private function loadParticipantsIntoAssessment(IAssessable $a)
    {
        $a->setAllParticipants($this->getParticipantMapperProxy()->findForAssessment($a));

        if ($a instanceof Project) {
            foreach ($a->getAssessables() as $b) {
                // recurse
                $this->loadParticipantsIntoAssessment($b);
            }
        }
    }

    /**
     * Temporary populator
     *
     * @return Submission
     */
    private function generateResponse1()
    {
        $hodes = \Schoolbox\UserMapper::getInstance()->findById(230);

        /* First file
         * Online Submission
         */
        $plagscan1 = new Plagscan();
        $plagscan1
            ->setId(1)
            ->setPlagscanId(4396294)
            ->setStatus(3)
            ->setScore('45%')
            ->setHtml('<div><strong>YOU CHEATED</strong></div>');

        $storage1 = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash('d3f0f82be30a7da942fa00792d221a7eddff43bd');

        $submissionFile1 = new SubmissionFile();
        $submissionFile1
            ->setId(52330000000)
            ->setStorage($storage1)
            ->setFilename($storage1->getFilename())
            ->setPlagscan($plagscan1);

        /* Second file
         * Crocodocable pdf
         */
        $plagscan2 = new Plagscan();
        $plagscan2
            ->setId(2)
            ->setPlagscanId(4396431)
            ->setStatus(-1);

        $storage2 = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash('603b535ff1c451df9013b392c59ed60ca36d5c89');

        $submissionFile2 = new SubmissionFile();
        $submissionFile2
            ->setId(133450000000)
            ->setStorage($storage2)
            ->setCrocodocId('768f4013-71d1-410e-bb2b-8e9d312d9dbc')
            ->setFilename($storage2->getFilename())
            ->setPlagscan($plagscan2);

        /* Third file
         * Image
         */
        $plagscan3 = new Plagscan();
        $plagscan3
            ->setId(3)
            ->setPlagscanId(4396443)
            ->setStatus(1);

        $storage3 = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash('efb4b418c6c963dc8760e9676986a4e99706f636');

        $submissionFile3 = new SubmissionFile();
        $submissionFile3
            ->setId(564560000000)
            ->setStorage($storage3)
            ->setFilename($storage3->getFilename())
            ->setPlagscan($plagscan3);

        /* Fourth file
         * Crocodocable pptx
         */
        $plagscan4 = new Plagscan();
        $plagscan4
            ->setId(4)
            ->setPlagscanId(4396470)
            ->setStatus(2);

        $storage4 = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash('a351bf467f221eead2d0a184db35d13cc86a43e3');

        $submissionFile4 = new SubmissionFile();
        $submissionFile4
            ->setId(642670000000)
            ->setStorage($storage4)
            ->setCrocodocId('9bd30ca7-3b0b-471d-a887-707773533c60')
            ->setFilename($storage4->getFilename())
            ->setPlagscan($plagscan4);

        /* Fifth file
         * Download only; cannot preview
         */
        $plagscan5 = new Plagscan();
        $plagscan5
            ->setId(5)
            ->setPlagscanId(4396541)
            ->setScore('67%')
            ->setStatus(3);

        $storage5 = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash('724f3f7e395e639bfa2e5cb58589f8dcf88b4be9');

        $submissionFile5 = new SubmissionFile();
        $submissionFile5
            ->setId(30193840000000)
            ->setStorage($storage5)
            ->setFilename($storage5->getFilename())
            ->setPlagscan($plagscan5);

        /* Sixth file
         * Video
         */
        $storage6 = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash('287d6486bce26b39b03d959d4ff8f8625d5a811b');

        $submissionFile6 = new SubmissionFile();
        $submissionFile6
            ->setId(3459068900000000)
            ->setStorage($storage6)
            ->setFilename($storage6->getFilename());

        /* Seventh file
         * Audio
         */
        $storage7 = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash('3c3594d7a353225aed0335a78c7c829e2be4b135');

        $submissionFile7 = new SubmissionFile();
        $submissionFile7
            ->setId(5564226)
            ->setStorage($storage7)
            ->setFilename($storage7->getFilename());

        /* Eighth file
         * Crocodocable file that requires uploading
         */
        $storage8 = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash('50d1bfcdf0b16c800db2df44c6f866688f6fd50d');

        $submissionFile8 = new SubmissionFile();
        $submissionFile8
            ->setId(364859260000000)
            ->setStorage($storage8)
            ->setCrocodocId(null)
            ->setFilename($storage8->getFilename() . ' - needs crocodoc upload');

        /* Accumulate
         */
        $response = new Submission();
        $response
            ->setId(2)
            ->setSubmittedBy($hodes)
            ->setSubmittedOnBehalfOf($hodes)
            ->setAllFiles((new \Schoolbox\Collection())
                ->add($submissionFile1)
                ->add($submissionFile2)
                ->add($submissionFile3)
                ->add($submissionFile4)
                ->add($submissionFile5)
                ->add($submissionFile6)
                ->add($submissionFile7)
                ->add($submissionFile8)
            )
            ->setCreatedAt(new \Alaress_Date_Format('2016-11-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-11-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setSubmittedAt(new \Alaress_Date_Format('2016-11-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        return $response;
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
     * @return Participant\DataSource
     */
    protected function getParticipantMapperProxy()
    {
        return $this->injector->get('participantDataSource');
    }
}
