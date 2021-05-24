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
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\User;

abstract class AbstractSummator implements ISummator
{
    /**
     * @inheritDoc
     */
    public function summateList($values)
    {
        $this->reset();
        foreach ($values as $value) {
            if (!is_null($value)) {
                $this->aggregate($value, 1);
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
                $normMark = $participant->getNormalisedMark($publishedOnly);
                if (null !== $normMark) {
                    $this->aggregate($normMark, $currentAssessable->getWeight());
                }
            }
        }

        return $this->finalise();
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
            $normMark = $participant->getNormalisedMark($publishedOnly);

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
