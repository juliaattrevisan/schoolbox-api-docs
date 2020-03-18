#!/usr/bin/php
<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Rubric\DataModel\Capability\Mapper as CapabilityMapper;
use Schoolbox\Assessment\Rubric\DataModel\Capability\Model as Capability;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Mapper as IndicatorMapper;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Model as Indicator;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpec;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\HomepageConfig;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\UserMapper;

// Setup kernel
require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Schoolbox\Application\Daemon\Kernel('Generate invalid data');

/**
 * This is to simulate invalid rubric data
 */
$app = new class extends CliTool\App {

    /** @var FolderMapper */
    private $folderMapper;

    /** @var CourseSpecMapper */
    private $courseSpecMapper;

    /** @var UnitSpecMapper */
    private $unitSpecMapper;

    /** @var CapabilityMapper */
    private $capabilityMapper;

    /** @var IndicatorMapper */
    private $indicatorMapper;

    /** @var AssessmentMapper */
    private $assessmentMapper;

    /** @var SubmissionBoxMapper */
    private $submissionBoxMapper;

    /** @var UserMapper */
    private $userMapper;

    /** @var MarkTypeMapper */
    private $markTypeMapper;

    /** @var HomepageTemplateMapper */
    private $homepageTemplateMapper;

    /** @var HomepageConfigMapper */
    private $homepageConfigMapper;

    /** @inheritDoc */
    public function run(): void
    {
        $configMapper = ConfigMapper::getInstance();
        $configMapper->getDatabase()->query("UPDATE config SET value = 0 WHERE name = 'external_markbook'");

        $this->folderMapper           = FolderMapper::getInstance();
        $this->courseSpecMapper       = CourseSpecMapper::getInstance();
        $this->unitSpecMapper         = UnitSpecMapper::getInstance();
        $this->capabilityMapper       = CapabilityMapper::getInstance();
        $this->indicatorMapper        = IndicatorMapper::getInstance();
        $this->submissionBoxMapper    = SubmissionBoxMapper::getInstance();
        $this->assessmentMapper       = AssessmentMapper::getInstance();
        $this->markTypeMapper         = MarkTypeMapper::getInstance();
        $this->userMapper             = UserMapper::getInstance();
        $this->homepageTemplateMapper = HomepageTemplateMapper::getInstance();
        $this->homepageConfigMapper   = HomepageConfigMapper::getInstance();
        $user                         = $this->userMapper->findByUsername('alaress');

        // preparation
        $suffix         = date('H:i') . ' Test ';
        $rootFolder     = $this->folderMapper->findById(2);
        $testRootFolder = $this->createFolder($rootFolder, " {$suffix} Courses");

        // Case A : duplicate capabilities without indicators
        $courseSpecA = $this->createCourseSpec($testRootFolder, " {$suffix} A Course");
        $this->createUnitSpec($courseSpecA, "{$suffix} A Unit ");

        $this->createCapability($courseSpecA->getFolder(), "{$suffix} A Course Capability");
        $this->createCapability($courseSpecA->getFolder(), "{$suffix} A Course Capability\r\n");
        $capA = $this->createCapability($courseSpecA->getFolder(), " {$suffix} A Course Capability");
        $this->createIndicator($capA, "{$suffix} A Course Indicator");

        // Case B : duplicate capabilities
        $courseSpecB = $this->createCourseSpec($testRootFolder, "{$suffix} B Course");
        $unitSpecB1  = $this->createUnitSpec($courseSpecB, "{$suffix} B Unit 1");
        $unitSpecB2  = $this->createUnitSpec($courseSpecB, "{$suffix} B Unit 2");

        $capUB1 = $this->createCapability($unitSpecB1->getFolder(), "{$suffix} B Unit 1 Capability");
        $this->createIndicator($capUB1, "{$suffix} B Unit 1 Indicator");
        $capUB2 = $this->createCapability($unitSpecB2->getFolder(), "{$suffix} B Unit 2 Capability");
        $this->createIndicator($capUB2, "{$suffix} B Unit 2 Indicator");

        $folderB = $this->createFolder($testRootFolder, "{$suffix} B Folder");
        $capFB1  = $this->createCapability($folderB, "{$suffix} B Unit 1 Capability\r\n");
        $this->createIndicator($capFB1, "{$suffix} B Unit 1 Indicator\r\n");
        $capFB2 = $this->createCapability($folderB, "{$suffix} B Unit 2 Capability");
        $this->createIndicator($capFB2, "{$suffix} B Unit 2 Indicator\r\n");

        // Case C : no descriptor
        $courseSpecC = $this->createCourseSpec($testRootFolder, "{$suffix} C Course");
        $unitSpecC   = $this->createUnitSpec($courseSpecC, "{$suffix} C Unit");

        $capC1 = $this->createCapability($courseSpecC->getFolder(), "{$suffix} C Course Capability");
        $this->createIndicator($capC1, "{$suffix} C Course Indicator");

        $assessment = ($this->assessmentMapper->createModel())
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setContextUnitSpecification($unitSpecC)
            ->setTitle('test');
        $this->assessmentMapper->save($assessment);

        $submissionBox = $this->submissionBoxMapper->createModel()
                                                   ->setAssessmentModel($assessment)
                                                   ->setOwner($user)
                                                   ->setCategory($unitSpecC->getFolder())
                                                   ->setName('test')
                                                   ->setMarkType($this->markTypeMapper->findAll()->first())
                                                   ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_FULL)
                                                   ->setUseRubric(1);
        $this->submissionBoxMapper->save($submissionBox);

        // Case D : duplicate capabilities in course and unit
        $courseSpecD = $this->createCourseSpec($testRootFolder, " {$suffix} D Course");
        $unitSpecD   = $this->createUnitSpec($courseSpecD, "{$suffix} D Unit ");

        $capCD = $this->createCapability($courseSpecD->getFolder(), "{$suffix} D Capability");
        $this->createIndicator($capCD, "{$suffix} D Course Indicator");
        $capUD = $this->createCapability($unitSpecD->getFolder(), "{$suffix} D Capability\r\n");
        $this->createIndicator($capUD, "{$suffix} D Unit Indicator");
    }

    /**
     * @param Folder $parent
     * @param string $name
     * @return Folder
     */
    private function createFolder(Folder $parent, string $name): Folder
    {

        $folder = $this->folderMapper->createModel()
                                     ->setInherit(true)
                                     ->setParent($parent)
                                     ->setName($name);

        $this->folderMapper->save($folder);

        $homepageConfig = (new HomepageConfig())
            ->setTemplate($this->homepageTemplateMapper->findOneByColumn('name', 'Blank'))
            ->setCategory($folder);
        $this->homepageConfigMapper->resetToTemplate($homepageConfig);
        $this->homepageConfigMapper->save($homepageConfig);

        return $folder;
    }

    /**
     * @param Folder $parent
     * @param string $name
     * @return CourseSpec
     */
    private function createCourseSpec(Folder $parent, string $name): CourseSpec
    {
        $courseFolder = $this->createFolder($parent, $name);
        $courseSpec   = $this->courseSpecMapper->createModel()
                                               ->setFolder($courseFolder);
        $this->courseSpecMapper->save($courseSpec);

        $courseFolder->setCourseSpecification($courseSpec);
        $this->folderMapper->save($courseFolder);
        return $courseSpec;

    }

    /**
     * @param CourseSpec $courseSpec
     * @param string     $name
     * @return UnitSpec
     */
    private function createUnitSpec(CourseSpec $courseSpec, string $name): UnitSpec
    {
        $unitSpecFolder = $this->createFolder($courseSpec->getFolder(), $name);
        $this->folderMapper->save($unitSpecFolder);
        $unitSpec = $this->unitSpecMapper->createModel()
                                         ->setCourse($courseSpec)
                                         ->setFolder($unitSpecFolder);

        $this->unitSpecMapper->save($unitSpec);
        return $unitSpec;
    }

    /**
     * @param Folder $folder
     * @param string $name
     * @return Capability
     */
    private function createCapability(Folder $folder, string $name): Capability
    {
        $capability = $this->capabilityMapper->createModel()
                                             ->setParentId($folder->getId())
                                             ->setName($name);
        try {
            $this->capabilityMapper->save($capability);
        } catch (Exception $e) {
            $this->getDatabaseInternal()->executeQuery(
                "INSERT INTO rubric_capability (name, parent_id, parent_type) VALUES (:name, :parentId, 'category')",
                ['name' => $name, 'parentId' => $folder->getId()]
            );
            $id         = $this->getDatabaseInternal()->lastInsertId();
            $capability = $this->capabilityMapper->findById($id);
        }
        return $capability;
    }

    /**
     * @param Capability $capability
     * @param string     $name
     * @return Indicator
     */
    private function createIndicator(Capability $capability, string $name): Indicator
    {
        $indicator = $this->indicatorMapper->createModel()
                                           ->setCapability($capability)
                                           ->setName($name);
        try {
            $this->indicatorMapper->save($indicator);
        } catch (Exception $e) {
            $this->getDatabaseInternal()->executeQuery(
                "INSERT INTO rubric_indicator (name, rubric_capability_id) VALUES (:name, :capabilityId)",
                ['name' => $name, 'capabilityId' => $capability->getId()]
            );
            $id        = $this->getDatabaseInternal()->lastInsertId();
            $indicator = $this->indicatorMapper->findById($id);
        }
        return $indicator;
    }
};

$kernel = new CliTool\Kernel(
    $app,
    [
        CliTool\Kernel::OPTION_LEVEL  => Monolog\Logger::CRITICAL,
        CliTool\Kernel::OPTION_ERRORS => E_ALL,
    ]
);
$kernel->runApp();
