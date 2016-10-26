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
use Alaress\Web\Controller as Base;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Translator as UnitSpecificationTranslator;
use Schoolbox\Learning\Course\Unit\SpecificationMapperProxy as UnitSpecificationMapperProxy;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;

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

        $this->setUnitInstanceMapper(UnitInstanceMapper::getInstance());
        $this->setAccess(Access::getInstance());

        // TODO: I couldn't get DI to play so all of this stuff is kerfludged in here rather uglily as you will see below

        $this->setUnitSpecificationMapper(UnitSpecificationMapper::getInstance());
        $this->setUnitSpecificationTranslator(new UnitSpecificationTranslator());

        $unitInstanceTranslator = new UnitInstanceTranslator();

        $this->setUnitSpecificationMapperProxy(new UnitSpecificationMapperProxy(
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
        // Get the current folder and hydrate its parent

        $folder = $this->getSource();
        $folder->setParent($this->getFolderMapper()->findById($folder->getParent()->getId()));

        // Get the units details

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

    /** @var UnitInstanceMapper */
    protected $unitInstanceMapper;

    /**
     * @return UnitInstanceMapper
     */
    protected function getUnitInstanceMapper()
    {
        return $this->unitInstanceMapper;
    }

    /**
     * @param UnitInstanceMapper $unitInstanceMapper
     */
    public function setUnitInstanceMapper($unitInstanceMapper)
    {
        $this->unitInstanceMapper = $unitInstanceMapper;
    }

    /** @var Access */
    protected $access;

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

    /** @var UnitSpecificationMapper */
    protected $unitSpecificationMapper;

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

    /** @var UnitSpecificationTranslator */
    protected $unitSpecificationTranslator;

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

    /** @var UnitSpecificationMapperProxy */
    protected $unitSpecificationMapperProxy;

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

    /** @var AssessmentMapperProxy */
    protected $assessmentMapperProxy;

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
}
