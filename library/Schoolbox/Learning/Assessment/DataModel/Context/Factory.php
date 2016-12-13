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
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Course\Unit\SpecificationMapperProxy as UnitSpecMapperProxy;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;


class Factory
{

    /**
     * @var FolderMapper
     */
    var $folderMapper;
    /**
     * @var UnitImplementationMapper
     */
    var $unitImplementationMapper;
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
        UnitImplementationMapper $unitImplementationMapper,
        AssessmentMapperProxy $assessmentMapperProxy,
        UnitSpecMapperProxy $unitSpecMapperProxy
    ) {

        $this->setFolderMapper($folderMapper);
        $this->setUnitImplementationMapper($unitImplementationMapper);
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
        if (substr($type, -1) == 's') {
            $type = substr($type, 0, -1);
        }

        switch ($type) {
            case AssessmentContextType::TYPE_FOLDER:
                $mapper = $this->getFolderMapper();
                break;
            case AssessmentContextType::TYPE_UNIT_IMPLEMENTATION:
                $mapper = $this->getUnitImplementationMapper();
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
     * @return UnitImplementationMapper
     */
    public function getUnitImplementationMapper()
    {
        return $this->unitImplementationMapper;
    }

    /**
     * @param UnitImplementationMapper $unitImplementationMapper
     */
    public function setUnitImplementationMapper(UnitImplementationMapper $unitImplementationMapper)
    {
        $this->unitImplementationMapper = $unitImplementationMapper;
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