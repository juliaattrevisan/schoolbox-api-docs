<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Import\Atlas;

use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\Assessment\Course\Import\Atlas\Api\Bridge as ApiBridge;
use Schoolbox\Assessment\Course\Import\Atlas\Api\Curriculum;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpec;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\HomepageConfigMapper;

class Controller extends BaseController
{
    /**
     * @var ApiBridge
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
     * @var CourseSpecMapper
     */
    private $courseSpecMapper;


    /**
     * Constructor
     *
     * @param ApiBridge        $apiBridge
     * @param FolderMapper     $folderMapper
     * @param CourseSpecMapper $courseSpecMapper
     */
    public function __construct(ApiBridge $apiBridge, FolderMapper $folderMapper, CourseSpecMapper $courseSpecMapper)
    {
        $this->apiBridge        = $apiBridge;
        $this->folderMapper     = $folderMapper;
        $this->courseSpecMapper = $courseSpecMapper;
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

        foreach ($this->request->post->get('unit') as $unitId => $unitParts) {
            // Build a unit page out of this

            // Which attachments are selected?

            // Which assessments are selected?
        }

        // we're done here?
        // at an earlier time we should've asked for a location to import into?
        // alternately, ask now?

//        $this->response->redirect->afterPost($this->router->generate('course.courseList'));
    }


    private function createCourse(Curriculum $curriculum, FolderModel $baseFolder): CourseSpec
    {
        // TODO this all should be moved to a helper class
        // (as \Schoolbox\Assessment\Course\Controller::persistFolder does much the same thing)
        // TODO figure out if this course has already been imported, and update instead of create if so
        $courseSpecFolder = $this->folderMapper->createModel()
            ->setParent($baseFolder)
            ->setName($curriculum->getTitle())
            ->setInherit($this->getConfigLoader()->get('course_builder_default_course_page_inherit'));

        // In addition, need to apply:
        // * Curriculum codes (if any)
        // * Course builder template
        // * Permissions
        // ** Inheritance
        $this->folderMapper->save($courseSpecFolder);


        // Apply the course builder homepage template
        $template = $this->getConfigLoader()->get('course_builder_default_course_page_template');
        $courseSpecFolderConfig = $this->homepageConfigMapper->createModel()
            ->setTemplate($template);



//        $homepageConfig->setTemplate($tplMapper->findById($template));
//
//        // Save homepage config
//        $confMapper->save($homepageConfig);
//        $confMapper->resetToTemplate($homepageConfig);



        // Save the course spec
        $courseSpec = $this->courseSpecMapper->createModel()
            ->setFolder($courseSpecFolder);
        $this->courseSpecMapper->save($courseSpec);

        return $courseSpec;
    }
}
