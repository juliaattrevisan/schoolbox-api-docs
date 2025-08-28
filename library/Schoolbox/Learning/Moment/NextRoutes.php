<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Moment;

use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Moment\Moderation\ModerationRequired;
use Schoolbox\User;

class NextRoutes
{
    /**
     * Return a _links array which contains urls for things you could potentially do
     * next, for a given $moment, for the Cohort Overview.
     *
     * This function now provides _links for students as well as other user roles.
     * It checks the current user's roletype if student, checks for moderation requirements.
     * The _links node is always included in the response of a PUT endpoint reflecting the users available actions.
     *
     * You have to actually obtain the links via the route generator in controllers
     * because it depends on a pre-existing logic that we shouldn't replicate here.
     * There doesn't seem to be a convenient way to compartmentalise getRouteIfAuthorized()
     *
     * @param Model $moment
     * @param User $user The user currently logged in
     * @param bool $globalModerationRequired
     * @return array{draft:true|null,submit:true|null,publish:true|null,reject:true|null}
     */
    public function generate(\Schoolbox\Learning\Moment\Model $moment, User $user, bool $globalModerationRequired): array
    {
        /* Each roletype has a somewhat-unique workflow of states into which a learning moment can transition.
         * For example:
         *
         *     Students are beholden to the Learning Moment Moderation requirement on the roles;
         *     Parents skip the moderation process entirely,
         *     Staff have the ability to reject.
         */
        $draft = null;
        $submit = null;
        $publish = null;
        $reject = null;

        /* Start with staff
         */
        if ($user->getRole()->isStaff()) {
            if ($moment->isDraft()) {
                /* Draft is a possibility for repeated edits
                 */
                $draft = true;

                /* Staff can always publish on behalf of a student in case they forget,
                 * because they're like 2 years old and don't hit the publish button themselves
                 */
                $publish = true;

                /* If moderation was needed, rejected state becomes a possibility
                 */
                if ((new ModerationRequired())->isSatisfiedBy($globalModerationRequired, $moment->getRecipient()->getYear())) {
                    $reject = true;
                }
            } elseif ($moment->isSubmitted()) {
                /* Draft is a possibility for repeated edits
                 */
                $draft = true;

                /* submitted can move to published
                 */
                $publish = true;

                /* If moderation was needed, rejected state becomes a possibility
                 */
                if ((new ModerationRequired())->isSatisfiedBy($globalModerationRequired, $moment->getRecipient()->getYear())) {
                    $reject = true;
                }
            } elseif ($moment->isRejected()) {
                /* You can't continually/repeatedly reject an already-rejected learning moment
                 * ...but you can change your mind and send it into published state
                 *
                 * Anything else is prohibited and you are forced back to the start of the workflow
                 * in draft state
                 */
                $publish = true;
            } elseif ($moment->isPublished()) {
                /* Once published, always published, even if you change the content as a staff
                 */
                $publish = true;
            }
        } elseif ($user->getRole()->isParent()) {
            /* When parents author their own content, they skip the whole moderation workflow
             * but they can't progress anyone else's learning moments to another state.
             */
            if ($moment->getCreator()->getId() === $user->getId()) {
                $publish = true;
            }
        } elseif ($user->getRole()->isStudent()) {
            if ($moment->isDraft()) {
                /* Draft is a possibility for repeated edits
                 */
                $draft = true;

                if ((new ModerationRequired())->isSatisfiedBy($globalModerationRequired, $moment->getRecipient()->getYear())) {
                    /* If moderation was needed, submitted state becomes a possibility
                     */
                    $submit = true;
                } else {
                    /* Otherwise students can immediately self-publish their own content
                     */
                    $publish = true;
                }
            } elseif ($moment->isSubmitted()) {
                if ((new ModerationRequired())->isSatisfiedBy($globalModerationRequired, $moment->getRecipient()->getYear())) {
                    /* noop: you need to wait for a staff to progress your learning moment.
                     * Students can't self-publish when moderation is required.
                     */
                } else {
                    /* This occurs when a learning moment was in a submitted state and moderation was required,
                     * but then moderation requirement was removed: your content is now in this limbo area where
                     * you need to progress it further by yourself, as if you had authored it without moderation
                     */
                    $publish = true;
                }
            } elseif ($moment->isRejected()) {
                /* You can't continually/repeatedly reject an already-rejected learning moment
                 * ...but you can change your mind and send it into published state
                 *
                 * Anything else is prohibited and you are forced back to the start of the workflow
                 * in draft state
                 */
                $publish = true;
            }
        }

        return [
            'draft' => $draft,
            'submit' => $submit,
            'publish' => $publish,
            'reject' => $reject,
        ];
    }
}
