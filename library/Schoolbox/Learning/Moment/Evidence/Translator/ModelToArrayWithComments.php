<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Json\Decorator\PropertyDecorator;
use Aura\Router\Router;
use Schoolbox\Discussion\Comment;
use Schoolbox\Json\Decorator\LinkDecorator;
use Schoolbox\Learning\Moment\Evidence;
use Schoolbox\Learning\Moment\Evidence\Discussion;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Scrub;

/**
 * Translator for translating a learning moment evidence model into an array,
 * along with some of its comments and links.
 */
class ModelToArrayWithComments
{
    /**
     * The base model-to-array translator, as used to translate most of the
     * evidence's fields.
     */
    private ModelToArray $baseTranslator;


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
     * The source of comments for an evidence's discussion thread.
     */
    private Comment\Mapper $commentMapper;


    /**
     * A decorator passed to the loaded discussion thread's `jsonSerialize`
     * method: used to create links.
     */
    private LinkDecorator $linkDecorator;


    /**
     * A decorator passed to the loaded discussion thread's `jsonSerialize`
     * method: used to translate properties to array values.
     */
    private PropertyDecorator $propertyDecorator;


    /**
     * Constructor
     *
     * @param ModelToArray      $baseTranslator
     * @param Router            $router
     * @param Authorizer        $authorizer
     * @param User              $currentUser
     * @param Comment\Mapper    $commentMapper
     * @param LinkDecorator     $linkDecorator
     * @param PropertyDecorator $propertyDecorator
     */
    public function __construct(
        ModelToArray $baseTranslator,
        Router $router,
        Authorizer $authorizer,
        User $currentUser,
        Comment\Mapper $commentMapper,
        LinkDecorator $linkDecorator,
        PropertyDecorator $propertyDecorator
    ) {
        $this->baseTranslator    = $baseTranslator;
        $this->router            = $router;
        $this->authorizer        = $authorizer;
        $this->currentUser       = $currentUser;
        $this->commentMapper     = $commentMapper;
        $this->linkDecorator     = $linkDecorator;
        $this->propertyDecorator = $propertyDecorator;
    }


    /**
     * @param Evidence\Model $evidence
     * @return array
     */
    public function __invoke(Evidence\Model $evidence): array
    {
        // Basic translation with the model-to-array translator
        $translation = $this->baseTranslator->__invoke($evidence);


        // TODO remove once the base translator can scrub
        $translation['body']   = Scrub::html($translation['body']);
        $translation['_links'] = [];


        // Does this evidence have a discussion thread?
        // If so: load and translate it, and inject it into the evidence
        // If not: create a link to create a thread.
        if ($evidence->getDiscussionThread()) {
            $translation['discussionThread'] = $this->loadAndTranslateCommentsInDiscussionThread($evidence);
        } else {
            // Output the create thread link.
            // TBD: is it output as a simple link, or a href/auth object?
            // The old Aura link outputs the object, newer links are output
            // as simple values (and are not output at all if permission denied)
            $routeParams       = [
                'context'   => $evidence,
                'contextId' => $evidence->getId(),
            ];
            $createThreadRoute = 'evidenceDiscussionThreads.createThread';
            if ($this->authorizer->authorizePathForUser($createThreadRoute, $routeParams, $this->currentUser)) {
                $translation['_links']['createThread'] = $this->router->generate($createThreadRoute, $routeParams);
            }
        }

        return $translation;
    }


    /**
     * Helper method for loading the comments that are a part of the evidence's
     * discussion thread, and then translating them into the discussion thread's
     * JSON schema structure.
     *
     * @param Evidence\Model $evidence
     * @return array
     * @throws MapperException if a query error occurs when retrieving or
     *                         counting comments
     */
    private function loadAndTranslateCommentsInDiscussionThread(Evidence\Model $evidence): array
    {
        // TODO this should fetch less comments: perhaps only two?
        $commentCursor  = new Comment\Cursor(
            $this->commentMapper,
            $evidence->getDiscussionThread(),
            Comment\Cursor::ORDER_DESC
        );
        $results        = $commentCursor->retrieveResults(null);
        $encodedResults = $results
            ->map(static function (Comment\Model $comment) use ($evidence) {
                return new Discussion\Comment\ModelDecorator\Decorator($comment, $evidence);
            });
        $encodedResults = $encodedResults
            ->jsonSerialize($this->propertyDecorator, $this->linkDecorator);


        // Serialize the discussion thread using its decorator
        $result = (new Discussion\Thread\ModelDecorator\Decorator($evidence->getDiscussionThread(), $evidence))
            ->jsonSerialize($this->propertyDecorator, $this->linkDecorator);


        // Scrub each result's comment
        foreach ($encodedResults as &$encodedResult) {
            $encodedResult['content'] = Scrub::html($encodedResult['content']);
        }
        unset($encodedResult);


        // Serialize the thread's comments as well
        $result['children'] = [
            'data'     => $encodedResults,
            'metadata' => [
                'count'  => $commentCursor->retrieveResultCount(),
                'cursor' => [
                    'current' => $commentCursor->current($results),
                    'next'    => $commentCursor->next($results),
                ],
            ],
        ];

        return $result;
    }
}
