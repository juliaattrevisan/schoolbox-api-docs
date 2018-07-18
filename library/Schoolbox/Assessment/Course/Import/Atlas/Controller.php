<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Import\Atlas;

use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric;
use Schoolbox\Assessment\Course\Import\Atlas\Api;
use Schoolbox\Assessment\Rubric\Competency\Level;
use Schoolbox\Assessment\Rubric\Criteria\Criterion;
use Schoolbox\Assessment\Rubric\Criteria\Group;
use Schoolbox\Assessment\Rubric\Descriptor;
use Schoolbox\Curriculum\Node\Mapper as NodeMapper;
use Schoolbox\Curriculum\Node\Model as Node;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpec;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplateMapper;

class Controller extends BaseController
{
    /**
     * @var Api\Bridge
     */
    private $apiBridge;


    /**
     * @var FolderMapper
     */
    private $folderMapper;


    /**
     * @var HomepageConfigMapper
     */
    private $homepageConfigMapper;


    /**
     * @var HomepageTemplateMapper
     */
    private $homepageTemplateMapper;


    /**
     * @var CourseSpecMapper
     */
    private $courseSpecMapper;


    /**
     * @var UnitSpecMapper
     */
    private $unitSpecMapper;


    /**
     * @var Access
     */
    private $access;


    /**
     * @var UserAccessMapper
     */
    private $userAccessMapper;


    /**
     * @var Rubric\GroupMapper
     */
    private $rubricGroupMapper;


    /**
     * @var Rubric\CriterionMapper
     */
    private $rubricCriterionMapper;


    /**
     * @var AssessmentDataSource
     */
    private $assessmentDataSource;


    /**
     * @var NodeMapper
     */
    private $nodeMapper;


    /**
     * Constructor
     *
     * @param Api\Bridge             $apiBridge
     * @param FolderMapper           $folderMapper
     * @param HomepageConfigMapper   $homepageConfigMapper
     * @param HomepageTemplateMapper $homepageTemplateMapper
     * @param CourseSpecMapper       $courseSpecMapper
     * @param UnitSpecMapper         $unitSpecMapper
     * @param Access                 $access
     * @param UserAccessMapper       $userAccessMapper
     * @param Rubric\GroupMapper     $rubricGroupMapper
     * @param Rubric\CriterionMapper $rubricCriterionMapper
     * @param AssessmentDataSource   $assessmentDataSource
     * @param NodeMapper             $nodeMapper
     */
    public function __construct(
        Api\Bridge $apiBridge,
        FolderMapper $folderMapper,
        HomepageConfigMapper $homepageConfigMapper,
        HomepageTemplateMapper $homepageTemplateMapper,
        CourseSpecMapper $courseSpecMapper,
        UnitSpecMapper $unitSpecMapper,
        Access $access,
        UserAccessMapper $userAccessMapper,
        Rubric\GroupMapper $rubricGroupMapper,
        Rubric\CriterionMapper $rubricCriterionMapper,
        AssessmentDataSource $assessmentDataSource,
        NodeMapper $nodeMapper
    ) {
        $this->apiBridge              = $apiBridge;
        $this->folderMapper           = $folderMapper;
        $this->homepageConfigMapper   = $homepageConfigMapper;
        $this->homepageTemplateMapper = $homepageTemplateMapper;
        $this->courseSpecMapper       = $courseSpecMapper;
        $this->unitSpecMapper         = $unitSpecMapper;
        $this->access                 = $access;
        $this->userAccessMapper       = $userAccessMapper;
        $this->rubricGroupMapper      = $rubricGroupMapper;
        $this->rubricCriterionMapper  = $rubricCriterionMapper;
        $this->assessmentDataSource   = $assessmentDataSource;
        $this->nodeMapper             = $nodeMapper;
    }


    /**
     * Render form for the import page.
     */
    public function getImport(): void
    {
        $this->response->content->set(
            $this->buildHtmlOutput('@courseImportAtlas/import.twig', [], 'Atlas Import', 'course')
        );
    }


    /**
     * Action for getting a list of all curricula.
     *
     * @throws \Throwable
     */
    public function getCurriculumList(): void
    {
        $this->getResponse()->content->set($this->buildJsonOutput($this->apiBridge->getCurriculumList()));
    }


    /**
     * Action for getting a curriculum and contents in a single request.
     *
     * @param int $curriculumId
     * @throws \Throwable
     */
    public function getCurriculum(int $curriculumId): void
    {
        $this->getResponse()->content->set($this->buildJsonOutput($this->apiBridge->getCurriculum($curriculumId)));
    }


    /**
     * POST action for `getImport` mage form.
     */
    public function postImport(): void
    {
        if ($this->request->post->getBool('part-1')) {
            $this->postImportPart1();
            return;
        }

        // Part 2: I have chosen units, now import them
        if ($this->request->post->getBool('part-2')) {
            $this->postImportPart2();
            return;
        }
    }


