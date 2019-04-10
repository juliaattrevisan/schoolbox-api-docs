<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread;

use Alaress\Json\Decorator;
use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\Discussion\Comment;
use Schoolbox\Discussion\Thread\Context\IContext;
use Schoolbox\Discussion\Thread\Context\IContextLocator;
use Schoolbox\Json\Decorator\LinkDecorator;
use Scrub;

/**
 * Controller for thread-related API requests.
 */
class Controller extends BaseController
{
    /**
     * @var Comment\Mapper
     */
    private $commentMapper;


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
     * The context object, used for associating a thread to a context.
     *
     * @var IContextLocator
     */
    private $context;


    /**
     * The model decorator factory for threads, used to wrap a thread in a
     * renderer that knows how to create links for its context.
     *
     * @var ModelDecorator\IFactory
     */
    private $decoratorFactory;


    /**
     * The model decorator factory for comments, used to wrap comments in a
     * thread in a renderer that knows how to create links for its context.
     *
     * @var Comment\ModelDecorator\IFactory
     */
    private $commentDecoratorFactory;


    /**
     * Constructor
     *
     * @param Comment\Mapper                  $commentMapper
     * @param Mapper                          $threadMapper
     * @param IContextLocator                 $context
     * @param ModelDecorator\IFactory         $decoratorFactory
     * @param Comment\ModelDecorator\IFactory $commentDecoratorFactory
     */
    public function __construct(
        Comment\Mapper $commentMapper,
        Mapper $threadMapper,
        IContextLocator $context,
        ModelDecorator\IFactory $decoratorFactory,
        Comment\ModelDecorator\IFactory $commentDecoratorFactory
    ) {
        $this->commentMapper           = $commentMapper;
        $this->threadMapper            = $threadMapper;
        $this->context                 = $context;
        $this->decoratorFactory        = $decoratorFactory;
        $this->commentDecoratorFactory = $commentDecoratorFactory;
    }


    /**
     * Get a thread, including its most recent comments.
     *
     * @api   GET /discussion/{contextType}/{contextId}/threads/{id}
     * @level 190
     *
     * @param Model    $thread
     * @param IContext $context
     */
    public function getThread(Model $thread, IContext $context): void
    {
        $this->buildResponse($thread, $context, true);
    }


    /**
     * Create a new thread, and associates it with `$context`.
     *
     * @api   POST /discussion/{contextType}/{contextId}/threads/
     * @level 190
     *
     * @param IContext $context The item with which to associate the thread
     * @throws \Schoolbox\Mapper\Exception
     */
    public function createThread(IContext $context): void
    {
        /** @var \stdClass $data */
        $data   = $this->request->content->get();
        $thread = $this->threadMapper->createModel()
            ->setOpen($data->open);
        $this->threadMapper->save($thread);
        $this->context->associate($thread, $context);
        $this->buildResponse($thread, $context, false);
    }


    /**
     * Get a list of comments in the given thread.
     *
     * @api   GET /discussion/{contextType}/{contextId}/threads/{id}/comments
     * @level 190
     *
     * @param Model    $thread
     * @param IContext $context
     */
    public function getCommentsByThread(Model $thread, IContext $context): void
    {
        $responseData = $this->retrieveCommentList($thread, $context, $this->request->query->get('cursor'));

        // Clean-up every content before returning the result
        foreach ($responseData['data'] as $key => $data) {
            $responseData['data'][$key]['content'] = Scrub::html($data['content']);
        }

        $this->response->content->set(
            $this->isJsonRequest()
                ? $this->buildJsonOutput($responseData)
                : $this->buildUnthemedHtmlOutput(
                    '@discussionComments/list.twig',
                    ['list' => $responseData, 'thread' => $thread]
                )
        );
    }


    /**
     * Comment to a thread.
     *
     * @api   POST /discussion/{contextType}/{contextId}/threads/{id}/comments
     * @level 190
     *
     * @param Model    $thread
     * @param IContext $context
     *
     * @throws \Schoolbox\Mapper\Exception
     */
    public function createCommentInThread(Model $thread, IContext $context): void
    {
        /** @var \stdClass $data */
        $data = $this->request->content->get();

        $comment = $this->commentMapper->createModel()
            ->setThread($thread)
            ->setAuthor($this->getUser())
            ->setContent($data->content);

        $this->commentMapper->save($comment);

        $responseData = $this->commentDecoratorFactory
            ->build($comment, $context)
            ->jsonSerialize($this->retrieveJsonDecorator(), $this->retrieveLinkDecorator());

        // Clean-up content before returning the result
        $responseData['content'] = Scrub::html($responseData['content']);

        $this->response->content->set(
            $this->isJsonRequest()
                ? $this->buildJsonOutput($responseData)
                : $this->buildUnthemedHtmlOutput('@discussionComments/item.twig', ['item' => $responseData])
        );
    }

    /**
     * @api   PUT /discussion/{contextType}/{contextId}/threads/{id}/open
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
     * @api   PUT /discussion/{contextType}/{contextId}/threads/{id}/close
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
     * Retrieve a list of comments as an array for later output.
     *
     * @param Model       $thread
     * @param IContext    $context
     * @param string|null $encodedCursor
     * @return array
     */
    private function retrieveCommentList(Model $thread, IContext $context, ?string $encodedCursor): array
    {
        $cursor   = new Comment\Cursor($this->commentMapper, $thread, Comment\Cursor::ORDER_DESC);
        $comments = $cursor->retrieveResults($encodedCursor);
        return [
            'data'     => $comments
                ->map(function (Comment\Model $comment) use ($context) {
                    return $this->commentDecoratorFactory->build($comment, $context);
                })
                ->jsonSerialize($this->retrieveJsonDecorator(), $this->retrieveLinkDecorator()),
            'metadata' => [
                'count'  => $cursor->retrieveResultCount(),
                'cursor' => [
                    'current' => $encodedCursor ?? $cursor->current($comments),
                    'next'    => $cursor->next($comments),
                ],
            ],
        ];
    }


    /**
     * Builds a response for this thread, putting it into
     * `$this->response->content`.
     *
     * @param Model    $thread
     * @param IContext $context
     * @param bool     $loadChildren
     */
    private function buildResponse(Model $thread, IContext $context, bool $loadChildren): void
    {
        $responseData             = $this->decoratorFactory->build($thread, $context)->jsonSerialize(
            $this->retrieveJsonDecorator(),
            $this->retrieveLinkDecorator()
        );
        $responseData['children'] = $loadChildren
            ? $this->retrieveCommentList($thread, $context, null)
            : [];
        $this->response->content->set(
            $this->isJsonRequest()
                ? $this->buildJsonOutput($responseData)
                : $this->buildUnthemedHtmlOutput('@discussionThreads/item.twig', ['item' => $responseData])
        );
    }
}
