<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Feedback\Translator;

use Error;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model;

/**
 * Translator for converting DueWork Feedback to an array representation, for
 * outputting in a JSON response.
 */
class ModelToArray
{
    /**
     * Translator for converting a user within the Award model.
     *
     * This applies to the person tho received the award and the person
     * who gave the award
     *
     * (callable isn't a valid typedef)
     *
     * @var callable
     */
    private $assessmentTranslator;


    /**
     * Translator for converting a Badge instance.
     *
     * (callable isn't a valid typedef)
     *
     * @var callable
     */
    private $userTranslator;


    /**
     * Translator for generating urls relevant to an Award
     *
     * (callable isn't a valid typedef)
     *
     * @var callable
     */
    private $linkTranslator;


    /**
     * Constructor
     *
     * @param callable $assessmentTranslator
     * @param callable $userTranslator
     * @param callable $linkTranslator
     */
    public function __construct(callable $assessmentTranslator, callable $userTranslator, callable $linkTranslator)
    {
        $this->assessmentTranslator = $assessmentTranslator;
        $this->userTranslator       = $userTranslator;
        $this->linkTranslator       = $linkTranslator;
    }


    /**
     * @param Model $model
     * @return array
     */
    public function __invoke(Model $model): array
    {
        try {
            $assessment = call_user_func($this->assessmentTranslator, $model->getAssessable());
        } catch (Error $e) {
            $assessment = null;
        }

        return [
            'id'             => $model->getId(),
            'mark'           => $model->getHumanReadableMark(),
            'normalisedMark' => $model->getNormalisedMark(),
            'comment'        => $model->getComment(),
            'publishedAt'    => $model->getPublishedAt()->getDatabase(),
            'instructor'     => call_user_func($this->userTranslator, $model->getReviewer()),
            'assessment'     => $assessment,
            '_links'         => call_user_func($this->linkTranslator, $model->getAssessable(), $model->getReviewee()),
        ];
    }
}
