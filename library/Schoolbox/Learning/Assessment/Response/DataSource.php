<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Response;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IAssessable;

/**
 * The single source of truth for assessment responses.
 *
 * This class is a composite of various instances of IResponseDataSource.
 */
class DataSource implements IResponseDataSource
{
    /**
     * @var IResponseDataSource[]
     */
    protected $subSources = [];

    /**
     * Adds a new source.
     *
     * @param IResponseDataSource $subSource
     * @return static
     */
    public function addSubSource(IResponseDataSource $subSource)
    {
        $this->subSources[] = $subSource;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function recognizes(IAssessable $assessment)
    {
        foreach ($this->subSources as $subSource) {
            if ($subSource->recognizes($assessment)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function findForAssessment(IAssessable $assessment)
    {
        $assessments = new Collection();
        foreach ($this->subSources as $subSource) {
            if ($subSource->recognizes($assessment)) {
                $assessments = $assessments->merge($subSource->findForAssessment($assessment));
            }
        }
        return $assessments;
    }
}
