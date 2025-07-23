<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence\Moderation;

use Alaress\Web\Controller\Base;
use Schoolbox\Discussion\Comment;
use Schoolbox\Learning\Moment\Evidence\Model;
use Schoolbox\Learning\Moment\Evidence\Translator;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Exception;
use Schoolbox\TokenReplacer;

class Controller extends Base
{
    /**
     * Constructor
     *
     * @param \Schoolbox\Learning\Moment\Evidence\Mapper $mapper
     * @param Comment\Mapper $commentMapper
     */
    public function __construct(
        private \Schoolbox\Learning\Moment\Evidence\Mapper $mapper,
        private Comment\Mapper $commentMapper
    ) {
    }


    /**
     * Persists any staff-made changes to a Moment and sets it in the published state
     *
     * @api   PUT /learning/evidence/{id}/publish
     * @param Model $evidence
     * @return void
     * @throws MapperException
     * @throws Exception
     */
    public function putPublish(Model $evidence): void
    {
        $requestBody       = $this->request->getParsedBody();
        $requestTranslator = new Translator\ArrayToModel\MutableFieldsOnly(new CollectorFactory());
        $requestTranslator->setCurriculumTaggingEnabled($this->curriculumTaggingEnabled());
        $tokenReplacer     = new TokenReplacer\Student(new TokenReplacer\Simple());

        /* This is a pretty convoluted way of doing things just to decide on a subset of nodes
         * Anyway, this $baseModel contains any curriculum nodes and tags you might have altered
         */
        $baseModel = (new Translator\ArrayToModel\MutableFieldsOnly(new CollectorFactory()))($requestBody)
            ->setCreator($this->getUser());

        /* Update anything that might have changed
         */
        $evidence = $requestTranslator($requestBody, $evidence)
            ->setBody($tokenReplacer->replace($baseModel->getBody(), $evidence->getRecipient()))
            ->setTags($baseModel->getTags())
            ->setCurriculumNodes($baseModel->getCurriculumNodes());

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

        $this->mapper->save($evidence);
    }


    /**
     * Reject a Moment, along with a reason which goes int the Moment's comment thread.
     *
     * @param Model $evidence
     * @return void
     */
    public function postReject(Model $evidence): void
    {
        /* Update the status of $evidence to Rejected
         */
        $evidence->reject($this->getUser());

        $this->mapper->save($evidence);

        /* Spawn a comment
         * Save it to $evidence's discussion thread somehow
         */
        $postData = $this->requestPost->getArrayCopy();

        $comment = $this->commentMapper->createModel()
            ->setContent($postData['rejectionReason'])
            ->setAuthor($this->getUser())
            ->setThread($evidence->getDiscussionThread());

        $this->commentMapper->save($comment);
    }


    /**
     * Checks whether curriculum tagging is enabled
     */
    private function curriculumTaggingEnabled(): bool
    {
        return $this->getUser()->getRole()->isStaff() && $this->getConfigLoader()->get('curriculum_tagging_enabled');
    }
}
