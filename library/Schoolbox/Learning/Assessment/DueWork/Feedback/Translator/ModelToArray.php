<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Feedback\Translator;

use Alaress\HTMLPurifier\IPurifier;
use Error;
use Schoolbox\Json\Decorator\LinkDecorator;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model;
use Schoolbox\Learning\Assessment\Translator\AssessmentForFeed;
use Schoolbox\Learning\Assessment\Translator\Links;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Config\Loader as ConfigLoader;

/**
 * Translator for converting DueWork Feedback to an array representation, for
 * outputting in a JSON response.
 */
class ModelToArray
{
    /**
     * @param ConfigLoader                                   $configLoader
     * @param AssessmentForFeed                              $assessmentTranslator
     * @param \Schoolbox\User\Translator\Author\ModelToArray $userTranslator
     * @param Links                                          $linkTranslator
     * @param LinkDecorator                                  $linkDecorator
     */
    public function __construct(
        private ConfigLoader $configLoader,
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

        /* The gradientMark can be:
         * - null: No mark was given
         * - string: The gradient value to use, if the config is enabled and a mark exists
         */
        $gradientMark = null;
        if (is_numeric($model->getNormalisedMark()) && $this->configLoader->get(ConfigMapper::NAME_GRADE_GRADIENT_ENABLED)) {
            $gradientMark = (string)floor($model->getNormalisedMark() / 10);
        }

        return [
            'id'             => $model->getId(),
            'mark'           => $model->getHumanReadableMark(),
            'normalisedMark' => $model->getNormalisedMark(), /* DEPRECATED IN 25.0 */
            'gradientMark'   => $gradientMark,
            'comment'        => $this->purifier->purify($model->getComment()),
            'publishedAt'    => $model->getPublishedAt()->getUsingFormat(DATE_ATOM),
            'instructor'     => call_user_func($this->userTranslator, $model->getReviewer()),
            'assessment'     => $assessment,
            '_links'         => call_user_func($this->linkTranslator, $model->getAssessable(), $model->getReviewee()),
        ];
    }
}
