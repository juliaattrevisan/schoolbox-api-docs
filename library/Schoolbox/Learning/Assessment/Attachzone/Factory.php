<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Attachzone;

use Schoolbox\Assessment\Course\Task;
use Schoolbox\Learning\Assessment\DataModel\Translator\DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Types;
use Schoolbox\Resource\Attachment\Zone;

/**
 * Factory for creating \Schoolbox\Resource\Attachment\Zone instances for an
 * Assessment, or a particular assessment type.
 */
class Factory
{
    protected $assessmentTypes;

    public function __construct(Types $assessmentTypes)
    {
        $this->assessmentTypes = $assessmentTypes;
    }

    /**
     * Creates a zone from a given assessment.
     *
     * @param IAssessable $assessment
     * @return Zone
     */
    public function buildForAssessment(IAssessable $assessment)
    {
        if ($assessment instanceof Task) {
            return $this->buildForTask();
        } elseif ($assessment instanceof DueWork) {
            return $this->buildForDueWork();
        }
        return $this->buildGeneric();
    }

    /**
     * Creates a zone from a given assessment type.
     *
     * @param int $type
     * @return Zone
     */
    public function buildForAssessmentType($type)
    {
        if ($this->assessmentTypes->isTask($type)) {
            return $this->buildForTask();
        } elseif ($this->assessmentTypes->isDueWork($type)) {
            return $this->buildForDueWork();
        }
        return $this->buildGeneric();
    }

    /**
     * @return Zone
     */
    protected function buildGeneric()
    {
        return new Zone('files');
    }

    /**
     * @return Zone
     */
    protected function buildForTask()
    {
        $zone = new Zone('files');
        $zone->setMulti(false);
        return $zone;
    }

    /**
     * @return Zone
     */
    protected function buildForDueWork()
    {
        $zone = new Zone('files');
        $zone->setMulti(true);
        return $zone;
    }
}
