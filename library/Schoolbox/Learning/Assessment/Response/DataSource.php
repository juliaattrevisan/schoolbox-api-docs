<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Response;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IResponse;

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
     * @param IAssessable $assessment
     * @return Collection|IResponse[]
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

    /**
     * Find a particular $response by its id among a set of IResponses
     *
     * @param $responseId
     * @param IAssessable $assessment
     * @return IResponse
     * @throws \OutOfBoundsException
     */
    public function findInAssessment($responseId, IAssessable $assessment)
    {
        $allResponses = $this->findForAssessment($assessment);

        foreach ($allResponses as $currentResponse) {
            if ($currentResponse->getId() == $responseId) {
                return $currentResponse;
            }
        }

        /* Requested response doesn't exist in this Assessment
         */
        throw new \OutOfBoundsException('Response not found in this assessment');
    }

    /**
     * @param IResponse $response
     */
    public function save(IResponse $response)
    {
        foreach ($this->subSources as $subSource) {
            if ($subSource->recognizes($response->getAssessable())) {
                $subSource->save($response);
                return;
            }
        }
    }

    /**
     * @param IResponse $response
     */
    public function delete(IResponse $response)
    {
        foreach ($this->subSources as $subSource) {
            if ($subSource->recognizes($response->getAssessable())) {
                $subSource->delete($response);
                return;
            }
        }
    }
}
