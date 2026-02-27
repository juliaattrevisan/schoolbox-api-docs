<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Response\SubSource;

use Alaress\Database\Predicate\Builder;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Response\IResponseDataSource;
use Schoolbox\Mapper\AbstractMapperProxy;
use Schoolbox\User;

/**
 * Base class for response sub-sources.
 */
abstract class AbstractSubSource implements IResponseDataSource
{
    /**
     * @var AbstractMapperProxy
     */
    protected $mapperProxy;

    /**
     * The field of the mapper proxy's table which contains the student ID.
     *
     * @var string
     */
    protected $studentField;

    /**
     * Constructor
     *
     * @param AbstractMapperProxy $mapperProxy
     */
    public function __construct(AbstractMapperProxy $mapperProxy)
    {
        $this->mapperProxy = $mapperProxy;
    }

    /**
     * @param IAssessable $assessment
     * @param User|null   $student If set, only return this student's responses
     * @return Collection|IResponse[]
     */
    public function findForAssessment(IAssessable $assessment, ?User $student = null)
    {
        if ($this->recognizes($assessment)) {
            // Only fetch for student if provided
            $columns = ['assessment_id' => $assessment->getId(),];
            if ($student instanceof User) {
                $columns[$this->studentField] = $student->getId();
            }

            return $this->mapperProxy->findByColumnList($columns)
                                     ->map(function ($item) use ($assessment) {
                                         /** @var IResponse $item */
                                         return $item->setAssessable($assessment);
                                     });
        }
        return new Collection();
    }

    /**
     * @param Collection<IAssessable> $assessments
     * @param Collection<\Schoolbox\User>|null $users If set, only return this student's responses
     * @param array $options Options for findByColumnList
     * @return Collection|IResponse[][]
     */
    public function findForAssessmentCollection(Collection $assessments, ?Collection $users = null, array $options = [])
    {
        // Find responses which belong to any assessments this recognizes
        $recognizedIds = $assessments->filter([$this, 'recognizes'])->getPropertyValues('id');

        if (!\count($recognizedIds)) {
            return new Collection();
        }

        // Only fetch for student if provided
        $columns = ['assessment_id' => $recognizedIds,];

        if ($users instanceof \Traversable && $users->count()) {
            foreach ($users as $user) {
                /* SOMEWHERE this can spawn a collection of a singular null value
                 */
                if ($user instanceof \Schoolbox\User) {
                    $columns[$this->studentField][] = $user->getId();
                }
            }
        }

        // Group responses by assessment
        /** @var Collection|IResponse[][] $responses */
        $responses = $this->mapperProxy
            ->findByColumnList($columns, $options)
            ->groupByCallback(function (IResponse $v) {
                return $v->getAssessable()->getId();
            })
        ;

        // Add assessment into each response
        foreach ($assessments as $assessment) {
            if (isset($responses[$assessment->getId()])) {
                foreach ($responses[$assessment->getId()] as $response) {
                    $response->setAssessable($assessment);
                }
            }
        }

        return $responses;
    }

    /**
     * @inheritdoc
     */
    public function findForStudent(User $student)
    {
        return $this->mapperProxy->findByColumnList([$this->studentField => $student->getId()]);
    }

    /**
     * @inheritdoc
     */
    public function save(IResponse $response)
    {
        if ($this->recognizes($response->getAssessable())) {
            $this->mapperProxy->save($response);
        }
    }

    /**
     * @inheritdoc
     */
    public function delete(IResponse $response)
    {
        if ($this->recognizes($response->getAssessable())) {
            $this->mapperProxy->delete($response);
        }
    }

    /**
     * @return Builder
     */
    public function getPredicateBuilder()
    {
        return $this->mapperProxy->getPredicateBuilder();
    }

    /**
     * Gets a typed Collection of objects that match the specified predicates
     *
     * @param Builder $pb
     * @param array   $options
     * @return GenericCollection
     */
    public function findByPredicates(Builder $pb, $options = array())
    {
        return $this->mapperProxy->findByPredicates($pb, $options);
    }
}
