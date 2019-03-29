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
use Scrub;

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
     * Get a thread, including its most recent comments.
     *
     * @api   GET /threads/{id}
     * @level 190
     *
     * @param Model $thread
     */
    public function getThread(Model $thread): void
    {
        $responseData             = $thread->jsonSerialize(
            $this->retrieveJsonDecorator(),
            $this->retrieveLinkDecorator()
        );
        $responseData['children'] = $this->retrievePostList($thread, null);
        $this->response->content->set(
            $this->isJsonRequest()
                ? $this->buildJsonOutput($responseData)
                : $this->buildUnthemedHtmlOutput('@discussionThreads/item.twig', ['item' => $responseData])
        );
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
        $responseData = $this->retrievePostList($thread, $this->request->query->get('cursor'));

        // Clean-up every content before returning the result
        foreach ($responseData['data'] as $key => $data) {
            $responseData['data'][$key]['content'] = Scrub::html($data['content']);
        }

        $this->response->content->set(
            $this->isJsonRequest()
                ? $this->buildJsonOutput($responseData)
                : $this->buildUnthemedHtmlOutput(
                    '@discussionPosts/list.twig',
                    ['list' => $responseData, 'thread' => $thread]
                )
        );
    }


    /**
     * Post to a thread.
     *
     * @api   POST /threads/{id}/posts
     * @level 190
     *
     * @param Model $thread
     *
     * @throws \Schoolbox\Mapper\Exception
     */
    public function createPostInThread(Model $thread): void
    {
        /** @var \stdClass $data */
        $data = $this->request->content->get();

        $post = $this->postMapper->createModel()
            ->setThread($thread)
            ->setAuthor($this->getUser())
            ->setContent($data->params->content);

        $this->postMapper->save($post);

        $responseData = $post->jsonSerialize($this->retrieveJsonDecorator(), $this->retrieveLinkDecorator());

        // Clean-up content before returning the result
        $responseData['content'] = Scrub::html($responseData['content']);

        $this->response->content->set(
            $this->isJsonRequest()
                ? $this->buildJsonOutput($responseData)
                : $this->buildUnthemedHtmlOutput('@discussionPosts/item.twig', ['item' => $responseData])
        );
    }

    /**
     * @api   PUT /threads/{id}/open
     * @param Model $thread
     *
     * @throws \Aura\Web\Exception\InvalidStatusCode
     * @throws \Schoolbox\Mapper\Exception
     */
    public function openThread(Model $thread): void
    {
        $this->setThreadStatus($thread, true);
    }

    /**
     * @api   PUT /threads/{id}/close
     * @param Model $thread
     *
     * @throws \Aura\Web\Exception\InvalidStatusCode
     * @throws \Schoolbox\Mapper\Exception
     */
    public function closeThread(Model $thread): void
    {
        $this->setThreadStatus($thread, false);
    }

    /**
     * @param Model $thread
     * @param bool  $value
     *
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Aura\Web\Exception\InvalidStatusCode
     */
    private function setThreadStatus(Model $thread, bool $value): void
    {
        $thread->setOpen($value);
        $this->threadMapper->save($thread);

        $this->response->status->setCode(204);
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


    /**
     * Retrieve a list of posts as an array for later output.
     *
     * @param Model       $thread
     * @param string|null $encodedCursor
     * @return array
     */
    private function retrievePostList(Model $thread, ?string $encodedCursor): array
    {
        $cursor = new Post\Cursor($this->postMapper, $thread, Post\Cursor::ORDER_DESC);
        $posts  = $cursor->retrieveResults($encodedCursor);
        return [
            'data'     => $posts->jsonSerialize($this->retrieveJsonDecorator(), $this->retrieveLinkDecorator()),
            'metadata' => [
                'count'  => $cursor->retrieveResultCount(),
                'cursor' => [
                    'current' => $encodedCursor ?? $cursor->retrieveCurrentCursor($posts),
                    'next'    => $cursor->retrieveNextCursor($posts),
                ],
            ],
        ];
    }
}
