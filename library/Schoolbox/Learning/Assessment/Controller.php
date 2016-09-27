<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment;

use Alaress\Web\Controller\Base;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Assessment\Component\SubmissionBox\WorkTypeMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\User as User;
use Schoolbox\UserMapper;
use \Alaress_Date_Format;
use \Alaress_Date_NullDate;
use \Schoolbox\Resource\Folder\Mapper as FolderMapper;

use \Schoolbox\Resource\Storage\Model as Storage;

use \Schoolbox\Learning\Assessment\Participant;
use \Schoolbox\Learning\Assessment\Project as Project;

use \Schoolbox\Learning\Assessment\Summator\Collection as SummatorCollection;
use \Schoolbox\Learning\Assessment\Summator\WeightedAverage as SummatorWeightedAverage;
use \Schoolbox\Learning\Assessment\Summator\Average as SummatorAverage;
use \Schoolbox\Learning\Assessment\Summator\Maximum as SummatorMaximum;
use \Schoolbox\Learning\Assessment\Summator\Minimum as SummatorMinimum;

use \Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use \Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkSubmission;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as DueWorkSubmissionFile;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan as DueWorkSubmissionFilePlagscan;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\File\Model as DueWorkFeedbackFile;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use \Schoolbox\Learning\Assessment\DueWork\File as DueWorkFile;
use \Schoolbox\Learning\Assessment\DueWork\Type as DueWorkType;

use \Schoolbox\Learning\Assessment\Task\Model as Task;
use \Schoolbox\Learning\Assessment\Task\Completion\Model as TaskCompletion;
use \Schoolbox\Learning\Assessment\Task\Feedback\Model as TaskFeedback;

use \Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use \Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;
use \Schoolbox\Learning\Assessment\Quiz\Feedback\Model as QuizReview;

class Controller extends Base
{

    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * @var FolderMapper
     */
    protected $folderMapper;

    /**
     * Controller constructor.
     * @param FolderMapper $folderMapper
     * @param ErrorMapper $errorMapper
     */
    public function __construct(FolderMapper $folderMapper, ErrorMapper $errorMapper)
    {
        $this->setErrorMapper($errorMapper);
        $this->setFolderMapper($folderMapper);
    }

