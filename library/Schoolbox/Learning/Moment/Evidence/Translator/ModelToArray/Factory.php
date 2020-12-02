<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);


namespace Schoolbox\Learning\Moment\Evidence\Translator\ModelToArray;


use Alaress\Authorizer\Authorizer;
use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Alaress\Json\Decorator\PropertyDecorator;
use Aura\Router\Router;
use Schoolbox\API\Translator\ModelToArray\Composite;
use Schoolbox\Discussion\Comment\Mapper as CommentMapper;
use Schoolbox\Json\Decorator\LinkDecorator;
use Schoolbox\Resource\Storage;
use Schoolbox\User;


/**
 * Factory for building a standard translator
 */
class Factory
{
    /**
     * The router used to build links to evidence.
     */
    private Router $router;


    /**
     * The router used to determine links to evidence.
     *
     * Required for determining permissions to links created by the router.
     */
    private Authorizer $authorizer;


    /**
     * The user to build a translator for.
     *
     * Required for determining permissions to links created by the router.
     */
    private User $user;


    /**
     * Mapper for retrieving comments for evidence found by that translators
     * built by this class.
     */
    private CommentMapper $commentMapper;


    /**
     * Constructor
     *
     * @param Router $router
     * @param Authorizer $authorizer
     * @param User $user
     * @param CommentMapper $commentMapper
     */
    public function __construct(
        Router $router,
        Authorizer $authorizer,
        User $user,
        CommentMapper $commentMapper
    ) {
        $this->router = $router;
        $this->authorizer = $authorizer;
        $this->user = $user;
        $this->commentMapper = $commentMapper;
    }


    /**
     * Builds a translator object which includes comments.
     *
     * @param int $commentLimit
     * @return Composite
     */
    public function buildWithComments(int $commentLimit): Composite
    {
        $scrubWrapper   = new ScrubAdapter();
        $baseTranslator = new Base(
            new User\Translator\Author\ModelToArray(),
            new Storage\Translator\ModelToArray(),
            $scrubWrapper,
        );

        $linkTranslator = new Links(
            $this->router,
            $this->authorizer,
            $this->user,
        );

        $linkDecorator     = new LinkDecorator($this->router, $this->authorizer, $this->user);
        $propertyDecorator = new PropertyDecorator(null, $linkDecorator);
        $commentTranslator = new Comments(
            $this->commentMapper,
            $linkDecorator,
            $propertyDecorator,
            $commentLimit,
            $scrubWrapper,
        );

        return new Composite(
            $baseTranslator,
            $linkTranslator,
            $commentTranslator,
        );
    }
}
