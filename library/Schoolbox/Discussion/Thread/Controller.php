<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread;

use Alaress\Json\Decorator;
use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\Discussion\Post;
use Schoolbox\Forum\Thread;
use Schoolbox\Json\Decorator\LinkDecorator;

/**
 * Controller for thread-related API requests.
 */
class Controller extends BaseController
{
    /**
     * @var Post\Mapper
     */
    private $postMapper;


    /**
     * @var Decorator\PropertyDecorator
     */
    private $jsonDecorator;


    /**
     * @var LinkDecorator
     */
    private $linkDecorator;


    /**
     * @var Mapper
     */
    private $threadMapper;


    /**
     * Constructor
     *
     * @param Post\Mapper $postMapper
     * @param Mapper      $threadMapper
     */
    public function __construct(Post\Mapper $postMapper, Mapper $threadMapper)
    {
        $this->postMapper   = $postMapper;
        $this->threadMapper = $threadMapper;
    }


    /**
     * Get a list of posts in the given thread.
     *
     * @api   GET /threads/{id}/posts
     * @level 190
     *
     * @param Model $thread
     */
    public function getPostsByThread(Model $thread): void
    {
        $cursor        = new Post\Cursor($this->postMapper, $thread, Post\Cursor::ORDER_DESC);
        $encodedCursor = $this->request->query->get('cursor');
        $posts         = $cursor->retrieveResults($encodedCursor);
        $responseData  = [
            'data'     => $posts->jsonSerialize($this->retrieveJsonDecorator(), $this->retrieveLinkDecorator()),
            'metadata' => [
                'cursor' => [
                    'current' => $encodedCursor ?? $cursor->retrieveCurrentCursor($posts),
                    'next'    => $cursor->retrieveNextCursor($posts),
                ]
            ]
        ];

        $this->response->content->set(
            $this->isJsonRequest()
                ? $this->buildJsonOutput($responseData)
                : $this->buildUnthemedHtmlOutput('@discussionPosts/list.twig', ['list' => $responseData])
        );
    }


    /**
     * Post to a thread.
     *
     * @api   POST /threads/{id}/posts
     * @level 190
     *
     * @param Model $thread
     */
    public function createPostInThread(Model $thread): void
    {
        /** @var \stdClass $data */
        $data = $this->request->content->get();
        $post = $this->postMapper->createModel()
            ->setThread($thread)
            ->setAuthor($this->getUser())
            ->setContent($data->content);

        $this->postMapper->save($post);

        $responseData = $post->jsonSerialize($this->retrieveJsonDecorator(), $this->retrieveLinkDecorator());
        $this->response->content->set(
            $this->isJsonRequest()
                ? $this->buildJsonOutput($responseData)
                : $this->buildUnthemedHtmlOutput('@discussionPosts/item.twig', ['item' => $responseData])
        );
    }

    /**
     * @api   PUT /threads/{id}/enable
     * @param Model $thread
     *
     * @throws \Schoolbox\Mapper\Exception
     */
    public function enableDisableThread(Model $thread): void
    {
        $data = $this->request->content->get();
        $thread->setOpen((bool) $data->threadEnabled);

        $this->threadMapper->save($thread);

        $this->getResponse()->content->set($this->buildJsonOutput('success'));
    }


    /**
     * Retrieve this class' link decorator.
     *
     * TODO inject this
     *
     * @return LinkDecorator
     */
    private function retrieveLinkDecorator(): LinkDecorator
    {
        if (!$this->linkDecorator) {
            $this->linkDecorator = new LinkDecorator($this->router, $this->authorizer, $this->getUser());
        }
        return $this->linkDecorator;
    }


    /**
     * Retrieve this class' JSON decorator.
     *
     * TODO inject this
     *
     * @return Decorator\PropertyDecorator
     */
    private function retrieveJsonDecorator(): Decorator\PropertyDecorator
    {
        if (!$this->jsonDecorator) {
            $this->jsonDecorator = new Decorator\PropertyDecorator(null, $this->retrieveLinkDecorator());
        }
        return $this->jsonDecorator;
    }
}
