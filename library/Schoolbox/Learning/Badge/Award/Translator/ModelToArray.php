<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Award\Translator;

use Alaress_Date_Format;
use Error;
use Schoolbox\Learning\Badge\Award\Model;

/**
 * Translator for converting an Award instance to an array representation, for
 * outputting in a JSON response.
 *
 * This converts the Award instance into an array format, using the Badge Translator
 * to convert the badge itself into an array representation.
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
    private $userTranslator;


    /**
     * Translator for converting a Badge instance.
     *
     * (callable isn't a valid typedef)
     *
     * @var callable
     */
    private $badgeTranslator;


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
     * @param callable $userTranslator
     * @param callable $badgeTranslator
     * @param callable $linkTranslator
     */
    public function __construct(callable $userTranslator, callable $badgeTranslator, callable $linkTranslator)
    {
        $this->userTranslator  = $userTranslator;
        $this->badgeTranslator = $badgeTranslator;
        $this->linkTranslator  = $linkTranslator;
    }


    /**
     * @param Model $model
     * @param bool  $translateParentAward If true, translate this award's
     *                                    parent award (if there is one).
     *                                    Don't attempt to translate an award
     *                                    which is already a parent.
     * @return array
     */
    public function __invoke(Model $model, bool $translateParentAward = true): array
    {
        /* Convert a User instance to its array equivalent
         *
         * When a user can't be found (soft- or hard-deleted, broken data somehow, etc)
         * just return null since there's no real way we can communicate this circumstance
         * In normal usage Award instances must come with a recipient and a guy who gave the
         * award; these two fields can't be empty
         */
        try {
            $recipient = call_user_func($this->userTranslator, $model->getRecipient());
        } catch (Error $e) {
            $recipient = null;
        }

        try {
            $awardedBy = call_user_func($this->userTranslator, $model->getAwardedBy());
        } catch (Error $e) {
            $awardedBy = null;
        }

        return [
            'id'        => $model->getId(),
            'badge'     => call_user_func($this->badgeTranslator, $model->getBadge()),
            'status'    => $model->getStatus(),
            'reason'    => $model->getReason(),
            'isGoal'    => $model->isGoal(),
            'award'     => $translateParentAward && $model->getAward()
                ? $this->__invoke($model->getAward(), false)
                : null,
            'recipient' => $recipient,
            'awardedBy' => $awardedBy,
            'awardedAt' => $model->getAwardedAt() && !$model->getAwardedAt()->isNull()
                ? $model->getAwardedAt()->getUsingFormat(DATE_ATOM)
                : null,
            'expiresAt' => $model->getExpiresAt() && !$model->getExpiresAt()->isNull()
                ? $model->getExpiresAt()->getUsingFormat(Alaress_Date_Format::DATABASE_DATE_FORMAT)
                : null,
            'viewedOn'  => $model->getViewedOn() && !$model->getViewedOn()->isNull()
                ? $model->getViewedOn()->getUsingFormat(Alaress_Date_Format::DATABASE_DATE_FORMAT)
                : null,
            '_links'    => call_user_func($this->linkTranslator, $model),
        ];
    }
}
