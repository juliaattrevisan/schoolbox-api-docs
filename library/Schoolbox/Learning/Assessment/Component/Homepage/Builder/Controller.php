<?php
/**
 * Course builder homepage component controller
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Component\Homepage\Builder;

use Component_Homepage_ControllerAbstract;
use Component_Instance;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\Permission\Access\UserAccess;
use SitePage;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Course\Unit\SpecificationMapperProxy as UnitSpecificationMapperProxy;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;

class Controller extends Component_Homepage_ControllerAbstract
{
    /** @var UnitInstanceMapper */
    protected $unitInstanceMapper;

    /** @var UnitSpecificationMapperProxy */
    protected $unitSpecificationMapperProxy;

    /** @var AssessmentMapperProxy */
    protected $assessmentMapperProxy;

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

        $this->setUnitSpecificationMapperProxy($this->getSitePage()->getDi()->get('unitSpecificationDataSource'));
        $this->setAssessmentMapperProxy($assessmentProxy = $this->getSitePage()->getDi()->get('assessmentDataSource'));

    }

    /**
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        // Get the current folder and hydrate its parent
        $folder = $this->getSource();

        // Get the units details
        try {
            $unit = $this->getUnitSpecificationMapperProxy()->findByFolderId($this->getSource()->getId());

            $data = [
                'unit'   => $unit,
                'tasks'  => $this->getAssessmentMapperProxy()->findAllForContext($unit),
                'usages' => $this->getUnitInstanceMapper()->findAllFoldersUsingCourse($folder)->getColumnValues('id'),
            ];
        }
        catch(MapperException $e) {

        }

        $data = array_merge($data, [
            'canRead'     => $this->getContentPermission() >= UserAccess::ACCESS_READ,
            'canWrite'    => $this->getContentPermission() >= UserAccess::ACCESS_WRITE,
            'returnUrl'   => $_GET["page"],
            'breadcrumbs' => [],
        ]);

        // Retrieve all of the parents for use as breadcrumbs
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
    public function setUnitSpecificationMapperProxy(UnitSpecificationMapperProxy $unitSpecificationMapperProxy)
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
    public function setAssessmentMapperProxy(AssessmentMapperProxy $assessmentMapperProxy)
    {
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        return $this;
    }
}
