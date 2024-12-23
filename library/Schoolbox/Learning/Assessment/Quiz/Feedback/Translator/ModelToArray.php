<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Quiz\Feedback\Translator;

use Alaress\HTMLPurifier\IPurifier;
use Error;
use Schoolbox\Json\Decorator\LinkDecorator;
use Schoolbox\Learning\Assessment\Quiz\Feedback\Model;
use Schoolbox\Learning\Assessment\Translator\AssessmentForFeed;
use Schoolbox\Learning\Assessment\Translator\Links;

/**
 * Translator for converting Quiz Feedback to an array representation, for
 * outputting in a JSON response.
 */
class ModelToArray
{
    /**
     * Constructor.
     *
     * @param AssessmentForFeed $assessmentTranslator
     * @param \Schoolbox\User\Translator\Author\ModelToArray $userTranslator
     * @param Links $linkTranslator
     * @param LinkDecorator $linkDecorator
     * @param IPurifier $purifier
     */
    public function __construct(
        private AssessmentForFeed $assessmentTranslator,
        private \Schoolbox\User\Translator\Author\ModelToArray $userTranslator,
        private Links $linkTranslator,
        private LinkDecorator $linkDecorator,
        private IPurifier $purifier,
    ) {
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
        $this->linkDecorator->decorate([], $model->getReviewer());

        if ($model->getReviewer()) {
            $instructor = call_user_func($this->userTranslator, $model->getReviewer());
        } else {
            $instructor = null;
        }

        return [
            'id'             => $model->getId(),
            'mark'           => $model->getHumanReadableMark(),
            'normalisedMark' => $model->getNormalisedMark(),
            'comment'        => $this->purifier->purify($model->getComment()),
            'publishedAt'    => $model->getPublishedAt()->getUsingFormat(DATE_ATOM),
            'instructor'     => $instructor,
            'assessment'     => $assessment,
            '_links'         => call_user_func($this->linkTranslator, $model->getAssessable(), $model->getReviewee()),
        ];
    }
}
