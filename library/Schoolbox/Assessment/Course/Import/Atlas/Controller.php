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
use Schoolbox\Mapper\Exception as MapperException;
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

        // Import
        // TODO inject?
        $importer = new Importer(
            $this->getUser(),
            $this->getConfigLoader(),
            $this->folderMapper,
            $this->homepageConfigMapper,
            $this->homepageTemplateMapper,
            $this->courseSpecMapper,
            $this->unitSpecMapper,
            $this->access,
            $this->userAccessMapper,
            $this->rubricGroupMapper,
            $this->rubricCriterionMapper,
            $this->assessmentDataSource,
            $this->nodeMapper
        );
        $importer->import(
            $curriculumPlan,
            $baseFolder,
            $this->request->post->get('unit')
        );
        $this->getResponse()->content->set($this->buildJsonOutput([]));
    }
}
