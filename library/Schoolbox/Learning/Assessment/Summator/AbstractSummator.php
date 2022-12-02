<?php

/**
 * AbstractSummator
 *
 * Template Design Pattern
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Summator;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\User;

abstract class AbstractSummator implements ISummator
{
    /**
     * Strategy for deriving the normalised mark for a participant.
     *
     * Defaults to `MarkDerivation\Standard`.
     */
    private MarkDerivation\IStrategy $markDerivationStrategy;

    /**
     * Constructor
     *
     * @param MarkDerivation\IStrategy|null $strategy
     */
    public function __construct(MarkDerivation\IStrategy $strategy = null)
    {
        $this->markDerivationStrategy = $strategy ?? new MarkDerivation\Standard();
    }

    /**
     * @inheritDoc
     */
    public function summateList($values, ?array $weights = null)
    {
        $this->reset();
        $weight = 1;
        foreach ($values as $key => $value) {
            if ($weights && key_exists($key, $weights)) {
                $weight = $weights[$key];
            }
            if (!is_null($value)) {
                $this->aggregate($value, $weight);
            }
        }
        return $this->finalise();
    }

    /**
     * @inheritDoc
     */
    public function summate(Collection $allAssessables, User $user, $publishedOnly)
    {
        if ($allAssessables->count() == 0) {
            return null;
        }

        $this->reset();

        /** @var IAssessable $currentAssessable */
        foreach ($allAssessables as $currentAssessable) {
            $participant = $currentAssessable->getParticipantIfExtant($user);
            if ($participant) {
                $normMark = $this->deriveNormalisedMark($currentAssessable, $participant, $publishedOnly);

                if (null !== $normMark) {
                    $this->aggregate($normMark, $currentAssessable->getWeight());
                }
            }
        }

        return $this->finalise();
    }


    /**
     * Derive the normalised mark to use for this participant in an assessment.
     *
     * @param IAssessable $assessable
     * @param Participant $participant
     * @param bool        $publishedOnly
     * @return float|null
     */
    protected function deriveNormalisedMark(IAssessable $assessable, Participant $participant, bool $publishedOnly): ?float
    {
        return $this->markDerivationStrategy->deriveNormalisedMark($participant, $publishedOnly);
    }


    /**
     * @inheritdoc
     */
    public function summateAllAssessablesForUser(Collection $allAssessables, User $user, $publishedOnly)
    {
        return $this->summate($allAssessables, $user, $publishedOnly);
    }

    /**
     * @inheritdoc
     */
    public function summateSingleAssessable(IAssessable $assessable, $publishedOnly)
    {
        $this->reset();
        foreach ($assessable->getAllParticipants() as $participant) {
            $normMark = $this->deriveNormalisedMark($assessable, $participant, $publishedOnly);

            if (null !== $normMark) {
                // Weight doesn't matter within the context of a single assessment
                $this->aggregate($normMark, $assessable->getWeight());
            }
        }
        return $this->finalise();
    }

    /**
     * @inheritdoc
     */
    public function summateAllAssessables(Collection $allAssessables, $publishedOnly, ISummator $subSummator)
    {
        $this->reset();
        /** @var IAssessable $assessable */
        foreach ($allAssessables as $assessable) {
            $val = $subSummator->summateSingleAssessable($assessable, $publishedOnly);
            if (isset($val)) {
                $this->aggregate($val, $assessable->getWeight());
            }
        }
        return $this->finalise();
    }

    /**
     * The sum total weight of all the assessables
     *
     * @param Collection $allAssessables
     * @return int
     */
    abstract protected function calculateTotalWeight(Collection $allAssessables);

    /**
     * @param float $val
     * @param float $assessableWeight
     * @return void
     */
    abstract protected function aggregate($val, $assessableWeight);

    /**
     * @return void
     */
    abstract protected function reset();

    /**
     * @return float|null
     */
    abstract protected function finalise();

    /**
     * @inheritDoc
     */
    public function contribute(IAssessable $assessable): bool
    {
        return !($assessable instanceof Task);
    }
}
