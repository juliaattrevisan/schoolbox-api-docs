<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence;

use Alaress\Web\Controller\Base;
use ICollection;
use Schoolbox\User;

/**
 * Controller for learning moments.
 */
class Controller extends Base
{
    /**
     * Render a create form for a learning moment.
     *
     * @api   GET /learning/evidence/user/{userIds}/create
     * @since 20.2
     *
     * @param ICollection $users
     */
    public function getCreateForm(ICollection $users): void
    {
        $this->response->content->set(
            $this->buildHtmlOutput(
                '@learningMomentEvidence/form.twig',
                [
                    'users' => $users,
                ]
            )
        );
    }


    /**
     * Retrieve a JSON representation of a single learning moment.
     *
     * @api   GET /learning/evidence/{id}
     * @since 20.2
     */
    public function getMoment(): void
    {
    }


    /**
     * Retrieve a user's learning moments.
     *
     * @api   GET /learning/evidence/user/{userId}
     * @since 20.2
     */
    public function getMomentsForUser(User $user): void
    {
    }


    /**
     * Post a JSON representation of one or more learning moments.
     *
     * @api   POST /learning/evidence/
     * @since 20.2
     */
    public function postMoments(): void
    {
    }


    /**
     * Post a JSON representation of a learning moment for a user.
     *
     * @api   POST /learning/evidence/user/{userId}
     * @since 20.2
     */
    public function postMomentForUser(User $user): void
    {
    }


    /**
     * Delete a single moment.
     *
     * @api   DELETE /learning/evidence/{id}
     * @since 20.2
     */
    public function deleteMoment(): void
    {
    }
}
