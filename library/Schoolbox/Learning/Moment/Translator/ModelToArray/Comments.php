<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Translator\ModelToArray;

use Alaress\HTMLPurifier\IPurifier;
use Alaress\Json\Decorator\PropertyDecorator;
use Schoolbox\Discussion\Comment;
use Schoolbox\Json\Decorator\LinkDecorator;
use Schoolbox\Learning\Moment\Evidence;
use Schoolbox\Learning\Moment\Evidence\Discussion;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Translator for translating a learning moment evidence model into an array,
 * along with some of its comments and links.
 */
class Comments
{
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
     * The max number of comments to initially load for each evidence's
     * discussion thread. (More comments will be available in the cursor.)
     */
    private int $commentLimit;


    /**
     * HTMLPurifier instance, or an adapter thereof.
     *
     * Required for purifying the content of each comment.
     */
    private IPurifier $purifier;


    /**
     * Constructor
     *
     * @param Comment\Mapper    $commentMapper
     * @param LinkDecorator     $linkDecorator
     * @param PropertyDecorator $propertyDecorator
     * @param int               $commentLimit
     * @param IPurifier         $purifier
     */
    public function __construct(
        Comment\Mapper $commentMapper,
        LinkDecorator $linkDecorator,
        PropertyDecorator $propertyDecorator,
        int $commentLimit,
        IPurifier $purifier
    ) {
        $this->commentMapper     = $commentMapper;
        $this->linkDecorator     = $linkDecorator;
        $this->propertyDecorator = $propertyDecorator;
        $this->commentLimit      = $commentLimit;
        $this->purifier          = $purifier;
    }


    /**
     * @param \Schoolbox\Learning\Moment\Model $evidence
     * @return array
     */
    public function __invoke(\Schoolbox\Learning\Moment\Model $evidence): array
    {
        $translation = [];

        // Does this evidence have a discussion thread?
        // If so: load and translate it, and inject it into the evidence
        // If not: create a link to create a thread.
        if ($evidence->getDiscussionThread()) {
            $translation['discussionThread'] = $this->loadAndTranslateCommentsInDiscussionThread($evidence);
        }

        return $translation;
    }


    /**
     * Helper method for loading the comments that are a part of the evidence's
     * discussion thread, and then translating them into the discussion thread's
     * JSON schema structure.
     *
     * @param \Schoolbox\Learning\Moment\Model $evidence
     * @return array
     * @throws MapperException if a query error occurs when retrieving or
     *                         counting comments
     */
    private function loadAndTranslateCommentsInDiscussionThread(\Schoolbox\Learning\Moment\Model $evidence): array
    {
        $commentCursor  = new Comment\Cursor(
            $this->commentMapper,
            $evidence->getDiscussionThread(),
            Comment\Cursor::ORDER_DESC,
            $this->commentLimit,
        );
        $results        = $commentCursor->retrieveResults(null);
        $encodedResults = $results
            ->map(static function (Comment\Model $comment) use ($evidence) {
                return new \Schoolbox\Learning\Moment\Discussion\Comment\ModelDecorator\Decorator($comment, $evidence);
            });
        $encodedResults = $encodedResults
            ->jsonSerialize($this->propertyDecorator, $this->linkDecorator);


        // Serialize the discussion thread using its decorator
        $result = (new \Schoolbox\Learning\Moment\Discussion\Thread\ModelDecorator\Decorator($evidence->getDiscussionThread(), $evidence))
            ->jsonSerialize($this->propertyDecorator, $this->linkDecorator);


        // Scrub each result's comment
        foreach ($encodedResults as &$encodedResult) {
            $encodedResult['content'] = $this->purifier->purify($encodedResult['content']);
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
