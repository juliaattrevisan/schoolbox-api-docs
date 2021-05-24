<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Response;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\User;

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
     * @param User        $student If set, only return this student's responses
     * @return Collection|IResponse[]
     */
    public function findForAssessment(IAssessable $assessment, User $student = null)
    {
        $assessments = new Collection();
        foreach ($this->subSources as $subSource) {
            if ($subSource->recognizes($assessment)) {
                $assessments = $assessments->merge($subSource->findForAssessment($assessment, $student));
            }
        }
        return $assessments;
    }

    /**
     * @param Collection|IAssessable[] $assessments
     * @param User                     $student If set, only return this student's responses
     * @return Collection|IResponse[][]
     */
    public function findForAssessmentCollection(Collection $assessments, User $student = null)
    {
        $responses = new Collection();
        foreach ($this->subSources as $subSource) {
            $responses = $responses->union($subSource->findForAssessmentCollection($assessments, $student));
        }
        return $responses;
    }

    /**
     * Find a particular $response by its id among a set of IResponses
     *
     * @param int $responseId
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
     * @param User $student
     * @return Collection|IResponse[]
     */
    public function findForStudent(User $student)
    {
        $responses = new Collection();
        foreach ($this->subSources as $subSource) {
            $responses = $responses->merge($subSource->findForStudent($student));
        }
        return $responses;
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
