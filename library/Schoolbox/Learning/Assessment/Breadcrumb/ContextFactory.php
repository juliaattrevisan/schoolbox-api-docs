<?php
/**
 * ContextFactory
 *
 * Create a trail of crumbs for screens where you spawn Assessments
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\Breadcrumb;

use \Schoolbox\Resource\Folder\Mapper as FolderMapper;
use \Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Course\Unit\ImplementationMapperProxy as UnitImplementationDataSource;
use Schoolbox\Learning\Course\Unit\SpecificationMapperProxy as UnitSpecificationDataSource;

class ContextFactory
{
    /**
     * @var FolderMapper
     */
    protected $folderMapper;

    /**
     * @var UnitImplementationDataSource
     */
    protected $unitImplementationDataSource;

    /**
     * @var UnitSpecificationDataSource
     */
    protected $unitSpecificationDataSource;

    /**
     * @var AssessmentDataSource
     */
    protected $assessmentDataSource;

    /**
     * @var int
     */
    protected $contextId;

    /**
     * @var string
     */
    protected $contextType;

    /**
     * ContextFactory constructor.
     * @param $contextId
     * @param $contextType
     * @param $folderMapper
     * @param $unitImplementationDataSource
     * @param $unitSpecificationDataSource
     * @param $assessmentDataSource
     */
    public function __construct($contextId, $contextType, $folderMapper, $unitImplementationDataSource, $unitSpecificationDataSource, $assessmentDataSource)
    {
        $this->setFolderMapper($folderMapper);
        $this->setUnitImplementationDataSource($unitImplementationDataSource);
        $this->setUnitSpecificationDataSource($unitSpecificationDataSource);
        $this->setAssessmentDataSource($assessmentDataSource);

        $this->setContextId($contextId);
        $this->setContextType($contextType);
    }

    /**
     * Produce breadcrumb trail
     *
     * @return array
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function produce()
    {
        $trail = [];
        $trail[] = ['Learning', '#'];

        try {
            if ($this->getContextType() == 'folder') {
                $context = $this->getFolderMapper()->findById($this->getContextId());
                $trail[] = [$context->getName(), "/homepage/{$context->getId()}"];
            } elseif ($this->getContextType() == 'unit') {
                $context = $this->getUnitImplementationDataSource()->findById($this->getContextId());
                $trail[] = [$context->getTitle(), "/homepage/{$context->getId()}"];
            } elseif ($this->getContextType() == 'unitspec') {
                $context = $this->getUnitSpecificationDataSource()->findById($this->getContextId());
                $trail[] = [$context->getFolder()->getName(), "/homepage/{$context->getId()}"];
            } elseif ($this->getContextType() == 'project') {
                $context = $this->getAssessmentDataSource()->findById($this->getContextId());
                $trail[] = [$context->getTitle(), "/project/{$context->getId()}"];
            }
        }
        catch (\Schoolbox\Mapper\Exception\Deleted $e) {

            // noop?
        }

        $trail[] = ["Create Assessment", '#'];

        return $trail;
    }

    /* Getter/Setter
     */

    /**
     * @return FolderMapper
     */
    protected function getFolderMapper()
    {
        return $this->folderMapper;
    }

    /**
     * @param FolderMapper $folderMapper
     */
    protected function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }

    /**
     * @return UnitImplementationDataSource
     */
    protected function getUnitImplementationDataSource()
    {
        return $this->unitImplementationDataSource;
    }

    /**
     * @param UnitImplementationDataSource $unitImplementationDataSource
     */
    protected function setUnitImplementationDataSource($unitImplementationDataSource)
    {
        $this->unitImplementationDataSource = $unitImplementationDataSource;
    }

    /**
     * @return UnitSpecificationDataSource
     */
    protected function getUnitSpecificationDataSource()
    {
        return $this->unitSpecificationDataSource;
    }

    /**
     * @param UnitSpecificationDataSource $unitSpecificationDataSource
     */
    protected function setUnitSpecificationDataSource($unitSpecificationDataSource)
    {
        $this->unitSpecificationDataSource = $unitSpecificationDataSource;
    }

    /**
     * @return AssessmentDataSource
     */
    protected function getAssessmentDataSource()
    {
        return $this->assessmentDataSource;
    }

    /**
     * @param AssessmentDataSource $assessmentDataSource
     */
    protected function setAssessmentDataSource($assessmentDataSource)
    {
        $this->assessmentDataSource = $assessmentDataSource;
    }

    /**
     * @return int
     */
    protected function getContextId()
    {
        return $this->contextId;
    }

    /**
     * @param int $contextId
     */
    protected function setContextId($contextId)
    {
        $this->contextId = $contextId;
    }

    /**
     * @return string
     */
    protected function getContextType()
    {
        return $this->contextType;
    }

    /**
     * @param string $contextType
     */
    protected function setContextType($contextType)
    {
        $this->contextType = $contextType;
    }
}