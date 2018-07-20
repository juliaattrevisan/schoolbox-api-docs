<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas;

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric;
use Schoolbox\Assessment\Course\Import\Atlas\Api;
use Schoolbox\Assessment\Rubric\Competency\Level;
use Schoolbox\Assessment\Rubric\Criteria\Criterion;
use Schoolbox\Assessment\Rubric\Criteria\Group;
use Schoolbox\Assessment\Rubric\Descriptor;
use Schoolbox\Curriculum\Node\Mapper as NodeMapper;
use Schoolbox\Curriculum\Node\Model as Node;
use Schoolbox\IConfigLoader;
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
use Schoolbox\User;

/**
 * Importer for Atlas curricula into Schoolbox courses.
 */
class Importer
{

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
     * @var IConfigLoader
     */
    private $config;


    /**
     * @var User
     */
    private $currentUser;


    /**
     * Constructor
     *
     * @param User                   $currentUser
     * @param IConfigLoader          $config
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
        User $currentUser,
        IConfigLoader $config,
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
        // TODO: consider passing $currentUser in import()
        $this->currentUser            = $currentUser;
        $this->config                 = $config;
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
     * Imports the Atlas curriculum into the given course folder.
     *
     * @param Api\Curriculum $curriculum
     * @param Folder         $folder
     * @param array          $selectedUnits
     * @throws MapperException if something couldn't be found somewhere?
     */
    public function import(Api\Curriculum $curriculum, Folder $folder, array $selectedUnits): void
    {
        // Preparation: setup the curriculum nodes
        $baseNode       = $this->findOrCreateBaseCurriculumNode();
        $standardsNode  = $this->findOrCreateBaseCurriculumStandardsNode($baseNode);


        // Create the course in the prescribed folder
        // TODO use an existing course if it's there (or at least have the option)
        $courseSpec = $this->createCourse($curriculum, $folder);


        // Associate curriculum node to course's folder
        // TODO use an existing node if it's there
        $courseSpecNode = $this->createCurriculumNodeFromCurriculumPlan($curriculum, $baseNode);
        $courseSpec->getFolder()->getCurriculumNodes()->add($courseSpecNode);
        $this->folderMapper->save($courseSpec->getFolder());


        // Import the units that were requested
        foreach ($curriculum->getUnits() as $unit) {
            if (!empty($selectedUnits[$unit->getId()]['selected'])) {
                // This unit was selected; create it
                $unitSpec = $this->createUnit($unit, $courseSpec);


                // Associate curriculum node to unit's folder
                $unitSpecNode = $this->createCurriculumNodeFromUnit($unit, $courseSpecNode);
                $unitSpec->getFolder()->getCurriculumNodes()->add($unitSpecNode);
                $this->folderMapper->save($unitSpec->getFolder());


                // Import requested attachments
                foreach ($unit->getAttachments() as $attachment) {
                    // Is this attachment private?
                    // What kind of attachment is it?
                    $this->createAttachment($attachment, $unitSpec->getFolder());
                }


                // Import requested assessments
                foreach ($unit->getAssessments() as $assessment) {
                    $this->createDueWork($assessment, $unitSpec, $unitSpecNode, $standardsNode);
                }

            } else {
                // This unit wasn't selected; delete it
                // TODO implement deletion
            }
        }
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
                                               ->setInherit($this->config->get('course_builder_default_course_page_inherit'));
        $this->folderMapper->save($courseSpecFolder);


        // Allow the current user to write to the course folder
        // (if for some reason they can't).
        $this->ensureCurrentUserHasWriteAccess($courseSpecFolder);


        // Apply the course builder homepage template
        $this->applyHomepageTemplate(
            $courseSpecFolder,
            $this->config->get('course_builder_default_course_page_template')
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
     * @throws MapperException if for some reason a model couldn't be created?
     */
    private function createUnit(Api\Unit $unit, CourseSpec $courseSpec): UnitSpec
    {
        // Create and save unit spec folder
        $unitSpecFolder = $this->folderMapper->createModel()
                                             ->setParent($courseSpec->getFolder())
                                             ->setName($unit->getTitle())
                                             ->setInherit($this->config->get('course_builder_default_unit_page_inherit'));
        $this->folderMapper->save($unitSpecFolder);


        // Allow the current user to write to the course folder
        // (if for some reason they can't).
        $this->ensureCurrentUserHasWriteAccess($unitSpecFolder);


        // Apply the course builder homepage template
        $this->applyHomepageTemplate(
            $unitSpecFolder,
            $this->config->get('course_builder_default_unit_page_template')
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
     * @param Node           $unitSpecNode
     * @param Node           $standardsNode
     * @return IAssessable
     * @throws MapperException if for some reason a model couldn't be created?
     */
    private function createDueWork(
        Api\Assessment $assessment,
        UnitSpec $unitSpec,
        Node $unitSpecNode,
        Node $standardsNode
    ): IAssessable
    {
        $dueWork = new DueWork();
        $dueWork
            ->setContext($unitSpec)
            ->setTitle($assessment->getTitle())
            ->setDescription($assessment->getContent())
            ->setWeight(0)
            ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_NONE)
            ->setCreator($this->currentUser)
            ->setMarkScheme(new Percentage());

        $dueWorkNode = $this->createCurriculumNodeFromAssessment($assessment, $unitSpecNode);
        $dueWork->getCurriculumNodes()->add($dueWorkNode);

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
                    $standard->getTitle(),
                    false
                );

                // Create node and re-save
                $standardNode = $this->createCurriculumNodeFromStandard($standard, $standardsNode);
                if ($standardNode) {
                    $groupDataModel->getCurriculumNodes()->add($standardNode);
                    $this->rubricGroupMapper->save($groupDataModel);
                }

                // Add that rubric group
                $group = new Group($groupDataModel->getId(), $groupDataModel->getName());
                $rubric->putGroup($group);

                // Add a criterion with an empty name for now, I guess
                $criterionDataModel = $this->rubricCriterionMapper->findOrCreateOneForFolderByKey(
                    $unitSpec->getFolder()->getId(),
                    '',
                    $standard->getTitle(),
                    false
                );
                $criterion = new Criterion($criterionDataModel->getId(), $criterionDataModel->getName());
                $rubric->putCriterion($group, $criterion);

                // And an empty descriptor for that level?
                $rubric->putDescriptor(
                    $group,
                    $criterion,
                    $level,
                    new Descriptor('', 1)
                );
            }

            $dueWork
                ->setUseRubric(true)
                ->setRubric($rubric);
        }

        $this->assessmentDataSource->save($dueWork);
        return $dueWork;
    }