    /**
     * Select an Assessment
     *
     * This is when you chooze what type of assessment it is, which
     * returns you an appropriate fragment of common+assessmentType
     * form inputs for you to fill out
     *
     * @param $folderId
     */
    public function getSelectAssessment($folderId)
    {
        try {
            $folder = $this->getFolderMapper()->findById($folderId);
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_CATEGORY_DELETED);
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningAssessment/create.twig', [
            'folder' => $folder,
            'types' => new Types(),
        ]));
    }

    /**
     * Return a fragment corresponding to the type of assessment you choze
     */
    public function postSelectAssessment()
    {
        try {
            $folder = $this->getFolderMapper()->findById($this->getRequest()->post['folderId']);
        }
        catch (MapperException $e) {
            $this->sendError('Could not create assessment', [ 'select a valid folder' ]);
            return;
        }

        /* TODO: Remove this stub
         * I added it to save myself choosing dates all the time
         */
        $assessment = (new DueWork())
            ->setPublishAt(Alaress_Date_Format::now())
            ->setOpenAt(Alaress_Date_Format::now())
            ->setDueAt(Alaress_Date_Format::now())
            ->setCloseAt(Alaress_Date_Format::now());

        /* Common data among all assessmentType fragments
         */
        $data = [
            'types' => new Types(),
            'assessment' => $assessment,
            'curriculumNodes' => $folder->getCurriculumNodes(),
            'allMarkTypes' => $this->getAllMarkTypes()
        ];

        /* Select the fragment to return
         */
        switch($this->getRequest()->post['assessmentType']) {
            case Types::ASSESSMENT_PROJECT:
                $data['allSummators'] = new SummatorCollection();
                $this->getResponse()->content->set($this->buildUnthemedHtmlOutput('@learningAssessment/fragments/project/create.twig', $data));
                break;

            case Types::ASSESSMENT_DUE_WORK:
                $this->getResponse()->content->set($this->buildUnthemedHtmlOutput('@learningAssessment/fragments/dueWork/create.twig', $data));
                break;

            case Types::ASSESSMENT_TASK:
                $this->getResponse()->content->set($this->buildUnthemedHtmlOutput('@learningAssessment/fragments/task/create.twig', $data));
                break;

            case Types::ASSESSMENT_QUIZ:
                $this->getResponse()->content->set($this->buildUnthemedHtmlOutput('@learningAssessment/fragments/quiz/create.twig', $data));
                break;

            default:
                break;
        }
    }

    /**
     * Save the Assessment's data
     *
     * This saves the combination of common+assessmentType-specific details
     */
    public function postCreateAssessment()
    {
        $assessment = null;

        try {
            $folder = $this->getFolderMapper()->findById($this->getRequest()->post['folderId']);
        }
        catch (MapperException $e) {
            $this->sendError('Could not create assessment', [ 'select a valid Folder' ]);
            return;
        }

        /* Figure out what kind of assessment was chosen
         * and spawn a new instance of it to manipulate
         */
        switch($this->getRequest()->post['assessmentType']) {
            case Types::ASSESSMENT_PROJECT:
                $assessment = new Project(new SummatorWeightedAverage());
                break;

            case Types::ASSESSMENT_DUE_WORK:
                $assessment = new DueWork();
                break;

            case Types::ASSESSMENT_TASK:
                $assessment = new Task();
                break;

            case Types::ASSESSMENT_QUIZ:
                $assessment = new Quiz();
                break;

            default:
                $this->sendError('Could not create assessment', [ 'select a valid assessment type' ]);
                return;
        }

        /* Check dates are set to something
         */
        $publishAt = trim($this->getRequest()->post['publishDate'] . ' ' . $this->getRequest()->post['publishTime']);
        $openAt = trim($this->getRequest()->post['openDate'] . ' ' . $this->getRequest()->post['openTime']);
        $dueAt = trim($this->getRequest()->post['dueDate'] . ' ' . $this->getRequest()->post['dueTime']);
        $closeAt = trim($this->getRequest()->post['closeDate'] . ' ' . $this->getRequest()->post['closeTime']);

        if (!strlen($publishAt)) {
            $this->sendError('Could not create assessment', [ 'set a publish date' ]);
            return;
        }

        if (!strlen($openAt)) {
            $this->sendError('Could not create assessment', [ 'set an open date' ]);
            return;
        }

        if (!strlen($dueAt)) {
            $this->sendError('Could not create assessment', [ 'set a due date' ]);
            return;
        }

        if (!strlen($closeAt)) {
            $this->sendError('Could not create assessment', [ 'set a close date' ]);
            return;
        }

        /* Start assembling assessment instance
         */
        $assessment
            ->setTitle($this->getRequest()->post['title'])
            ->setDescription($this->getRequest()->post['description'])
            ->setCreator($_SESSION['user'])
            ->setWeight($this->getRequest()->post['weight'])
            ->setPublishAt(Alaress_Date_Format::newLocal($publishAt))
            ->setOpenAt(Alaress_Date_Format::newLocal($openAt))
            ->setDueAt(Alaress_Date_Format::newLocal($dueAt))
            ->setCloseAt(Alaress_Date_Format::newLocal($closeAt))
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());

        /* Verify that dates are temporally correct
         */
        if ($assessment->isValidDates()) {

            /* TODO: Actually save data here
             */
            $assessment->setId(5568);

            /* If it were a project, go back to the project's Update screen
             * Otherwise return to the homepage
             */
            if ($assessment instanceof Project) {
                $this->getResponse()->content->set($this->buildUnthemedHtmlOutput('@learningAssessment/fragments/project/createAssessment.twig', [
                    'types' => new Types(),
                    'assessmentId' => $assessment->getId(),
                    'allReturnUrls' => $this->getAllReturnUrls($assessment->getId()),
                ]));

                return;
            }
            else {
                $this->getResponse()->redirect->to("/homepage/{$folder->getId()}");
            }
        }
        else {
            $this->sendError('Could not create assessment', ['fill in all date fields correctly']);
            return;
        }
    }

    /**
     * Update an existing Assessment's details
     *
     * @param int $assessmentId
     */
    public function getUpdateAssessment($assessmentId)
    {

        /* TODO: Actually retrieve $assessmentId data here
         */
        $assessment = new Project(new SummatorAverage());
        $assessment
            ->setId($assessmentId)
            ->setTitle('Find Diablo')
            ->setDescription('The things you need to do in order to slay the prime evil')
            ->setCreator($_SESSION['user'])
            ->setAllParticipants(new Collection())
            ->setWeight(100)
            ->setAssessables((new Collection())
                ->add((new DueWork())
                    ->setId(2)
                    ->setTitle('Enter the Realm of Terroarrrr')
                    ->setDescription('Diablo was found in the land of terroarrrrr! Find him, and whack him!')
                    ->setType((new DueWorkType())
                        ->setId(1)
                        ->setName('Essay'))
                    ->setCreator((new User)->setId(150)->setFirstname('Anelie')->setLastname('Somodio'))
                    ->setPublishAt(Alaress_Date_Format::now())
                    ->setOpenAt(Alaress_Date_Format::now())
                    ->setDueAt(Alaress_Date_Format::now())
                    ->setCloseAt(Alaress_Date_Format::now())
                    ->setCreatedAt(Alaress_Date_Format::now())
                    ->setUpdatedAt(Alaress_Date_Format::now()))
                ->add((new DueWork())
                    ->setId(2)
                    ->setTitle('Enter the Realm of Terroarrrr')
                    ->setDescription('Diablo was found in the land of terroarrrrr! Find him, and whack him!')
                    ->setType((new DueWorkType())
                        ->setId(1)
                        ->setName('Essay'))
                    ->setCreator((new User)->setId(150)->setFirstname('Anelie')->setLastname('Somodio'))
                    ->setPublishAt(Alaress_Date_Format::now())
                    ->setOpenAt(Alaress_Date_Format::now())
                    ->setDueAt(Alaress_Date_Format::now())
                    ->setCloseAt(Alaress_Date_Format::now())
                    ->setCreatedAt(Alaress_Date_Format::now())
                    ->setUpdatedAt(Alaress_Date_Format::now())))
            ->setPublishAt(Alaress_Date_Format::now())
            ->setOpenAt(Alaress_Date_Format::now())
            ->setDueAt(Alaress_Date_Format::now())
            ->setCloseAt(Alaress_Date_Format::now())
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());

        /* TODO: Determine type
         */
        $assessmentType = Types::ASSESSMENT_PROJECT;

        switch ($assessmentType) {
            case Types::ASSESSMENT_PROJECT:
                $template = 'updateProject.twig';
                break;
            case Types::ASSESSMENT_DUE_WORK:
                $template = 'updateDueWork.twig';
                break;
            case Types::ASSESSMENT_TASK:
                $template = 'updateTask.twig';
                break;
            case Types::ASSESSMENT_QUIZ:
                $template = 'updateQuiz.twig';
                break;
            default:
                $this->sendError('Could not create assessment', [ 'select a valid assessment type' ]);
                return;
        }

        /* TODO: Figure out assessment's relationship to $folder in some way
         */
        try {
            $folder = $this->getFolderMapper()->findById(43);
        }
        catch (MapperException $e) {
            $this->sendError('Could not create assessment', [ 'select a valid Folder' ]);
            return;
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningAssessment/' . $template, [
            'assessment' => $assessment,
            'assessmentType' => $assessmentType,
            'folder' => $folder,
            'allReturnUrls' => $this->getAllReturnUrls($assessmentId),
            'curriculumNodes' => $folder->getCurriculumNodes(),
            //            'allMarkTypes' => $this->getAllMarkTypes(),
            'allSummators' => new SummatorCollection(),
        ]));
    }

    /**
     * Save the data when creating a specific kind of Assessment
     *
     * This happens after the common details are saved and after
     * you've chosen one of project, due work, task, or quiz
     *
     * @param $assessmentId
     */
    public function postUpdateAssessment($assessmentId)
    {
        /* TODO: Actually save data here
         * TODO: Save the attributes common among all assessables
         */
        new \Alaress_Probe($assessmentId, $this->getRequest()->post, "Redirect to <a href='".$this->getRouter()->generate('learningAssessment.getUpdateAssessment', ['assessmentId' => $assessmentId])."'>this url</a>");
    }

    /**
     * Create an Assessment within a particular Project
     *
     * @param $assessmentId
     * @param $assessmentType
     */
    public function getCreateAssessmentWithinProject($assessmentId, $assessmentType)
    {

        /* TODO: Actually retrieve $assessmentId data here
         */
        $project = new Project(new SummatorAverage());
        $project
            ->setId($assessmentId)
            ->setTitle('Find Diablo')
            ->setDescription('The things you need to do in order to slay the prime evil')
            ->setCreator($_SESSION['user'])
            ->setAllParticipants(new Collection())
            ->setWeight(100)
            ->setAssessables((new Collection())
                ->add((new DueWork())
                    ->setId(2)
                    ->setTitle('Enter the Realm of Terroarrrr')
                    ->setDescription('Diablo was found in the land of terroarrrrr! Find him, and whack him!')
                    ->setType((new DueWorkType())
                        ->setId(1)
                        ->setName('Essay'))
                    ->setCreator((new User)->setId(150)->setFirstname('Anelie')->setLastname('Somodio'))
                    ->setPublishAt(Alaress_Date_Format::now())
                    ->setOpenAt(Alaress_Date_Format::now())
                    ->setDueAt(Alaress_Date_Format::now())
                    ->setCloseAt(Alaress_Date_Format::now())
                    ->setCreatedAt(Alaress_Date_Format::now())
                    ->setUpdatedAt(Alaress_Date_Format::now()))
                ->add((new DueWork())
                    ->setId(2)
                    ->setTitle('Enter the Realm of Terroarrrr')
                    ->setDescription('Diablo was found in the land of terroarrrrr! Find him, and whack him!')
                    ->setType((new DueWorkType())
                        ->setId(1)
                        ->setName('Essay'))
                    ->setCreator((new User)->setId(150)->setFirstname('Anelie')->setLastname('Somodio'))
                    ->setPublishAt(Alaress_Date_Format::now())
                    ->setOpenAt(Alaress_Date_Format::now())
                    ->setDueAt(Alaress_Date_Format::now())
                    ->setCloseAt(Alaress_Date_Format::now())
                    ->setCreatedAt(Alaress_Date_Format::now())
                    ->setUpdatedAt(Alaress_Date_Format::now())))
            ->setPublishAt(Alaress_Date_Format::now())
            ->setOpenAt(Alaress_Date_Format::now())
            ->setDueAt(Alaress_Date_Format::now())
            ->setCloseAt(Alaress_Date_Format::now())
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningAssessment/createWithinProject.twig', [
            'types' => new Types(),
            'project' => $project,
            'assessment' => [],
            'assessmentType' => $assessmentType,
            'returnUrl' => $this->getRequest()->query['return_url']
        ]));
    }

    /**
     * Save the data when creating an assessment within a Project
     */
    public function postCreateAssessmentWithinProject()
    {
        /* TODO: Actually save data here then redirect back to project update screen
         */
        $returnUrl = (new Redirect())->decode($this->getRequest()->post['returnUrl']);

        new \Alaress_Probe($_POST, $this->getRequest()->post, "Redirect to <a href='{$returnUrl}'>{$returnUrl}</a>");
    }

    /* Utility methods - these can probably be refactored elsewhere
     */

    /**
     * Find all the returnUrls
     *
     * @param int $assessmentId
     * @return array
     */
    private function getAllReturnUrls($assessmentId)
    {
        /* Figure out a returnUrl
         *
         * When spawning a Project, its constituent Assessables return to this url
         * after they are spawned
         */
        $returnUrl = $this->getRouteIfAuthorized('learningAssessment.getUpdateAssessment', ['assessmentId' => $assessmentId]);
        $suffix = '?return_url=' . (new Redirect())->encode($returnUrl);

        /* TODO: Refactor this better
         */
        $allReturnUrls = [];

        $allTypes = new Types();

        foreach ($allTypes->getTypes() as $title => $index) {
            $allReturnUrls[$index] = $this->getRouter()->generate('learningAssessment.getCreateAssessmentWithinProject', ['assessmentId' => $assessmentId, 'assessmentType' => $index]) . $suffix;
        }

        return $allReturnUrls;
    }

    /**
     * All the possible Mark Types to chooze from
     * TODO: Replace with actual objects
     */
    private function getAllMarkTypes()
    {
        return [
            [
                'id'   => 1,
                'name' => 'None'
            ],
            [
                'id'   => 2,
                'name' => 'Percentage'
            ],
            [
                'id'   => 3,
                'name' => 'Letter'
            ]
        ];
    }

    /**
     * Send a neat and tidy error back to the client
     *
     * TODO: I made a few fixes to this which need to be traitored
     *
     * @param $problem
     * @param array $resolutions
     * @param int $statusCode
     * @throws SchoolboxException
     */
    protected function sendError($problem, array $resolutions = [], $statusCode = 400)
    {
        // cast exception to primitives
        if ($problem instanceof \Alaress\Web\Controller\Exception) {
            $resolutions = $problem->getReasons();
            $problem     = $problem->getMessage();
        }

        if (count($resolutions)) {
            $problem .= ". You need to ";
        }

        $this->getResponse()->status->set($statusCode);

        if (!$this->getRequest()->isXhr()) {
            throw $this->getErrorMapper()->generateException(105, null, $problem . implode(', ', $resolutions) . '.');
        }

        $this->getResponse()->content->set([
            'template' => '@course/mage-error.twig',
            'format'   => 'nochrome',
            'data'     => [
                'error' => [
                    'problem' => $problem,
                    'reasons' => $resolutions
                ]
            ]
        ]);
    }













