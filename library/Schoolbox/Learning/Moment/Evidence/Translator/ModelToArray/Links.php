<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence\Translator\ModelToArray;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Router;
use Schoolbox\Learning\Moment\Evidence;
use Schoolbox\User;

/**
 * Translator for loading links into the array.
 */
class Links
{
    /**
     * The router object, as required to generate a link to content.
     */
    private Router $router;


    /**
     * The authorizer object, as required to determine whether a user is
     * authorized to access a link.
     */
    private Authorizer $authorizer;


    /**
     * The user who is being authorized to access a link.
     */
    private User $currentUser;


    /**
     * Constructor
     *
     * @param Router     $router
     * @param Authorizer $authorizer
     * @param User       $currentUser
     */
    public function __construct(Router $router, Authorizer $authorizer, User $currentUser)
    {
        $this->router      = $router;
        $this->authorizer  = $authorizer;
        $this->currentUser = $currentUser;
    }


    /**
     * @param Evidence\Model $evidence
     * @return array
     */
    public function __invoke(Evidence\Model $evidence): array
    {
        $translation = [
            '_links' => [],
        ];

        // Does this evidence have a discussion thread?
        // If so: load and translate it, and inject it into the evidence
        // If not: create a link to create a thread.
        if (!$evidence->getDiscussionThread()) {
            // Output the create thread link.
            // TBD: is it output as a simple link, or a href/auth object?
            // The old Aura link outputs the object, newer links are output
            // as simple values (and are not output at all if permission denied)
            $translation['_links']['createThread'] = $this->getRouteIfAuthorized(
                'evidenceDiscussionThreads.createThread',
                [
                    'context'   => $evidence,
                    'contextId' => $evidence->getId(),
                ]
            );
        }


        $translation['_links']['detail'] = $this->getRouteIfAuthorized(
            'learningMomentEvidence.getEvidenceDetail',
            [
                'evidenceId' => $evidence->getId(),
                'userId'     => $evidence->getRecipient()->getId(),
            ]
        );

        /* TODO Figure out what the modify perms will be
         *  I added this here, it was in there SA-11246 screenshot.
         */
        $translation['_links']['modify'] = null;

        $translation['_links']['delete'] = $this->getRouteIfAuthorized(
            'learningMomentEvidence.deleteEvidence',
            [
                'evidenceId' => $evidence->getId(),
                'creatorId'  => $evidence->getCreator()->getId(),
            ]
        );


        return $translation;
    }


    /**
     * Helper function for outputting a route, only if it is authorized.
     *
     * @param string $route
     * @param array  $params
     * @return string|null
     * @noinspection PhpDocMissingThrowsInspection Should never happen as `$route` should never not be defined
     */
    private function getRouteIfAuthorized(string $route, array $params): ?string
    {
        if ($this->authorizer->isPathAuthorizedForUser($route, $params, $this->currentUser)) {
            /** @noinspection PhpUnhandledExceptionInspection */
            return $this->router->generate($route, $params);
        }
        return null;
    }
}