    /**
     * Add an attachment to the folder.
     *
     * @param Api\Attachment $attachment
     * @param Folder         $folder
     * @return void Don't need to return what was attached?
     */
    private function createAttachment(Api\Attachment $attachment, Folder $folder): void
    {
        // TODO did I pass the attachment as private?
        // How to create the item?
        switch ($attachment->getAttachmentType()) {
            case Api\Attachment::ATTACHMENT_TYPE_FILE:
            case Api\Attachment::ATTACHMENT_TYPE_IMAGE:
            case Api\Attachment::ATTACHMENT_TYPE_LESSON:
                // upload as a file to the folder
                // (Consider creating a files component if there's not one there?)
                break;

            case Api\Attachment::ATTACHMENT_TYPE_LINK:
                // upload as a link to the folder
                // (Consider creating a links component if there's not one there?)
                break;

            case Api\Attachment::ATTACHMENT_TYPE_GOOGLE_DRIVE:
                // not sure. Probably save to Links as well?
                // Otherwise: consider creating a textbox, which contains drive file?
                break;

            default:
                // Dunno what to do with it
                break;
        }
    }


    /**
     * Create the base-level curriculum node for all Atlas imports.
     *
     * @return Node
     * @throws MapperException if somehow it couldn't be created
     */
    private function findOrCreateBaseCurriculumNode(): Node
    {
        $code = 'atlas:root';
        try {
            // Does the base node exist? Anywhere?
            return $this->nodeMapper->findOneByColumn('code', $code);

        } catch (MapperException $e) {
            // It was not found: create it
            // Consider soft-deletion the same as hard deletion
            $node = $this->nodeMapper->createModel()
                                     ->setParent(null)
                                     ->setCode($code);
            $this->nodeMapper->save($node);
            return $node;
        }
    }