    /**
     * POST part 1: I have chosen a plan, now show the parts
     */
    private function postImportPart1(): void
    {
        // Which plan did I choose?
        $curriculumPlanId = $this->request->post->getInt('curriculum-plan', 0);

        // None: render an error
        if ($curriculumPlanId === 0) {
            $this->response->status->setCode(400);
            $this->response->content->set(
                $this->buildUnthemedHtmlOutput('sub/mage-error.twig',
                    [
                        'error' => [
                            'problem' => 'You must select a curriculum plan.'
                        ]
                    ]
                )
            );
            return;
        }

        $curriculumPlan = $this->apiBridge->getCurriculum($curriculumPlanId);

        $this->response->content->set(
            $this->buildUnthemedHtmlOutput(
                '@courseImportAtlas/import/plan.twig',
                [
                    'curriculum' => $curriculumPlan,
                ]
            )
        );
    }


    /**
     * POST part 2: I have chosen parts of a plan, now import them
     */
    private function postImportPart2(): void
    {
        // Which curriculum plan got selected?

        // Which units were selected?
        // * Create a new unit out of them
        // Which assessments within get imported?
        // * Create a new Schoolbox assessment out of them
        $curriculumPlanId = $this->request->post->getInt('curriculum-plan', 0);
        $curriculumPlan   = $this->apiBridge->getCurriculum($curriculumPlanId);

        // Hard-coded folder for testing (faculty root)
        $baseFolder = $this->courseSpecMapper->getFacultyRootFolder();

        $courseSpec = $this->createCourse($curriculumPlan, $baseFolder);


        // Should this be an API call to createCourse? I think so
        // It should maybe happen on the frontend itself?

        // Alternately should the API call occur in AJAX?

        $selectedUnits = $this->request->post->get('unit');
        foreach ($curriculumPlan->getUnits() as $unit) {
            if (!empty($selectedUnits[$unit->getId()]['selected'])) {
                // This unit was selected; create it
                $unitSpec = $this->createUnit($unit, $courseSpec);

                // Put the assessment inside that unit
                foreach ($unit->getAttachments() as $attachment) {
                    // Is this attachment private?
                    // What kind of attachment is it?
                }

                foreach ($unit->getAssessments() as $assessment) {
                    $this->createDueWork($assessment, $unitSpec);
                }

            } else {
                // This unit wasn't selected; delete it
                // TODO implement deletion
            }
        }
        $this->response->redirect->afterPost($this->router->generate('course.courseList'));
    }


    /**
     * Create a course spec out of `$curriculum` in `$folder`.
     *
     * @param Api\Curriculum $curriculum
     * @param Folder         $baseFolder
     * @return CourseSpec
     * @throws \Schoolbox\Mapper\Exception if for some reason a model couldn't be created?
     */
    private function createCourse(Api\Curriculum $curriculum, Folder $baseFolder): CourseSpec
    {
        // Create and save course spec folder
        // TODO this all should be moved to a helper class
        // (as \Schoolbox\Assessment\Course\Controller::persistFolder does much the same thing)
        // TODO figure out if this course has already been imported, and update instead of create if so
        $courseSpecFolder = $this->folderMapper->createModel()
            ->setParent($baseFolder)
            ->setName($curriculum->getTitle())
            ->setInherit($this->getConfigLoader()->get('course_builder_default_course_page_inherit'));
        $this->folderMapper->save($courseSpecFolder);


        // Allow the current user to write to the course folder
        // (if for some reason they can't).
        $this->ensureCurrentUserHasWriteAccess($courseSpecFolder);


        // Apply the course builder homepage template
        $this->applyHomepageTemplate(
            $courseSpecFolder,
            $this->getConfigLoader()->get('course_builder_default_course_page_template')
        );


        // Save the course spec
        $courseSpec = $this->courseSpecMapper->createModel()
            ->setFolder($courseSpecFolder);
        $this->courseSpecMapper->save($courseSpec);

        return $courseSpec;
    }


    /**
     * Create a unit spec out of `$unit` in `$courseSpec`.
     *
     * @param Api\Unit   $unit
     * @param CourseSpec $courseSpec
     * @return UnitSpec
     * @throws \Schoolbox\Mapper\Exception if for some reason a model couldn't be created?
     */
    private function createUnit(Api\Unit $unit, CourseSpec $courseSpec): UnitSpec
    {
        // Create and save unit spec folder
        $unitSpecFolder = $this->folderMapper->createModel()
            ->setParent($courseSpec->getFolder())
            ->setName($unit->getTitle())
            ->setInherit($this->getConfigLoader()->get('course_builder_default_unit_page_inherit'));
        $this->folderMapper->save($unitSpecFolder);


        // Allow the current user to write to the course folder
        // (if for some reason they can't).
        $this->ensureCurrentUserHasWriteAccess($unitSpecFolder);


        // Apply the course builder homepage template
        $this->applyHomepageTemplate(
            $unitSpecFolder,
            $this->getConfigLoader()->get('course_builder_default_unit_page_template')
        );


        // Save the unit spec
        $unitSpec = $this->unitSpecMapper->createModel()
            ->setFolder($unitSpecFolder)
            ->setCourse($courseSpec);
        $this->unitSpecMapper->save($unitSpec);
        return $unitSpec;
    }


