<?php

/**
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 */

namespace Schoolbox\Learning\Assessment\DataModel\Context;

use Schoolbox\Learning\Assessment\AssessmentContextType;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;

/**
 * Class Factory
 *
 * @package Schoolbox\Learning\Assessment\DataModel\Context
 */
class Factory
{

    /**
     * @var FolderMapper
     */
    private $folderMapper;

    /**
     * @var UnitImplementationMapper
     */
    private $unitImplementationMapper;

    /**
     * @var AssessmentMapperProxy
     */
    private $assessmentMapperProxy;

    /**
     * @var UnitSpecificationMapper
     */
    private $unitSpecificationMapper;

    /**
     * Factory constructor.
     * @param FolderMapper $folderMapper
     * @param UnitImplementationMapper $unitImplementationMapper
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param UnitSpecificationMapper $unitSpecificationMapper
     */
    public function __construct(
        FolderMapper $folderMapper,
        UnitImplementationMapper $unitImplementationMapper,
        AssessmentMapperProxy $assessmentMapperProxy,
        UnitSpecificationMapper $unitSpecificationMapper
    ) {
        $this->setFolderMapper($folderMapper);
        $this->setUnitImplementationMapper($unitImplementationMapper);
        $this->setAssessmentMapperProxy($assessmentMapperProxy);
        $this->setUnitSpecificationMapper($unitSpecificationMapper);
    }


    /**
     * Used to produce context model for a given type, will include parent if context is project
     * @param string $type ContextType string, with no plural
     * @param int $id
     * @return AssessmentContext
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \UnexpectedValueException
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
                $mapper = $this->getUnitSpecificationMapper();
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
     * @return static
     */
    public function setFolderMapper(FolderMapper $folderMapper)
    {
        $this->folderMapper = $folderMapper;
        return $this;
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
     * @return static
     */
    public function setUnitImplementationMapper(UnitImplementationMapper $unitImplementationMapper)
    {
        $this->unitImplementationMapper = $unitImplementationMapper;
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
    public function setUnitSpecificationMapper(UnitSpecificationMapper $unitSpecificationMapper)
    {
        $this->unitSpecificationMapper = $unitSpecificationMapper;
        return $this;
    }
}
