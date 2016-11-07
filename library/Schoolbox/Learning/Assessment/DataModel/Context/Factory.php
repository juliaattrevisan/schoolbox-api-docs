<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 */
namespace Schoolbox\Learning\Assessment\DataModel\Context;

use Schoolbox\Learning\Assessment\AssessmentContextType;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Learning\Course\Unit\ImplementationMapperProxy as UnitImplMapperProxy;
use Schoolbox\Learning\Course\Unit\SpecificationMapperProxy as UnitSpecMapperProxy;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;


class Factory
{

    /**
     * @var FolderMapper
     */
    var $folderMapper;
    /**
     * @var UnitImplMapperProxy
     */
    var $unitImplMapperProxy;
    /**
     * @var AssessmentMapperProxy
     */
    var $assessmentMapperProxy;
    /**
     * @var UnitSpecMapperProxy
     */
    var $unitSpecMapperProxy;

    public function __construct(
        FolderMapper $folderMapper,
        UnitImplMapperProxy $unitImplMapperProxy,
        AssessmentMapperProxy $assessmentMapperProxy,
        UnitSpecMapperProxy $unitSpecMapperProxy) {

        $this->setFolderMapper($folderMapper);
        $this->setUnitImplMapperProxy($unitImplMapperProxy);
        $this->setAssessmentMapperProxy($assessmentMapperProxy);
        $this->setUnitSpecMapperProxy($unitSpecMapperProxy);
    }


    /**
     * Used to produce context model for a given type, will include parent if context is project
     * @param $type string ContextType string, with no plural
     * @param $id
     * @return AssessmentContext
     */
    public function produce($type, $id)
    {
        // Remove that silly s
        if(substr($type, -1) == 's') {
            $type = substr($type,0,-1);
        }

        switch ($type) {
            case AssessmentContextType::TYPE_FOLDER:
                $mapper = $this->getFolderMapper();
                break;
            case AssessmentContextType::TYPE_UNIT_IMPLEMENTATION:
                $mapper = $this->getUnitImplMapperProxy();
                break;
            case AssessmentContextType::TYPE_PROJECT:
                $mapper = $this->getAssessmentMapperProxy();
                break;
            case AssessmentContextType::TYPE_UNIT_SPECIFICATION:
                $mapper = $this->getUnitSpecMapperProxy();
                break;
            default:
                throw new \InvalidArgumentException('Invalid context type string');
        }

        return $mapper->findById($id);

    }

    /**
     * @return FolderMapper
     */
    public function getFolderMapper()
    {
        return $this->folderMapper;
    }

    /**
     * @param FolderMapper $folderMapper
     */
    public function setFolderMapper(FolderMapper $folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }

    /**
     * @return UnitImplMapperProxy
     */
    public function getUnitImplMapperProxy()
    {
        return $this->unitImplMapperProxy;
    }

    /**
     * @param UnitImplMapperProxy $unitImplMapperProxy
     */
    public function setUnitImplMapperProxy(UnitImplMapperProxy $unitImplMapperProxy)
    {
        $this->unitImplMapperProxy = $unitImplMapperProxy;
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
     */
    public function setAssessmentMapperProxy(AssessmentMapperProxy $assessmentMapperProxy)
    {
        $this->assessmentMapperProxy = $assessmentMapperProxy;
    }

    /**
     * @return UnitSpecMapperProxy
     */
    public function getUnitSpecMapperProxy()
    {
        return $this->unitSpecMapperProxy;
    }

    /**
     * @param UnitSpecMapperProxy $unitSpecMapperProxy
     */
    public function setUnitSpecMapperProxy(UnitSpecMapperProxy $unitSpecMapperProxy)
    {
        $this->unitSpecMapperProxy = $unitSpecMapperProxy;
    }


}