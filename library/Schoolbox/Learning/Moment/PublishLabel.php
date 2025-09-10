<?php

namespace Schoolbox\Learning\Moment;

use Schoolbox\Learning\Moment\Moderation\ModerationRequired;
use Schoolbox\User;

class PublishLabel
{
    /**
     * Two different ways to express the same outcome; the Approve variant is chosen
     * for exactly one workflow but it ultimately does the same thing.
     */
    private const LABEL_PUBLISH = 'Publish';
    private const LABEL_APPROVE = 'Approve';


    /**
     * In one special workflow the Publish button gets renamed to Approve.
     * Its behaviour is exactly the same: only its user-facing label changes.
     *
     * @param bool $globalModerationRequired
     * @param User $viewer
     * @param Model $moment
     * @return string
     */
    public function generate(bool $globalModerationRequired, \Schoolbox\User $viewer, Model $moment): string
    {
        $label = self::LABEL_PUBLISH;

        /* If you're a staff,
         * and Learning Moment Moderation is required,
         * and the Moment was authored by a student,
         * and the Moment is unpublished,
         * then the button label says "Approve"
         *
         * Otherwise it says "Publish"
         */
        if ($viewer->getRole()->isStaff()) {
            if ((new ModerationRequired())->isSatisfiedBy($globalModerationRequired, $moment->getRecipient()->getYear())) {
                if (!$moment->isPublished()) {
                    if ($moment->getCreator()->getRole()->isStudent()) {
                        $label = self::LABEL_APPROVE;
                    }
                }
            }
        }

        return $label;
    }
}
