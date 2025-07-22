<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence\Moderation;

use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Alaress\Web\Controller\Base;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Discussion\Comment;
use Schoolbox\Learning\Moment\Evidence\Model;
use Schoolbox\Learning\Moment\Evidence\Translator;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Storage;
use Schoolbox\User;

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
     */
    public function putPublish(Model $evidence): void
    {
        $requestTranslator = new Translator\ArrayToModel\MutableFieldsOnly(new CollectorFactory());
        $requestTranslator->setCurriculumTaggingEnabled($this->curriculumTaggingEnabled());

        /* Publish it to the world
         */
        $evidence->publish($this->getUser());

        $this->mapper->save($evidence);

        /* TODO delete comments, if specified
         */

        $responseTranslator = new Translator\ModelToArray\Base(
            new User\Translator\Author\ModelToArray(),
            new Storage\Translator\ModelToArray(new PrefixGenerator($this->getConfigLoader())),
            new ScrubAdapter(),
        );

        $this->response = $this->responseAsJson($responseTranslator($evidence));
    }

    /**
     * Checks whether curriculum tagging is enabled
     */
    private function curriculumTaggingEnabled(): bool
    {
        return $this->getUser()->getRole()->isStaff() && $this->getConfigLoader()->get('curriculum_tagging_enabled');
    }
}
