<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Moderation;

use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Discussion\Comment;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Moment\Mapper;
use Schoolbox\Learning\Moment\Model;
use Schoolbox\Learning\Moment\NextRoutes;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Exception;
use Schoolbox\Resource\Storage;
use Schoolbox\TokenReplacer;
use Schoolbox\User;

class Controller extends Base
{
    /**
     * Constructor
     *
     * @param Mapper $mapper
     * @param Comment\Mapper $commentMapper
     * @param \Schoolbox\Discussion\Thread\Mapper $discussionThreadMapper
     */
    public function __construct(
        private \Schoolbox\Learning\Moment\Mapper $mapper,
        private Comment\Mapper $commentMapper,
        private \Schoolbox\Discussion\Thread\Mapper $discussionThreadMapper,
    ) {
    }


    /**
     * Persists any staff-made changes to a Moment and sets it in the published state;
     * also provides the option to delete the comment thread if one existed.
     *
     * @api   PUT /learning/evidence/{id}/publish
     * @param Model $evidence
     * @return array
     * @throws MapperException
     * @throws Exception
     * @throws RouteNotFound
     */
    public function putPublish(Model $evidence): array
    {
        $requestBody       = $this->request->getParsedBody();
        $requestTranslator = new \Schoolbox\Learning\Moment\Translator\ArrayToModel\MutableFieldsOnly(new CollectorFactory());
        $requestTranslator->setCurriculumTaggingEnabled($this->curriculumTaggingEnabled());
        $tokenReplacer     = new TokenReplacer\Student(new TokenReplacer\Simple());

        /* This is a pretty convoluted way of doing things just to decide on a subset of nodes
         * Anyway, this $baseModel contains any curriculum nodes and tags you might have altered
         */
        $baseModel = (new \Schoolbox\Learning\Moment\Translator\ArrayToModel\MutableFieldsOnly(new CollectorFactory()))($requestBody)
            ->setCreator($this->getUser());

        /* Update anything that might have changed
         */
        $evidence = $requestTranslator($requestBody, $evidence)
            ->setBody($tokenReplacer->replace($baseModel->getBody() ?? $evidence->getBody(), $evidence->getRecipient()))
            ->setTags($baseModel->getTags() ?? $evidence->getTags())
            ->setCurriculumNodes($baseModel->getCurriculumNodes() ?? $evidence->getCurriculumNodes());

        /* Secret-squirrel access to PUT payload...
         */
        $payload = $this->request->getParsedBody();

        /* Soft-delete all the comments associated with this learning moment
         */
        if ($payload['deleteComments'] ?? false) {
            /* Learning moments might not even have a discussion thread yet
             */
            if ($evidence->getDiscussionThread()) {
                $this->commentMapper->deleteByColumn('discussion_thread_id', $evidence->getDiscussionThread()->getId());
            }
        }

        /* Publish it to the world
         */
        $evidence->publish($this->getUser());

        /* Save it
         */
        $this->mapper->save($evidence);

        /* TODO Post a notification
         */

        $responseTranslator = new \Schoolbox\Learning\Moment\Translator\ModelToArray\Base(
            new User\Translator\Author\ModelToArray(),
            new Storage\Translator\ModelToArray(new PrefixGenerator($this->getConfigLoader())),
            new ScrubAdapter(),
        );

        $translated = $responseTranslator($evidence);

        /* A shortcut to urls for changing states in this learning moment
         */
        // $translated['_links'] = $this->getNextRoutes($evidence);

        return $translated;
    }


    /**
     * Bulk publish a set of $moments
     *
     * @return void
     * @throws Exception
     * @throws MapperException
     * @throws RouteNotFound
     */
    public function putBulkPublish(): void
    {
        $stack = [];

        /* Find all the learning moments to bulk publish
         */
        $requestBody = $this->request->getParsedBody();

        $moments = $this->mapper->findByColumn('id', $requestBody['momentIds']);

        /* Process each moment into the next state individually, collecting a $stack
         * of translated moments in their new state
         */
        foreach ($moments as $moment) {
            $stack[$moment->getId()] = $this->putPublish($moment);
        }

        $this->response = $this->responseAsJson($stack);
    }


    /**
     * Reject a Moment, along with a reason which goes int the Moment's comment thread.
     *
     * @param Model $evidence
     * @return void
     * @throws Exception
     * @throws MapperException
     * @throws RouteNotFound
     */
    public function putReject(Model $evidence): void
    {
        $this->reject($evidence);

        /* Redirect back to a place
         */
        $encodedReturnUrl = $this->requestPost->get(Redirect::RETURN_URL);

        if (!empty($encodedReturnUrl)) {
            $returnUrl = Redirect::decode($encodedReturnUrl);
        } else {
            $returnUrl = $this->routeGenerator->generate('searchUser.getProfile', [
                'userId' => $evidence->getRecipient()->getId()
            ]);
        }

        /* TODO Post a notification
         */

        $this->response = $this->redirectAfterPost($returnUrl);
    }