    /**
     * Create a Schoolbox due work out of `$assessment` in `$unitSpec`.
     *
     * @param Api\Assessment $assessment
     * @param UnitSpec       $unitSpec
     * @return IAssessable In practise, this will always be a due work
     */
    private function createDueWork(Api\Assessment $assessment, UnitSpec $unitSpec): IAssessable
    {
        $dueWork = new DueWork();
        $dueWork
            ->setContext($unitSpec)
            ->setTitle($assessment->getTitle())
            ->setDescription($assessment->getContent())
            ->setWeight(0)
            ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_NONE)
            ->setCreator($this->getUser())
            ->setMarkScheme(new Percentage());

        // Build a rubric for this due work
        if (\count($assessment->getStandards())) {
            $rubric = new Rubric\Rubric();

            // Dummy data
            $level = new Level();
            $level->setName('Level');
            $rubric->putLevel($level);

            // I can only create groups, as there's no sub-standards.
            // Provided there's mark-by-group without needing a criterion,
            // this should be okay.
            foreach ($assessment->getStandards() as $standard) {
                $groupDataModel = $this->rubricGroupMapper->findOrCreateOneForFolderByName(
                    $unitSpec->getFolder()->getId(),
                    $standard->getTitle()
                );
                $group = new Group($groupDataModel->getId());
                $rubric->putGroup($group);

                // Add an empty criterion for now, I guess
                $criterionDataModel = $this->rubricCriterionMapper->findOrCreateOneForFolderByKey(
                    $unitSpec->getFolder()->getId(),
                    $standard->getTitle(),
                    ''
                );
                $criterion = new Criterion($criterionDataModel->getId());
                $rubric->putCriterion($group, $criterion);

                // And an empty descriptor for that level?
                $rubric->putDescriptor(
                    $group,
                    $criterion,
                    $level,
                    new Descriptor('', 1)
                );

                // Add a code if there is one? ¯\_(ツ)_/¯
            }

            $dueWork
                ->setUseRubric(true)
                ->setRubric($rubric);
        }

        $this->assessmentDataSource->save($dueWork);
        return $dueWork;
    }


    /**
     * Create a curriculum node.
     *
     * @param string    $name
     * @param string    $code
     * @param string    $reference
     * @param string    $description
     * @param null|Node $parent
     * @return Node
     * @throws \Schoolbox\Mapper\Exception if somehow creation fails?
     */
    private function createCurriculumNode(
        string $name,
        string $code,
        string $reference,
        string $description,
        ?Node $parent
    ): Node {
        // Atlas-imported assessments have to tie back to curriculum nodes somehow.
        $node = $this->nodeMapper->createModel()
            ->setName($name)
            ->setCode($code)
            ->setDescription($description)
            ->setReference($reference)
            ->setParent($parent);
        $this->nodeMapper->save($node);
        return $node;
    }


    /**
     * Ensures that the current user has write access to `$folder`, as long as
     * the folder doesn't have inherit permissions.
     *
     * @param Folder $folder The folder to grant access for
     * @throws \Schoolbox\Mapper\Exception if for some reason a model couldn't be created?
     */
    private function ensureCurrentUserHasWriteAccess(Folder $folder): void
    {
        if (!$folder->getInherit()
            && $this->access->getFolderAccess($this->getUser(), $folder->getId()) > UserAccess::ACCESS_WRITE
        ) {
            $userAccess = $this->userAccessMapper->createModel()
                ->setUser($this->getUser())
                ->setService(UserAccess::SERVICE_HOMEPAGE)
                ->setAccessLevel(UserAccess::ACCESS_WRITE);
            $this->userAccessMapper->save($userAccess);
        }
    }


    /**
     * Apply the given template to `$folder`.
     *
     * @param Folder   $folder
     * @param int|null $templateId
     * @throws \Schoolbox\Mapper\Exception if for some reason a model couldn't be created?
     */
    private function applyHomepageTemplate(Folder $folder, ?int $templateId): void
    {
        if ($templateId) {
            $template     = $this->homepageTemplateMapper->findById($templateId);
            $folderConfig = $this->homepageConfigMapper->createModel()
                ->setCategory($folder)
                ->setTemplate($template);
            $this->homepageConfigMapper->save($folderConfig);
            $this->homepageConfigMapper->resetToTemplate($folderConfig);
        }
    }
}
