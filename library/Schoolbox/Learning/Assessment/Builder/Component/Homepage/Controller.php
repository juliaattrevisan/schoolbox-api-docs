<?php
/**
 * Course builder homepage component controller
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Builder\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Component_Instance;
use Schoolbox\Assessment\Course\TaskBuilder;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Schoolbox\Learning\Assessment\DataModel\Translator as AssessmentTranslator;
use Schoolbox\Learning\Assessment\DataModel\Translator\DueWork as DueWorkTranslator;
use Schoolbox\Learning\Assessment\DataModel\Translator\Project as ProjectTranslator;
use Schoolbox\Learning\Assessment\DataModel\Translator\Quiz as QuizTranslator;
use Schoolbox\Learning\Assessment\DataModel\Translator\Task as TaskTranslator;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Translator as UnitInstanceTranslator;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use SitePage;
use Alaress\Authorizer\Failure;
use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Tree\TreeNode;
use Alaress\Web\Controller as Base;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Web\Request\Values;
use Component_DefinitionMapper;
use Component_Homepage_CourseBuilderController;
use Component_Homepage_CourseOutlineController;
use Component_InstanceMapper;
use RangeException;
use Schoolbox\Assessment\Component\OnlineAssessmentMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Course\Config\Mapper as CourseConfigMapper;
use Schoolbox\Assessment\Course\Config\Model as CourseConfigModel;
use Schoolbox\Assessment\Course\Import\Import as CourseImport;
use Schoolbox\Assessment\Course\DeprecatedModel as CourseModel;
use Schoolbox\Assessment\Course\Unit\DeprecatedMapper as CourseUnitMapper;
use Schoolbox\Assessment\Course\Task\Instance as TaskInstance;
use Schoolbox\Assessment\Course\Task\InstanceMapper as TaskInstanceMapper;
use Schoolbox\Collection;
use Schoolbox\Error;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Translator as CourseSpecificationTranslator;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Translator as UnitSpecificationTranslator;
use Schoolbox\Learning\Course\Specification as CourseSpecification;
use Schoolbox\Learning\Course\SpecificationMapperProxy as CourseSpecificationMapperProxy;
use Schoolbox\Learning\Course\Unit\Implementation as UnitImplementation;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;
use Schoolbox\Learning\Course\Unit\SpecificationMapperProxy as UnitSpecificationMapperProxy;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception\Deleted as MapperExceptionDeleted;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Browser\AbstractTreeBuilder;
use Schoolbox\Resource\Browser\Resources\TreeBuilder;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Ancestry;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplate;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Config;
use Schoolbox\Resource\File\IFile as FileInterface;
use Schoolbox\Resource\Folder\IFolder as FolderInterface;
use Schoolbox\Resource;
use Schoolbox\Resource\Folder\IFolder;
use Schoolbox\Learning\Course;

class Controller extends Component_Homepage_ControllerAbstract
{
    /**
     * Injectable constructor
     *
     * @param Component_Instance $instance
     * @param User $user
     * @param SitePage $sitepage
     */
    public function __construct(
        Component_Instance $instance,
        User $user,
        SitePage $sitepage
    ) {
        parent::__construct($instance, $user, $sitepage);

        $this->setSubmissionBoxMapper(SubmissionBoxMapper::getInstance());
        $this->setFolderMapper(FolderMapper::getInstance());
        $this->setOnlineAssessmentMapper(OnlineAssessmentMapper::getInstance());
        $this->setComponentDefinitionMapper(Component_DefinitionMapper::getInstance());
        $this->setComponentInstanceMapper(Component_InstanceMapper::getInstance());
        $this->setUnitInstanceMapper(UnitInstanceMapper::getInstance());
        $this->setTaskMapper(TaskMapper::getInstance());
        $this->setTaskInstanceMapper(TaskInstanceMapper::getInstance());
        $this->setHomepageConfigMapper(HomepageConfigMapper::getInstance());
        $this->setHomepageTemplateMapper(HomepageTemplateMapper::getInstance());
        $this->setAccess(Access::getInstance());
        $this->setCourseConfigMapper(CourseConfigMapper::getInstance());
        $this->setUserAccessMapper(UserAccessMapper::getInstance());
        $this->setUserMapper(UserMapper::getInstance());
        $this->setErrorMapper(Error\Mapper::getInstance());

        // This mapper proxy stuff is doing my head in, couldn't get it DI'ed in here so kerfludged it together rather uglily as you
        // will see below

        $this->setCourseSpecificationMapper(CourseSpecificationMapper::getInstance());
        $this->setUnitSpecificationMapper(UnitSpecificationMapper::getInstance());
        $this->setUnitSpecificationTranslator(new UnitSpecificationTranslator());
        $this->setCourseSpecificationTranslator(new CourseSpecificationTranslator($this->getUnitSpecificationTranslator()));

        $unitInstanceTranslator = new UnitInstanceTranslator();

        $this->setUnitSpecificationMapperProxy(new UnitSpecificationMapperProxy(
            $this->getUnitSpecificationMapper(), $this->getUnitSpecificationTranslator()
        ));

        $this->setCourseSpecificationMapperProxy(new CourseSpecificationMapperProxy(
            $this->getCourseSpecificationMapper(), $this->getCourseSpecificationTranslator(),
            $this->getUnitSpecificationMapper(), $this->getUnitSpecificationTranslator()
        ));

        $assessmentTranslator = new AssessmentTranslator(
            new MarkSchemeFactory(),
            $this->getUnitSpecificationTranslator(),
            $unitInstanceTranslator
        );

        $assessmentTranslator
            ->addSubTranslator(new TaskTranslator())
            ->addSubTranslator(new DueWorkTranslator())
            ->addSubTranslator(new QuizTranslator())
            ->addSubTranslator(new ProjectTranslator($assessmentTranslator))
        ;

        // Proxy for assessment mapper
        $this->setAssessmentMapperProxy(
            new AssessmentMapperProxy(
                AssessmentMapper::getInstance(),
                $assessmentTranslator
            )
        );
    }

    /**
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        $folder = $this->getSource();
        $folder->setParent($this->getFolderMapper()->findById($folder->getParent()->getId()));

        $data['unit'] = $this->getUnitSpecificationMapper()->findByFolderId($this->getSource()->getId());
        $data['access'][$folder->getId()] = $this->getAccess()->getFolderAccess($this->getUser(), [$folder->getId()]);
        $data['access'] = $this->getAccess()->getFolderAccess($this->getUser(), [$folder->getId()]);
        $data['tasks'] = $this->getAssessmentMapperProxy()->findAllForUnitSpecificationId($data["unit"]->getId());
        $data['usages'] = $this->getUnitInstanceMapper()->findAllFoldersUsingCourse($folder)->getColumnValues('id');
        $data['returnUrl'] = $_GET["page"];

        // Retrieve all of the parents for use as breadcrumbs

        $data['breadcrumbs'] = [];

        if ($this->getContext() instanceof FolderModel) {
            foreach (FolderMapper::getInstance()->findParents($folder,null,true) as $parent) {
                $data['breadcrumbs'][] = $parent;
            }
        }

        // Render this mess out

        return $this->renderTwigTemplate(
            $templateHandle,
            '@learningAssessment/builder/component.twig',
            $data,
            $this->hasWriteAccess() || !$this->getConfig('compactMode')
        );
    }

    /* Getters/Setters
     */

    /** @var FolderMapper */
    protected $folderMapper;

    /** @var SubmissionBoxMapper */
    protected $submissionBoxMapper;

    /** @var OnlineAssessmentMapper */
    protected $onlineAssessmentMapper;

    /** @var TaskMapper */
    protected $taskMapper;

    /** @var TaskInstanceMapper */
    protected $taskInstanceMapper = null;

    /** @var CourseSpecificationMapper */
    protected $courseSpecificationMapper;

    /** @var AssessmentMapperProxy */
    protected $assessmentMapperProxy;

    /** @var CourseSpecificationMapperProxy */
    protected $courseSpecificationMapperProxy;

    /** @var UnitSpecificationMapper */
    protected $unitSpecificationMapper;

    /** @var UnitSpecificationMapperProxy */
    protected $unitSpecificationMapperProxy;

    /**
     * Translates between course specification data and domain models
     *
     * TODO: figure out if this should be a part of the course spec mapper
     *
     * @var CourseSpecificationTranslator
     */
    protected $courseSpecificationTranslator;

    /**
     * Translates between unit specification data and domain models
     *
     * TODO: figure out if this should be a part of the unit spec mapper
     *
     * @var UnitSpecificationTranslator
     */
    protected $unitSpecificationTranslator;

    /** @var Component_DefinitionMapper */
    protected $componentDefinitionMapper;

    /** @var Component_InstanceMapper */
    protected $componentInstanceMapper;

    /** @var UnitInstanceMapper */
    protected $unitInstanceMapper;

    /** @var HomepageConfigMapper */
    protected $homepageConfigMapper;

    /** @var  HomepageTemplateMapper */
    protected $homepageTemplateMapper;

    /** @var CourseConfigMapper */
    protected $courseConfigMapper;

    /** @var Error\Mapper */
    protected $errorMapper;

    /** @var UserAccessMapper */
    protected $userAccessMapper;

    /** @var UserMapper */
    protected $userMapper;

    /** @var Access */
    protected $access;

    /**
     * @return FolderMapper
     */
    public function getFolderMapper()
    {
        return $this->folderMapper;
    }

    /**
     * @param FolderMapper $folderMapper
     * @return $this
     */
    public function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
        return $this;
    }

    /**
     * @return TaskMapper
     */
    protected function getTaskMapper()
    {
        return $this->taskMapper;
    }

    /**
     * @param TaskMapper $taskMapper
     * @return $this
     */
    public function setTaskMapper($taskMapper)
    {
        $this->taskMapper = $taskMapper;
        return $this;
    }

    /**
     * @return Component_InstanceMapper
     */
    protected function getComponentInstanceMapper()
    {
        return $this->componentInstanceMapper;
    }

    /**
     * @param Component_InstanceMapper $componentInstanceMapper
     * @return $this
     */
    public function setComponentInstanceMapper($componentInstanceMapper)
    {
        $this->componentInstanceMapper = $componentInstanceMapper;
        return $this;
    }

    /**
     * @return Component_DefinitionMapper
     */
    protected function getComponentDefinitionMapper()
    {
        return $this->componentDefinitionMapper;
    }

    /**
     * Set component definition mapper
     *
     * @param Component_DefinitionMapper $componentDefinitionMapper
     */
    public function setComponentDefinitionMapper($componentDefinitionMapper)
    {
        $this->componentDefinitionMapper = $componentDefinitionMapper;
    }

    /**
     * Get unit instance mapper
     *
     * @return UnitInstanceMapper
     */
    protected function getUnitInstanceMapper()
    {
        return $this->unitInstanceMapper;
    }

    /**
     * Set unit instance mapper
     *
     * @param UnitInstanceMapper $unitInstanceMapper
     */
    public function setUnitInstanceMapper($unitInstanceMapper)
    {
        $this->unitInstanceMapper = $unitInstanceMapper;
    }

    /**
     * Set task mapper
     *
     * @param SubmissionBoxMapper $submissionBoxMapper
     */
    public function setSubmissionBoxMapper($submissionBoxMapper)
    {
        $this->submissionBoxMapper = $submissionBoxMapper;
    }

    /**
     * Get submission box mapper
     *
     * @return SubmissionBoxMapper
     */
    protected function getSubmissionBoxMapper()
    {
        return $this->submissionBoxMapper;
    }

    /**
     * Get online assessment mapper
     *
     * @return OnlineAssessmentMapper
     */
    protected function getOnlineAssessmentMapper()
    {
        return $this->onlineAssessmentMapper;
    }

    /**
     * Set online assessment mapper
     *
     * @param OnlineAssessmentMapper $onlineAssessmentMapper
     */
    public function setOnlineAssessmentMapper($onlineAssessmentMapper)
    {
        $this->onlineAssessmentMapper = $onlineAssessmentMapper;
    }

    /**
     * @return HomepageConfigMapper
     */
    protected function getHomepageConfigMapper()
    {
        return $this->homepageConfigMapper;
    }


    /**
     * @param HomepageConfigMapper $homepageConfigMapper
     * @return static
     */
    public function setHomepageConfigMapper($homepageConfigMapper)
    {
        $this->homepageConfigMapper = $homepageConfigMapper;
        return $this;
    }


    /**
     * @return HomepageTemplateMapper
     */
    protected function getHomepageTemplateMapper()
    {
        return $this->homepageTemplateMapper;
    }


    /**
     * @param HomepageTemplateMapper $homepageTemplateMapper
     * @return static
     */
    public function setHomepageTemplateMapper($homepageTemplateMapper)
    {
        $this->homepageTemplateMapper = $homepageTemplateMapper;
        return $this;
    }

    /**
     * @return CourseConfigMapper
     */
    protected function getCourseConfigMapper()
    {
        return $this->courseConfigMapper;
    }

    /**
     * @param  CourseConfigMapper $courseConfigMapper
     * @return static
     */
    public function setCourseConfigMapper($courseConfigMapper)
    {
        $this->courseConfigMapper = $courseConfigMapper;
        return $this;
    }


    /**
     * @return Error\Mapper
     */
    public function getErrorMapper()
    {
        return $this->errorMapper;
    }


    /**
     * @param Error\Mapper $errorMapper
     * @return static
     */
    public function setErrorMapper($errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
    }


    /**
     * @return UserAccessMapper
     */
    protected function getUserAccessMapper()
    {
        return $this->userAccessMapper;
    }


    /**
     * @param UserAccessMapper $userAccessMapper
     * @return static
     */
    public function setUserAccessMapper($userAccessMapper)
    {
        $this->userAccessMapper = $userAccessMapper;
        return $this;
    }


    /**
     * @return UserMapper
     */
    public function getUserMapper()
    {
        return $this->userMapper;
    }


    /**
     * @param UserMapper $userMapper
     * @return static
     */
    public function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }


    /**
     * @return Access
     */
    protected function getAccess()
    {
        return $this->access;
    }


    /**
     * @param Access $access
     * @return static
     */
    public function setAccess($access)
    {
        $this->access = $access;
        return $this;
    }

    /**
     * @return TaskInstanceMapper
     */
    protected function getTaskInstanceMapper()
    {
        return $this->taskInstanceMapper;
    }

    /**
     * @param TaskInstanceMapper $taskInstanceMapper
     */
    public function setTaskInstanceMapper($taskInstanceMapper)
    {
        $this->taskInstanceMapper = $taskInstanceMapper;
    }

    /**
     * @return CourseSpecificationMapper
     */
    public function getCourseSpecificationMapper()
    {
        return $this->courseSpecificationMapper;
    }

    /**
     * @param CourseSpecificationMapper $courseSpecificationMapper
     * @return static
     */
    public function setCourseSpecificationMapper($courseSpecificationMapper)
    {
        $this->courseSpecificationMapper = $courseSpecificationMapper;
        return $this;
    }

    /**
     * @return UnitSpecificationMapper
     */
    public function getUnitSpecificationMapper()
    {
        return $this->unitSpecificationMapper;
    }

    /**
     * @param UnitSpecificationMapper $unitSpecificationMapper
     * @return static
     */
    public function setUnitSpecificationMapper($unitSpecificationMapper)
    {
        $this->unitSpecificationMapper = $unitSpecificationMapper;
        return $this;
    }

    /**
     * @return CourseSpecificationTranslator
     */
    public function getCourseSpecificationTranslator()
    {
        return $this->courseSpecificationTranslator;
    }

    /**
     * @param CourseSpecificationTranslator $courseSpecificationTranslator
     * @return static
     */
    public function setCourseSpecificationTranslator($courseSpecificationTranslator)
    {
        $this->courseSpecificationTranslator = $courseSpecificationTranslator;
        return $this;
    }

    /**
     * @return UnitSpecificationTranslator
     */
    public function getUnitSpecificationTranslator()
    {
        return $this->unitSpecificationTranslator;
    }

    /**
     * @param UnitSpecificationTranslator $unitSpecificationTranslator
     * @return static
     */
    public function setUnitSpecificationTranslator($unitSpecificationTranslator)
    {
        $this->unitSpecificationTranslator = $unitSpecificationTranslator;
        return $this;
    }

    /**
     * @return CourseSpecificationMapperProxy
     */
    public function getCourseSpecificationMapperProxy()
    {
        return $this->courseSpecificationMapperProxy;
    }

    /**
     * @param CourseSpecificationMapperProxy $courseSpecificationMapperProxy
     * @return static
     */
    public function setCourseSpecificationMapperProxy($courseSpecificationMapperProxy)
    {
        $this->courseSpecificationMapperProxy = $courseSpecificationMapperProxy;
        return $this;
    }

    /**
     * @return UnitSpecificationMapperProxy
     */
    public function getUnitSpecificationMapperProxy()
    {
        return $this->unitSpecificationMapperProxy;
    }

    /**
     * @param UnitSpecificationMapperProxy $unitSpecificationMapperProxy
     * @return static
     */
    public function setUnitSpecificationMapperProxy($unitSpecificationMapperProxy)
    {
        $this->unitSpecificationMapperProxy = $unitSpecificationMapperProxy;
        return $this;
    }

    /**
     * @return AssessmentMapperProxy
     */
    public function getAssessmentMapperProxy()
    {
        return $this->assessmentMapperProxy;
    }

    /**
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @return static
     */
    public function setAssessmentMapperProxy($assessmentMapperProxy)
    {
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        return $this;
    }

    /**
     * @return Ancestry
     */
    public function getAncestry()
    {
        return $this->getFolderMapper()->getAncestry();
    }

    /** @var AbstractTreeBuilder */
    protected $treeBuilder;

    /** @var \Schoolbox\Mapper|Resource\File\ILocator */
    protected $fileMapper;

    /** @var Config\Loader */
    protected $conf;

    /** @var string */
    protected $namespace;

    /** @var string */
    protected $nodeType;

    /**
     * Which tab gets highlighted?
     *
     * @var string
     */
    protected $tab = 'resources';

    /**
     * @param AbstractTreeBuilder $treeBuilder
     * @return self
     */
    public function setTreeBuilder($treeBuilder)
    {
        $this->treeBuilder = $treeBuilder;
        return $this;
    }


    /**
     * @param Config\Loader $conf
     * @return self
     */
    public function setConfig($conf)
    {
        $this->conf = $conf;
        return $this;
    }


    /**
     * @param \Schoolbox\Mapper|Resource\File\ILocator $fileMapper
     * @return self
     */
    public function setFileMapper($fileMapper)
    {
        $this->fileMapper = $fileMapper;
        return $this;
    }


    /**
     * @param string $namespace
     * @return self
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
        return $this;
    }


    /**
     * @param string $nodeType
     * @return self
     */
    public function setNodeType($nodeType)
    {
        $this->nodeType = $nodeType;
        return $this;
    }

    /**
     * @param int $fileId
     * @return FileInterface
     * @throws MapperException
     */
    protected function getFile($fileId)
    {
        /** @var FileInterface $file */
        $file = $this->fileMapper->findById($fileId);

        // urgh...
        if (method_exists($file, 'setOwner')) {
            try {
                $file->setOwner($this->userMapper->findById($file->getOwner()->getId()));
            } catch (MapperException $e) {
                $file->setOwner($this->userMapper->createModel());
            }
        }

        return $file;
    }

    /**
     * @param int $folderId
     * @return FolderInterface
     */
    protected function findFolder($folderId)
    {
        try {
            return $this->getFolder($folderId);

        } catch (MapperExceptionDeleted $e) {
            // Deleted: load the parent (recursively)
            return $this->findFolder($e->getDeletedObject()->getParent()->getId());

        } catch (MapperException $e) {
            return $this->folderMapper->createModel()->setId(0);

        }
    }


    /**
     * @param int $folderId
     * @return FolderInterface
     * @throws MapperException
     */
    protected function getFolder($folderId)
    {
        return $this->folderMapper->findById($folderId);
    }

    /**
     * @param  string $tab
     * @return $this
     */
    public function setTab($tab)
    {
        $this->tab = $tab;
        return $this;
    }
}