    /**
     * Bulk reject a set of $moments
     *
     * @return void
     * @throws Exception
     * @throws MapperException
     * @throws RouteNotFound
     */
    public function putBulkReject(): void
    {
        $stack = [];

        /* Find all the learning moments to bulk reject
         */
        $requestBody = $this->request->getParsedBody();

        $moments = $this->mapper->findByColumn('id', $requestBody['momentIds']);

        /* Process each moment into the next state individually, collecting a $stack
         * of translated moments in their new state
         */
        foreach ($moments as $moment) {
            $stack[$moment->getId()] = $this->reject($moment);
        }

        $this->response = $this->responseAsJson($stack);
    }


    /**
     * Put an $evidence into Rejected state and spawn any rejection reasons as comments in a
     * discussion thread related to $evidence, creating one if it doesn't already exist.
     *
     * @param Model $evidence
     * @return array
     * @throws Exception
     * @throws MapperException
     * @throws RouteNotFound
     */
    private function reject(Model $evidence): array
    {
        $requestBody       = $this->request->getParsedBody();

        $requestTranslator = new \Schoolbox\Learning\Moment\Translator\ArrayToModel\MutableFieldsOnly(new CollectorFactory());
        $requestTranslator->setCurriculumTaggingEnabled($this->curriculumTaggingEnabled());
        $tokenReplacer     = new TokenReplacer\Student(new TokenReplacer\Simple());

        $evidence          = $requestTranslator($requestBody, $evidence)
            ->setBody($tokenReplacer->replace($evidence->getBody(), $evidence->getRecipient()));

        /* If a discussion thread never existed, spawn one
         */
        $discussionThread = $evidence->getDiscussionThread();

        if (null === $discussionThread) {
            $discussionThread = $this->discussionThreadMapper->createModel()
                ->setOpen(true);

            $this->discussionThreadMapper->save($discussionThread);

            $evidence->setDiscussionThread($discussionThread);
        }

        /* Update the status of $evidence to Rejected
         */
        $evidence->reject($this->getUser());

        /* Save it
         */
        $this->mapper->save($evidence);

        /* Spawn a comment
         * Save it to $evidence's discussion thread somehow
         */
        $comment = $this->commentMapper->createModel()
            ->setContent($requestBody['rejectionReason'] ?? '')
            ->setAuthor($this->getUser())
            ->setThread($discussionThread);

        $this->commentMapper->save($comment);

        $responseTranslator = new \Schoolbox\Learning\Moment\Translator\ModelToArray\Base(
            new User\Translator\Author\ModelToArray(),
            new Storage\Translator\ModelToArray(new PrefixGenerator($this->getConfigLoader())),
            new ScrubAdapter(),
        );

        $translated = $responseTranslator($evidence);

        /* A shortcut to urls for changing states in this learning moment
         */
        // $translated['_links'] = $this->getNextRoutes($evidence);

        return $translated;
    }


    /**
     * Checks whether curriculum tagging is enabled
     */
    private function curriculumTaggingEnabled(): bool
    {
        return $this->getUser()->getRole()->isStaff() && $this->getConfigLoader()->get('curriculum_tagging_enabled');
    }


    /**
     * For a particular learning moment, decide what actions could happen next.
     *
     * @param Model $moment
     * @return array
     * @throws RouteNotFound
     */
    private function getNextRoutes(Model $moment): array
    {
        $links = (new NextRoutes())->generate($moment, $this->getUser(), $this->getConfigLoader()->get(ConfigMapper::NAME_LEARNING_MOMENTS_MODERATION_ENABLED));

        /* Only get the routes you need
         */
        if ($links['submit']) {
            $links['submit'] = $this->getRouteIfAuthorized('learningMomentEvidence.putSubmit', [
                'evidenceId' => $moment->getId(),
                'creatorId' => $moment->getCreator()->getId(),
                'userId' => $moment->getRecipient()->getId(),
            ]);
        }

        if ($links['publish']) {
            $links['publish'] = $this->getRouteIfAuthorized('learningMomentEvidence.putPublish', [
                'evidenceId' => $moment->getId(),
                'creatorId' => $moment->getCreator()->getId(),
            ]);
        }

        if ($links['reject']) {
            $links['reject'] = $this->getRouteIfAuthorized('learningMomentModeration.putReject', [
                'evidenceId' => $moment->getId(),
            ]);
        }

        /* non-state dependent actions */
        $links['modifyForm'] = $this->getRouteIfAuthorized('learningMomentEvidence.getModifyForm', [
            'evidenceId' => $moment->getId(),
            'creatorId'  => $moment->getCreator()->getId(),
            'userId'     => $moment->getRecipient()->getId(),
        ]);

        $links['delete'] = $this->getRouteIfAuthorized('learningMomentEvidence.deleteEvidence', [
            'evidenceId' => $moment->getId(),
            'creatorId'  => $moment->getCreator()->getId(),
        ]);

        return $links;
    }
}