//        /* TODO: Actually save data here
//         */
//        $project = new Project(new SummatorAverage());
//        $project
//            ->setId(5567)
//            ->setCreator($_SESSION['user'])
//            ->setCreatedAt(Alaress_Date_Format::now())
//            ->setUpdatedAt(Alaress_Date_Format::now());
//
//        $assessmentId = $project->getId();
//
//        /* Final payload
//         */
//        $data = [
//            'assessmentId' => $project->getId(),
//            'allReturnUrls' => $this->getAllReturnUrls($assessmentId),
//
//            /* The next step of saving once you've chosen a type that isn't a project
//             */
//            'updateRoute' => $this->getRouter()->generate('learningAssessment.postUpdateAssessment', ['assessmentId' => $assessmentId]),
//        ];
//
//        $this->getResponse()->content->set($this->buildJsonOutput($data));













//

//


//
//    /**
//     * Delete an assessment
//     *
//     * @param $assessmentId
//     */
//    public function postDeleteAssessment($assessmentId)
//    {
//        /* TODO: soft-delete this assessment
//         */
//    }





    /**
     * Displays information regarding an assessment
     *
     * @api   GET /learning/assessment/:assessmentId/status
     * @level 165
     * @param IAssessable $assessmentId
     * @todo implement IAssessable interface
     */
    public function getStatus($assessmentId)
    {
        // this is a teacher page to show a list of students (cohort list)
        // with each student summarised (single column of the markbook)
        // same data structure as mark book, for a single assessable

        // Replaces /submission/viewSubmission/:dueWorkId
        //       eg /submission/viewSubmission/56
        // Replaces /task/progressReport/:folderId/:unitId
        //       eg /task/progressReport/177/11


        // ==================== test URLs: ======================
        // http://192.168.36.10/learning/assessment/1/status   - project with 1 quiz, 1 dueWork + 3 tasks
        // http://192.168.36.10/learning/assessment/2/status   - quiz
        // http://192.168.36.10/learning/assessment/3/status   - dueWork
        // http://192.168.36.10/learning/assessment/4/status   - task1
        // http://192.168.36.10/learning/assessment/5/status   - task2
        // http://192.168.36.10/learning/assessment/6/status   - task3

        // TODO - generate data

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningAssessment/status.twig', [
            'breadcrumbs'      => self::getDummyBreadcrumbs(),
            'cohortList'       => self::getDummyCohortList(),
            'assessment'       => self::getDummyAssessment($assessmentId),
        ]));
    }


    /**
     * Displays specified student's progress in relation to a specified assessable
     *
     * @api   GET /learning/assessment/:assessmentId/summary/:studentId
     * @level 165
     * @param IAssessable|int $assessmentId  TODO - replace int with IAssessable in Module
     * @param User $student
     * @todo implement IAssessable interface
     */
    public function getSummary($assessmentId, User $student)
    {
        // Replaces /submission/submissionHistory/dueWorkId/studentId
        //       eg /submission/submissionHistory/56/219


        // ==================== test URLs: ======================
        // http://192.168.36.10/learning/assessment/1/summary/230   - project with 1 quiz, 1 dueWork + 3 tasks
        // http://192.168.36.10/learning/assessment/2/summary/230   - quiz
        // http://192.168.36.10/learning/assessment/3/summary/230   - dueWork
        // http://192.168.36.10/learning/assessment/4/summary/230   - task

        $twigData = [
            'breadcrumbs' => self::getDummyBreadcrumbs(),
            'student'     => $student,
            'assessment'  => self::getDummyAssessment($assessmentId),
        ];

        // Is it a due work?
        if(in_array($assessmentId, [2])){
            // Rubric
            $submissionBox = SubmissionBoxMapper::getInstance()->findById(56);
            $twigData['rubric'] = RubricMapper::getInstance()->findBySubmissionBoxAndStudent($submissionBox, $student)->toContext();
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningAssessment/summary.twig', $twigData));
    }

    /**
     * Displays a form enabling a student to submit items to an assessment
     *
     * @api   GET /learning/assessment/:assessmentId/submit/:studentId
     * @level 165
     * @param IAssessable $assessment
     * @param User $student
     * @todo implement IAssessable interface
     */
    public function getSubmitForm($assessment, User $student)
    {
        // TODO - generate data

        // just the submission_box (assessable) submit form
        // just needs student object and assessable object

        // Replaces /submission/submitOnline/:dueWorkId/:studentId
        //       eg /submission/submitOnline/56/219

        // ==================== test URLs: ======================
        // http://192.168.36.10/learning/assessment/1/submit/230   - project with 1 quiz, 1 dueWork + 3 tasks
        // http://192.168.36.10/learning/assessment/2/submit/230   - quiz
        // http://192.168.36.10/learning/assessment/3/submit/230   - dueWork
        // http://192.168.36.10/learning/assessment/4/submit/230   - task

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningAssessment/submitForm.twig', [
            'breadcrumbs' => self::getDummyBreadcrumbs(),
            'assessment'  => self::getDummyAssessment($assessment),
            'student'     => $student,
        ]));
    }


    /* Getters/Setters
     */

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->errorMapper;
    }

    /**
     * @param ErrorMapper $errorMapper
     * @return static
     */
    protected function setErrorMapper(ErrorMapper $errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
    }

    /**
     * @return FolderMapper
     */
    protected function getFolderMapper()
    {
        return $this->folderMapper;
    }

    /**
     * @param FolderMapper $folderMapper
     */
    protected function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }



    // ================ DUMMY DATA GENERATION ===============================================


    /**
     * Temp method to generate dummy breadcrumbs
     *
     * @todo remove this method when no longer required
     * @return array
     */
    public static function getDummyBreadcrumbs(){
        return [
            [
                'href'  => '/learning/assessment/1/status',
                'label' => 'FirstCrumb',
            ],
            [
                'href'  => '/learning/assessment/2/status',
                'label' => 'SecondCrumb',
            ],
            [
                'href'  => '/learning/assessment/3/status',
                'label' => 'ActiveCrumb',
            ],
        ];
    }

    /**
     * Temp method to return a list of students is a class.
     * @todo remove when done
     * @return Collection|User[]
     */
    public static function getDummyCohortList(){
        $internalIds = [
            230,
            239,
            242,
            245,
        ];
        return UserMapper::getInstance()->findByColumn('id', $internalIds);
    }


    /**
     * Temp method to generate dummy assessment
     *
     * @param $assessmentId      1 => project
     *                           2 => dueWork
     *                           3 => quiz
     *                           4 => task1
     *                           5 => task2
     *                           6 => task3
     * @todo remove this method when no longer required
     * @return array
     */
    public static function getDummyAssessment($assessmentId){
        $creator = UserMapper::getInstance()->findById(151);    // Zane Smith (teacher)

        $assessment = [
            'id'              => $assessmentId,
            'publish_date'    => new Alaress_Date_Format('2016/08/01','Y/m/d'),
            'open_date'       => new Alaress_Date_Format('2016/08/08','Y/m/d'),
            'due_date'        => new Alaress_Date_Format('2016/08/15','Y/m/d'),
            'close_date'      => new Alaress_Date_Format('2016/08/22','Y/m/d'),
            'weight'          => 40,
            'creator'         => $creator,
            'mark_visibility' => 2,
            'responses'       => self::getDummyResponses($assessmentId),
            'created_at'      => new Alaress_Date_Format('2016/08/08','Y/m/d'),
            'updated_at'      => new Alaress_Date_Format('2016/08/15','Y/m/d'),
            'deleted_at'      => new Alaress_Date_NullDate(),
        ];

        switch($assessmentId){
            case 1: // project
                $assessment = array_merge($assessment, [
                    'assessment_type' => 'project',
                    'title'           => 'mProject',
                    'description'     => 'This is a project. It contains assessables',
                    'mark_scheme'     => self::getDummyMarkScheme(6),
                ]);
                return array_merge($assessment, [
                    'mark_summator' => [
                        'calculateMark' => 85.00,
                    ],
                    'assessables'   => [
                        self::getDummyAssessment(2),
                        self::getDummyAssessment(3),
                        self::getDummyAssessment(4),
                        self::getDummyAssessment(5),
                        self::getDummyAssessment(6),
                    ],
                ]);
            case 2: // dueWork
                $assessment = array_merge($assessment, [
                    'assessment_type' => 'dueWork',
                    'title'           => 'mDueWork',
                    'description'     => 'This is a dueWork. It does not contain assessables, but has extra dueWork-related info',
                    'mark_scheme'     => self::getDummyMarkScheme(2),
                ]);
                return array_merge($assessment, [
                    'accept_submission'  => true,
                    'multi_submission'   => true,
                    'skip_unsubmitted'   => 0,
                    'work_type'          => WorkTypeMapper::getInstance()->findById(1),
                    'files'              => [
                        self::getDummyDueWorkFile(1),
                        self::getDummyDueWorkFile(2),
                    ],
                ]);
            case 3: // quiz
                $assessment = array_merge($assessment, [
                    'assessment_type' => 'quiz',
                    'title'           => 'mQuiz',
                    'description'     => 'This is a quiz. It does not contain assessables, but has extra quiz-related info',
                    'mark_scheme'     => self::getDummyMarkScheme(1),
                ]);
                return array_merge($assessment, [
                    'sections' => [
                        self::getDummyQuizSection(1),
                        self::getDummyQuizSection(2),
                    ],
                    'attempts' => [
                        self::getDummyQuizAttempt(1),
                        self::getDummyQuizAttempt(2),
                        self::getDummyQuizAttempt(3),
                    ]
                ]);
            case 4: // task1
                $assessment = array_merge($assessment, [
                    'assessment_type' => 'task',
                    'title'           => 'mTask1',
                    'description'     => 'This is a task. It does not contain assessables, but has extra task-related info',
                    'mark_scheme'     => self::getDummyMarkScheme(3),
                ]);
                return array_merge($assessment, [
                    'link'            => 'https://www.youtube.com/watch?v=vShCnTY1-T0',
                    'isSelfMarkable'  => false,
                ]);
            case 5: // task2
                $assessment = array_merge($assessment, [
                    'assessment_type' => 'task',
                    'title'           => 'mTask2',
                    'description'     => 'This is a task. It does not contain assessables, but has extra task-related info',
                    'mark_scheme'     => self::getDummyMarkScheme(4),
                ]);
                return array_merge($assessment, [
                    'link'            => '',
                    'isSelfMarkable'  => true,
                ]);
            case 6: // task3
            default:
                $assessment = array_merge($assessment, [
                    'assessment_type' => 'task',
                    'title'           => 'mTask3',
                    'description'     => 'This is a task. It does not contain assessables, but has extra task-related info',
                    'mark_scheme'     => self::getDummyMarkScheme(5),
                ]);
                return array_merge($assessment, [
                    'link'            => 'https://www.youtube.com/watch?v=vShCnTY1-T0',
                    'isSelfMarkable'  => true,
                ]);
        }
    }

    /**
     * Temp method to generate dummy student responses
     *
     * @param $assessmentId      1 => project
     *                           2 => dueWork
     *                           3 => quiz
     *                           4 => task1
     *                           5 => task2
     *                           6 => task3
     * @todo remove this method when no longer required
     * @return array
     */
    public static function getDummyResponses($assessmentId)
    {
        switch($assessmentId){
            case 1: // project
                return array_merge(
                    self::getDummyResponses(2),
                    self::getDummyResponses(3),
                    self::getDummyResponses(4),
                    self::getDummyResponses(5),
                    self::getDummyResponses(6)
                );
            case 2: // dueWork
                return [
                    self::getDummySubmission(1),
                    self::getDummySubmission(2),
                    self::getDummySubmission(3),
                ];
            case 3: // quiz
                return [
                    self::getDummyQuizAttempt(1),
                    self::getDummyQuizAttempt(2),
                    self::getDummyQuizAttempt(3),
                ];
            case 4: // task1
                return [ ];
            case 5: // task2
                return [
                    self::getDummyTaskCompletion(1),
                ];
            case 6: // task3
                return [
                    self::getDummyTaskCompletion(1),
                    self::getDummyTaskCompletion(2),
                ];
        }
    }


    /**
     *
     * @param int $dueWorkFileId   enum {1,2}
     * @return array
     */
    public static function getDummyDueWorkFile($dueWorkFileId)
    {
        $dueWorkFile = [
            'id'      => $dueWorkFileId,
            'created_at' => new Alaress_Date_Format('2016/08/08','Y/m/d'),
            'updated_at' => new Alaress_Date_Format('2016/08/15','Y/m/d'),
            'deleted_at' => new Alaress_Date_NullDate(),
        ];
        switch($dueWorkFileId) {
            case 1:
                return array_merge($dueWorkFile, [
                    'hash'     => '8dc741fccc7a612bc0643caa0f1ced422497f3ae',
                    'fileName' => 'Hiking this weekend',
                ]);
            case 2:
            default;
                return array_merge($dueWorkFile, [
                    'hash'     => 'd4e7439b046c63787429176aec1efc930209f5c3',
                    'fileName' => 'articleday3',
                ]);
        }
    }


    /**
     * Generates a dummy dueWork Submission
     *
     * @param int $dueWorkFeedbackId    enum {1,2,3,4}
     * @todo remove when done
     * @return array
     */
    public static function getDummyDueWorkFeedback($dueWorkFeedbackId)
    {
        $dueWorkFeedback = [
            'id'           => $dueWorkFeedbackId,
            'feedbackType' => 'dueWorkFeedback',
            'reviewer'     => UserMapper::getInstance()->findById(151),
            'created_at'   => new Alaress_Date_Format('2016/08/08','Y/m/d'),
            'updated_at'   => new Alaress_Date_Format('2016/08/15','Y/m/d'),
            'deleted_at'   => new Alaress_Date_NullDate(),
        ];
        switch($dueWorkFeedbackId) {
            case 1:
                return array_merge($dueWorkFeedback, [
                    'mark'         => 'A+',
                    'norm_mark'    => 95.00000,
                    'published_at' => new Alaress_Date_NullDate(),
                    'comment'      => 'Excellent work',
                    'files'        => [
                        self::getDummyDueWorkFeedbackFile(1),
                    ],
                ]);
            case 2:
                return array_merge($dueWorkFeedback, [
                    'mark'         => 'C',
                    'norm_mark'    => 70.00000,
                    'published_at' => new Alaress_Date_Format('2016/08/15','Y/m/d'),
                    'comment'      => 'You are going ok',
                    'files' => [
                        self::getDummyDueWorkFeedbackFile(2),
                        self::getDummyDueWorkFeedbackFile(3),
                    ],
                ]);
            case 3:
                return array_merge($dueWorkFeedback, [
                    'mark'         => 'F',
                    'norm_mark'    => 30.00000,
                    'published_at' => new Alaress_Date_NullDate(),
                    'comment'      => 'You need help (this is a draft)',
                    'files'        => [ ],
                ]);
            case 4:
            default:
                return array_merge($dueWorkFeedback, [
                'mark'             => 'F',
                'norm_mark'        => 30.00000,
                'published_at'     => new Alaress_Date_Format('2016/08/15','Y/m/d'),
                'comment'          => 'You need help (this is published)',
                'files'            => [ ],
            ]);
        }
    }


    /**
     * @param int $dueWorkFeedbackFileId   enum {1,2,3}
     * @todo remove when done
     * @return array
     */
    public static function getDummyDueWorkFeedbackFile($dueWorkFeedbackFileId)
    {
        $dueWorkFeedbackFile = [
            'id'           => $dueWorkFeedbackFileId,
            'created_at'   => new Alaress_Date_Format('2016/08/08','Y/m/d'),
            'updated_at'   => new Alaress_Date_Format('2016/08/15','Y/m/d'),
            'deleted_at'   => new Alaress_Date_NullDate(),
        ];
        switch($dueWorkFeedbackFileId){
            case 1:
                return array_merge($dueWorkFeedbackFile, [
                    'crocodocUUID' => null,
                    'hash'         => '1e5b92268bf8f2370c5cf9e6526f1f35c3b329f1',
                    'fileName'     => 'ClassQuestions',
                ]);
            case 2:
                return array_merge($dueWorkFeedbackFile, [
                    'crocodocUUID' => '75ac23be-4210-43cf-93e1-77c172afab78',
                    'hash'         => '4b50da92d887f9e05804f7425125b5064f5cb385',
                    'fileName'     => 'quantum-teleportation',
                ]);
            case 3:
            default:
                return array_merge($dueWorkFeedbackFile, [
                    'crocodocUUID' => '04943e5c-ade8-4e1b-8e54-80c77c6a66f1',
                    'hash'         => '5bdd49d040562d9418ec4fd34a49c78a9eacf08b',
                    'fileName'     => 'questions-for-walter',
                ]);
        }
    }


    /**
     * Generates a dummy dueWork Submission
     *
     * @param int $dueWorkSubmissionId    enum {1,2,3}
     * @todo remove when done
     * @return array
     */
    public static function getDummySubmission($dueWorkSubmissionId)
    {
        $submission = [
            'id'           => $dueWorkSubmissionId,
            'responseType' => 'dueWorkSubmission',
            'created_at'   => new Alaress_Date_Format('2016/08/08','Y/m/d'),
            'deleted_at'   => new Alaress_Date_NullDate(),
        ];
        switch ($dueWorkSubmissionId) {
            case 1:
                return array_merge($submission, [
                    'submitted_by'  => UserMapper::getInstance()->findById(230),
                    'submitted_for' => UserMapper::getInstance()->findById(245),
                    'updated_at'   => new Alaress_Date_Format('2016/08/15','Y/m/d'),
                    'files'         => [
                        self::getDummyDueWorkSubmissionFile(1),
                        self::getDummyDueWorkSubmissionFile(2),
                    ],
                    'feedbacks'     => [
                        self::getDummyDueWorkFeedback(1),
                    ],
                ]);
            case 2:
                return array_merge($submission, [
                    'submitted_by'  => UserMapper::getInstance()->findById(230),
                    'submitted_for' => UserMapper::getInstance()->findById(245),
                    'updated_at'   => new Alaress_Date_Format('2016/08/16','Y/m/d'),
                    'files'         => [
                        self::getDummyDueWorkSubmissionFile(1),
                        self::getDummyDueWorkSubmissionFile(2),
                    ],
                    'feedbacks'     => [
                        self::getDummyDueWorkFeedback(1),
                    ],
                ]);
            case 3:
            default;
                return array_merge($submission, [
                    'submitted_by'  => UserMapper::getInstance()->findById(230),
                    'submitted_for' => UserMapper::getInstance()->findById(245),
                    'updated_at'   => new Alaress_Date_Format('2016/08/17','Y/m/d'),
                    'files'         => [
                        self::getDummyDueWorkSubmissionFile(1),
                        self::getDummyDueWorkSubmissionFile(2),
                    ],
                    'feedbacks'     => [
                        self::getDummyDueWorkFeedback(1),
                    ],
                ]);
        }
    }

    /**
     * Get dummy dueWork Submission File
     *
     * @param int $dueWorkSubmissionFileId      enum {1,2,3}
     * @todo remove when done
     * @return array
     */
    public static function getDummyDueWorkSubmissionFile($dueWorkSubmissionFileId)
    {
        $dueWorkSubmissionFile = [
            'id'           => $dueWorkSubmissionFileId,
            'created_at'   => new Alaress_Date_Format('2016/08/08','Y/m/d'),
            'updated_at'   => new Alaress_Date_Format('2016/08/17','Y/m/d'),
            'deleted_at'   => new Alaress_Date_NullDate(),
        ];

        switch($dueWorkSubmissionFileId) {
            case 1:
                return array_merge($dueWorkSubmissionFile, [
                    'crocodocUUID' => 'e367160c-0202-4034-83f7-e4643a953bbb',
                    'hash'         => '90dd4fb4f06041fdf26b15fdf7a45b5699bf5833',
                    'fileName'     => 'portfolio',
                    'plagScan'     => [ ],
                    'storage'      => self::getDummyStorage(1),
                ]);
            case 2:
                return array_merge($dueWorkSubmissionFile, [
                    'crocodocUUID' => '75ac23be-4210-43cf-93e1-77c172afab78',
                    'hash'         => '4b50da92d887f9e05804f7425125b5064f5cb385',
                    'fileName'     => 'quantum-teleportation',
                    'plagScan'     => [
                        self::getDummyPlagScan(1),
                    ],
                    'storage'      => self::getDummyStorage(2),
                ]);
            case 3:
            default:
                return array_merge($dueWorkSubmissionFile, [
                    'crocodocUUID' => null,
                    'hash'         => '1e5b92268bf8f2370c5cf9e6526f1f35c3b329f1',
                    'filename'     => 'ClassQuestions',
                    'plagScan'     => [
                        self::getDummyPlagScan(2),
                    ],
                    'storage'      => self::getDummyStorage(3),
                ]);
        }
    }

    /**
     * @param int $storageId        enum {1,2,3,4,5,6,7}
     * @return array
     */
    public static function getDummyStorage($storageId){
        $storage = [
            'id'           => $storageId,
            'mimetype'     => self::getDummyMimeType($storageId),
            'created_at'   => new Alaress_Date_Format('2016/08/08','Y/m/d'),
            'updated_at'   => new Alaress_Date_Format('2016/08/17','Y/m/d'),
            'deleted_at'   => new Alaress_Date_NullDate(),
        ];
        switch($storageId){
            case 1:
                return array_merge($storage, [
                    'extension'  => 'docx',
                    'filename'   => 'someFile',
                    'basename'   => 'someFile.docx',
                    'mime'       => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'size'       => 25000,
                    'hash'       => '4b50da92d887f9e05804f7425125b5064f5cb385',
                ]);
            case 2:
                return array_merge($storage, [
                    'extension'  => 'gif',
                    'filename'   => 'abc',
                    'basename'   => 'abc.gif',
                    'mime'       => 'image/gif',
                    'size'       => 25000,
                    'hash'       => '90dd4fb4f06041fdf26b15fdf7a45b5699bf5833',
                ]);
            case 3:
                return array_merge($storage, [
                    'extension'  => 'mpeg',
                    'filename'   => 'myVideo',
                    'basename'   => 'myVideo.mpeg',
                    'mime'       => 'video/mpeg',
                    'size'       => 25000,
                    'hash'       => '90dd4fb4f06041fdf26b15fdf7a45b5699bf5833',
                ]);
            case 4:
                return array_merge($storage, [
                    'extension'  => 'mpeg',
                    'filename'   => 'mAudio',
                    'basename'   => 'mAudio.mpeg',
                    'mime'       => 'audio/mpeg',
                    'size'       => 25000,
                    'hash'       => '90dd4fb4f06041fdf26b15fdf7a45b5699bf5833',
                ]);
            case 5:
                return array_merge($storage, [
                    'extension'  => 'html',
                    'filename'   => 'index',
                    'basename'   => 'index.html',
                    'mime'       => 'text/html',
                    'size'       => 4000,
                    'hash'       => '90dd4fb4f06041fdf26b15fdf7a45b5699bf5833',
                ]);
            case 6:
            default:
                return array_merge($storage, [
                    'extension'  => 'zip',
                    'filename'   => 'mArchive',
                    'basename'   => 'mArchive.zip',
                    'mime'       => 'application/x-7z-compressed',
                    'size'       => 25000,
                    'hash'       => '90dd4fb4f06041fdf26b15fdf7a45b5699bf5833',
                ]);
        }
    }

    /**
     * @param int $mimeTypeId        enum {1,2,3,4,5,6,7}
     * @return array
     */
    public static function getDummyMimeType($mimeTypeId){
        $mimeType = [
            'id'                => $mimeTypeId,
            'actualMaxFileSize' => 1000000,
            'maxFileSize'       => 1000000,
            'enable'            => true,
            'isEnabled'         => true,
            'deleted'           => false,
            'isDeleted'         => false,
        ];
        switch($mimeTypeId){
            case 1:
                return array_merge($mimeType, [
                    'fileTypeId'        => 46,
                    'class'             => 'docx',
                    'description'       => 'Microsoft Word 2007 Document',
                    'iconFile'          => 'msword_doc.png',
                    'iconClass'         => 'msworddoc',
                    'inline'            => false,
                    'mime'              => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'isAudio'           => false,
                    'isVideo'           => false,
                    'isImage'           => false,
                    'type'              => 'application',
                ]);
            case 2:
                return array_merge($mimeType, [
                    'fileTypeId'        => 1,
                    'class'             => 'gif',
                    'description'       => 'GIF Image',
                    'iconFile'          => 'image_gif.png',
                    'iconClass'         => 'imagegif',
                    'inline'            => true,
                    'mime'              => 'image/gif',
                    'isAudio'           => false,
                    'isVideo'           => false,
                    'isImage'           => true,
                    'type'              => 'image',
                ]);
            case 3:
                return array_merge($mimeType, [
                    'fileTypeId'        => 27,
                    'class'             => 'video',
                    'description'       => 'MPEG Video',
                    'iconFile'          => 'video_alt.png',
                    'iconClass'         => 'videoalt',
                    'inline'            => false,
                    'mime'              => 'video/mpeg',
                    'isAudio'           => false,
                    'isVideo'           => true,
                    'isImage'           => false,
                    'type'              => 'video',
                ]);
            case 4:
                return array_merge($mimeType, [
                    'fileTypeId'        => 23,
                    'class'             => 'mpeg',
                    'description'       => 'MPEG Audio',
                    'iconFile'          => 'sound_mp3.png',
                    'iconClass'         => 'soundmp3',
                    'inline'            => false,
                    'mime'              => 'audio/mpeg',
                    'isAudio'           => true,
                    'isVideo'           => false,
                    'isImage'           => false,
                    'type'              => 'audio',
                ]);
            case 5:
                return array_merge($mimeType, [
                    'fileTypeId'        => 2,
                    'class'             => 'html',
                    'description'       => 'HTML Document',
                    'iconFile'          => 'html.png',
                    'iconClass'         => 'html',
                    'inline'            => true,
                    'mime'              => 'text/html',
                    'isAudio'           => false,
                    'isVideo'           => false,
                    'isImage'           => false,
                    'type'              => 'text',
                ]);
            case 6:
            default:
                return array_merge($mimeType, [
                    'fileTypeId'        => 80,
                    'class'             => 'zip',
                    'description'       => '7-Zip',
                    'iconFile'          => 'archive.png',
                    'iconClass'         => 'archive',
                    'inline'            => false,
                    'mime'              => 'application/x-7z-compressed',
                    'isAudio'           => false,
                    'isVideo'           => false,
                    'isImage'           => false,
                    'type'              => 'application',
                ]);
        }
    }

    /**
     * Generate a dummy plag scan doc
     *
     * @param int $plagScanId    enum {1,2}
     * @todo remove when done
     * @return array
     */
    public static function getDummyPlagScan($plagScanId){
        $plagScan = [
            'id'           => $plagScanId,
            'created_at'   => new Alaress_Date_Format('2016/08/08','Y/m/d'),
            'updated_at'   => new Alaress_Date_Format('2016/08/17','Y/m/d'),
            'deleted_at'   => new Alaress_Date_NullDate(),
        ];
        switch($plagScanId){
            case 1:
                return array_merge($plagScan, [
                    'plagscan_id' => 4396294,
                    'status'      => 3,
                    'score'       => 99,
                    'html'        => '<html><head><title>Hello</title></head><body><p>This is plagscan 1</p></body></html>',
                    'deleted_at'  => null,
                ]);
            case 2:
            default:
                return array_merge($plagScan, [
                    'plagscan_id' => 4396541,
                    'status'      => 3,
                    'score'       => 0.1,
                    'html'        => '<html><head><title>Hello</title></head><body><p>This is some content for plagscan 2</p></body></html>',
                    'deleted_at'  => null,
                ]);
        }
    }


    /**
     * Temp method to generate dummy mark scheme
     *
     * @param $scheme    1 => percentage
     *                   2 => letterGrade
     *                   3 => totalMark
     *                   4 => none
     *                   5 => Completion
     * @todo remove this method when no longer required
     * @return array
     */
    public static function getDummyMarkScheme($scheme){
        switch($scheme){
            case 1:
                return [
                    'name'             => 'Percentage',
                    'getHumanReadable' => '32 %',
                    'getNormalised'    => 32.000000000,
                ];
            case 2:
                return [
                    'name'             => 'LetterGrade',
                    'gradeGroup'       => [
                        'id'     => 3,
                        'name'   => 'Competency',
                        'grades' => [
                            [
                                'id'          => 13,
                                'lower_bound' => 0,
                                'raw_grade'   => 20,
                                'caption'     => 'Needs Attention',
                            ],
                            [
                                'id'          => 14,
                                'lower_bound' => 40,
                                'raw_grade'   => 53,
                                'caption'     => 'Acceptable',
                            ],
                            [
                                'id'          => 15,
                                'lower_bound' => 60,
                                'raw_grade'   => 68,
                                'caption'     => 'Good',
                            ],
                            [
                                'id'          => 16,
                                'lower_bound' => 75,
                                'raw_grade'   => 88,
                                'caption'     => 'Very Good',
                            ],
                            [
                                'id'          => 17,
                                'lower_bound' => 90,
                                'raw_grade'   => 95,
                                'caption'     => 'Excellent',
                            ],
                        ],
                    ],
                    'getHumanReadable' => 'Very Good',
                    'getNormalised'    => 88.000000,
                ];
            case 3:
                return [
                    'name'             => 'TotalMark',
                    'maxMark'          => 10,
                    'getHumanReadable' => '6 / 10',
                    'getNormalised'    => 60.00000000,
                ];
            case 4:
                return [
                    'name'             => 'None',
                    'getHumanReadable' => null,
                    'getNormalised'    => null,
                ];
            case 5:
                return [
                    'name'             => 'Completion',
                    'status'           => 2,
                    'getHumanReadable' => 'Teacher Confirmed',
                    'getNormalised'    => null,
                ];
            case 6:
            default:
                return [
                    'name'             => 'LetterGrade',
                    'gradeGroup'       => [
                        'id'     => 1,
                        'name'   => 'Standard',
                        'grades' => [
                            [
                                'id'          => 1,
                                'lower_bound' => 0,
                                'raw_grade'   => 20,
                                'caption'     => 'F',
                            ],
                            [
                                'id'          => 1,
                                'lower_bound' => 50,
                                'raw_grade'   => 55,
                                'caption'     => 'E',
                            ],
                            [
                                'id'          => 2,
                                'lower_bound' => 60,
                                'raw_grade'   => 65,
                                'caption'     => 'D',
                            ],
                            [
                                'id'          => 3,
                                'lower_bound' => 70,
                                'raw_grade'   => 75,
                                'caption'     => 'C',
                            ],
                            [
                                'id'          => 4,
                                'lower_bound' => 80,
                                'raw_grade'   => 85,
                                'caption'     => 'B',
                            ],
                            [
                                'id'          => 5,
                                'lower_bound' => 90,
                                'raw_grade'   => 95,
                                'caption'     => 'A',
                            ],
                        ],
                    ],
                    'getHumanReadable' => 'B',
                    'getNormalised'    => 85.000000,
                ];
        }
    }


    /**
     * Create a dummy quiz section
     *
     * @param $sectionId
     * @return array
     */
    public static function getDummyQuizSection($sectionId){
        switch($sectionId){
            case 1:
                return [
                    'id'            => 1,
                    'title'         => 'mFirstSection',
                    'description'   => 'This is the first section of this quiz',
                    'random'        => 0,
                    'pool'          => 0,
                    'questions'     => [
                        self::getDummyQuizQuestion(1,true),
                    ],
                    'created_at' => new Alaress_Date_Format('2016/08/08','Y/m/d'),
                    'updated_at' => new Alaress_Date_Format('2016/08/15','Y/m/d'),
                    'deleted_at' => new Alaress_Date_NullDate(),
                ];
            case 2:
            default:
                return [
                    'id'            => 2,
                    'title'         => 'mSecondSection',
                    'description'   => 'This is the second section of this quiz',
                    'random'        => 0,
                    'pool'          => 0,
                    'questions'     => [
                        self::getDummyQuizQuestion(2,true),
                        self::getDummyQuizQuestion(3,true),
                    ],
                    'created_at' => new Alaress_Date_Format('2016/08/08','Y/m/d'),
                    'updated_at' => new Alaress_Date_Format('2016/08/15','Y/m/d'),
                    'deleted_at' => new Alaress_Date_NullDate(),
                ];
        }
    }



    /**
     * Generates a dummy quiz attempt for the specified user
     *
     * @param int $attemptId      enum {1,2,3}
     * @todo remove when done
     * @return array
     */
    public static function getDummyQuizAttempt($attemptId){
        $attempt= [
            'id'          => $attemptId,
            'responseType'=> 'quizAttempt',
            'start'       => new Alaress_Date_Format('2012/07/11 12:02:39','Y/m/d H:m:s'),
            'finish'      => new Alaress_Date_Format('2012/07/12 12:05:20','Y/m/d H:m:s'),
            'reviewed_at' => new Alaress_Date_Format('2016/08/15','Y/m/d'),
            'deleted_at'  => new Alaress_Date_NullDate(),
        ];

        switch($attemptId){
            case 1:
                $attempt['feedbacks'] = [
                    self::getDummyQuizAnswer(1,true),
                ];
                return $attempt;
            case 2:
                $attempt['feedbacks'] = [
                    self::getDummyQuizAnswer(2,true),
                    self::getDummyQuizAnswer(3,true),
                    self::getDummyQuizAnswer(4,true),
                ];
                return $attempt;
            case 3:
            default:
                $attempt['feedbacks'] = [
                    self::getDummyQuizAnswer(5,true),
                    self::getDummyQuizAnswer(6,true),
                    self::getDummyQuizAnswer(7,true),
                ];
                return $attempt;
        }
    }

    /**
     * Generates a dummy question, along with its solutions
     *
     * @param int $questionId            enum {1,2}
     * @param bool $nestAnswers          whether to nest answers inside question
     * @todo remove when done
     * @return array
     */
    public static function getDummyQuizQuestion($questionId, $nestAnswers)
    {
        $question = [
            'type'       => self::getDummyQuizQuestionType(1),
            'marks'      => 1.00,
            'config'     => null,
            'created_at' => new Alaress_Date_Format('2016/08/08','Y/m/d'),
            'updated_at' => new Alaress_Date_Format('2016/08/15','Y/m/d'),
            'deleted_at' => new Alaress_Date_NullDate(),
        ];

        switch ($questionId) {
            case 1:
                $question['id'] = 1;
                $question['question'] = '<div class="ff4">They used to say that a child conceived in love has a greater chance</div><div class="ff4">of... What?</div><div class="ff4">';
                $question['solution'] = [
                    'id'         => 1,
                    'solution'   => 'Happiness',
                    'created_at' => new Alaress_Date_Format('2016/08/08','Y/m/d'),
                    'updated_at' => new Alaress_Date_Format('2016/08/15','Y/m/d'),
                    'deleted_at' => new Alaress_Date_NullDate(),
                ];
                $question['answers'] = [
                    self::getDummyQuizAnswer(1,false),
                    self::getDummyQuizAnswer(2,false),
                    self::getDummyQuizAnswer(5,false),
                ];
                return $question;
            case 2:
                $question['id']       = 2;
                $question['question'] = '<<p>What is the weather in Bangalore right now?</p>';
                $question['solution'] = [
                    'id'         => 2,
                    'solution'   => 'Cloudy with a chance of meatballs',
                    'created_at' => new Alaress_Date_Format('2016/08/08','Y/m/d'),
                    'updated_at' => new Alaress_Date_Format('2016/08/15','Y/m/d'),
                    'deleted_at' => new Alaress_Date_NullDate(),
                ];
                $question['answers'] = [
                    self::getDummyQuizAnswer(3,false),
                    self::getDummyQuizAnswer(6,false),
                ];
                return $question;
            case 3:
            default:
                $question['id']       = 3;
                $question['question'] = '<<p>What is the color of the sky at night?</p>';
                $question['solution'] = [
                    'id'         => 3,
                    'solution'   => 'Black',
                    'created_at' => new Alaress_Date_Format('2016/08/08','Y/m/d'),
                    'updated_at' => new Alaress_Date_Format('2016/08/15','Y/m/d'),
                    'deleted_at' => new Alaress_Date_NullDate(),
                ];
            $question['answers'] = [
                self::getDummyQuizAnswer(4,false),
                self::getDummyQuizAnswer(7,false),
            ];
                return $question;

        }
    }


    /**
     * Generates data for a questionType
     *
     * @param int $questionType
     * @todo remove when done
     * @return array
     */
    public static function getDummyQuizQuestionType($questionType){
        switch ($questionType){
            case 1:
            default:
                return [
                    'id'                => $questionType,
                    'classname'         => 'Short',
                    'filename'          => 'short',
                    'description'       => 'Short Answer',
                    'moodle'            => 'shortanswer',
                    'automated_marking' => false,
                    'availability'      => 'all',
                    'has_legend'        => false,
                    'has_config'        => false,
                ];
        }
    }

    /**
     * Returns a QuizAnswer. (Formerly known as 'QuizResponse')
     * This represents a student answer to a question. Their answer may be right or wrong.
     *
     * @param int $answerId       enum {1,2,3,4,5,6,7}
     * @param bool $nestQuestion  Whether to return nested questions for this answer
     * @todo remove when not needed
     * @return array
     */
    public static function getDummyQuizAnswer($answerId, $nestQuestion)
    {
        $answer = [
            'id'           => $answerId,
            'feedbackType' => 'quizAnswer',
            'updated_at'   => new Alaress_Date_NullDate(),
            'deleted_at'   => new Alaress_Date_NullDate(),
        ];

        switch($answerId){
            case 1:
                return array_merge($answer, [
                    'answer'   => 'Sadness',
                    'comment'  => '',
                    'question' => $nestQuestion ? self::getDummyQuizQuestion(1,false) : null,
                ]);
            case 2:
                return array_merge($answer, [
                    'answer'   => 'Happiness',
                    'comment'  => 'Very good',
                    'question' => $nestQuestion ? self::getDummyQuizQuestion(1,false) : null,
                ]);
            case 3:
                return array_merge($answer, [
                    'answer'   => 'I think it is raining',
                    'comment'  => 'No you are wrong',
                    'question' => $nestQuestion ? self::getDummyQuizQuestion(2,false) : null,
                ]);
            case 4:
                return array_merge($answer, [
                    'answer'   => 'Blue',
                    'comment'  => 'Not quite',
                    'question' => $nestQuestion ? self::getDummyQuizQuestion(3,false) : null,
                ]);
            case 5:
                return array_merge($answer, [
                    'answer'   => 'Happiness',
                    'comment'  => 'Good work',
                    'question' => $nestQuestion ? self::getDummyQuizQuestion(1,false) : null,
                ]);
            case 6:
                return array_merge($answer, [
                    'answer'   => 'I think it is sunny',
                    'comment'  => 'Pretty close',
                    'question' => $nestQuestion ? self::getDummyQuizQuestion(2,false) : null,
                ]);
            case 7:
                return array_merge($answer, [
                    'answer'   => 'Yellow',
                    'comment'  => '',
                    'question' => $nestQuestion ? self::getDummyQuizQuestion(3,false) : null,
                ]);
        }
    }

    /**
     * Generate a dummy task completion
     *
     * @param int $taskCompletionId    enum {1,2}
     * @todo remove when done
     * @return array
     */
    public static function getDummyTaskCompletion($taskCompletionId){
        $taskCompletion = [
            'id'           => $taskCompletionId,
            'responseType' => 'taskCompletion',
        ];
        switch($taskCompletionId){
            case 1:
                return array_merge($taskCompletion, [
                    'student'   => UserMapper::getInstance()->findById(230),
                    'feedbacks' => [
                        self::getDummyTaskFeedback(1),
                    ]
                ]);
            case 2:
            default:
            return array_merge($taskCompletion, [
                'student'   => UserMapper::getInstance()->findById(245),
                'feedbacks' => [
                    self::getDummyTaskFeedback(2),
                    self::getDummyTaskFeedback(3),
                ]
            ]);
        }
    }


    /**
     * Generate a dummy task feedback
     *
     * @param int $taskFeedbackId   enum {1,2,3}
     * @todo remove when done
     * @return array
     */
    public static function getDummyTaskFeedback($taskFeedbackId){
        $taskFeedback = [
            'id'           => $taskFeedbackId,
            'feedbackType' => 'taskFeedback',
        ];
        switch($taskFeedbackId){
            case 1:
                return array_merge($taskFeedback, [
                    'reviewer' => UserMapper::getInstance()->findById(230),
                ]);
            case 2:
                return array_merge($taskFeedback, [
                    'reviewer' => UserMapper::getInstance()->findById(245),
                ]);
            case 3:
            default:
                return array_merge($taskFeedback, [
                    'reviewer' => UserMapper::getInstance()->findById(151),
                ]);
        }
    }
}