    /**
     * Create the base-level curriculum node for all Atlas standards.
     *
     * @param Node $parent The node in which this lives.
     * @return Node
     * @throws MapperException if somehow it couldn't be created
     */
    private function findOrCreateBaseCurriculumStandardsNode(Node $parent): Node
    {
        $code = 'atlas:standards';
        try {
            // Does the base node exist? Anywhere?
            return $this->nodeMapper->findOneByColumnList([
                'code'                      => $code,
                'curriculum_node_parent_id' => $parent->getId(),
            ]);

        } catch (MapperException $e) {
            // It was not found: create it
            // Consider soft-deletion the same as hard deletion
            $node = $this->nodeMapper->createModel()
                                     ->setParent($parent)
                                     ->setCode($code);
            $this->nodeMapper->save($node);
            return $node;
        }
    }


    /**
     * Create a curriculum node.
     *
     * @param string      $name
     * @param null|string $code
     * @param null|string $reference
     * @param null|string $description
     * @param null|Node   $parent
     * @return Node
     * @throws \Schoolbox\Mapper\Exception if somehow creation fails?
     */
    private function createCurriculumNode(
        string $name,
        ?string $code,
        ?string $reference,
        ?string $description,
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
     * Create a curriculum node from a curriculum plan.
     *
     * @param Api\Curriculum $curriculumPlan
     * @param Node           $parent
     * @return Node
     * @throws \Schoolbox\Mapper\Exception
     */
    private function createCurriculumNodeFromCurriculumPlan(Api\Curriculum $curriculumPlan, Node $parent): Node
    {
        return $this->createCurriculumNode(
            $curriculumPlan->getTitle(),
            sprintf('atlas:curriculum:%d', $curriculumPlan->getId()),
            null,
            $curriculumPlan->getDescription(),
            $parent
        );
    }


    /**
     * Create a curriculum node from a unit.
     *
     * @param Api\Unit $unit
     * @param Node     $parent
     * @return Node
     * @throws \Schoolbox\Mapper\Exception
     */
    private function createCurriculumNodeFromUnit(Api\Unit $unit, Node $parent): Node
    {
        return $this->createCurriculumNode(
            $unit->getTitle(),
            sprintf('atlas:unit:%d', $unit->getId()),
            null,
            null,
            $parent
        );
    }


    /**
     * Create a curriculum node from an assessment.
     *
     * @param Api\Assessment $assessment
     * @param Node           $parent
     * @return Node
     * @throws \Schoolbox\Mapper\Exception
     */
    private function createCurriculumNodeFromAssessment(Api\Assessment $assessment, Node $parent): Node
    {
        return $this->createCurriculumNode(
            $assessment->getTitle(),
            sprintf('atlas:assessment:%d', $assessment->getId()),
            null,
            $assessment->getContent(),
            $parent
        );
    }


    /**
     * Create a curriculum node from a standard, if it has a code.
     *
     * @param Api\Standard $standard
     * @param Node         $parent
     * @return null|Node
     * @throws \Schoolbox\Mapper\Exception
     */
    private function createCurriculumNodeFromStandard(Api\Standard $standard, Node $parent): ?Node
    {
        if ('' !== $standard->getCode()) {
            return $this->createCurriculumNode(
                $standard->getTitle(),
                $standard->getCode(),
                null,
                $standard->getRawText(),
                $parent
            );
        }
        return null;
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
            && $this->access->getFolderAccess($this->currentUser, $folder->getId()) > UserAccess::ACCESS_WRITE
        ) {
            $userAccess = $this->userAccessMapper->createModel()
                                                 ->setUser($this->currentUser)
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
