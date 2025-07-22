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
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Discussion\Comment;
use Schoolbox\Learning\Moment\Evidence\Model;
use Schoolbox\Learning\Moment\Evidence\Translator;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\GenericZone;
use Schoolbox\Resource\Exception;
use Schoolbox\Resource\Storage;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\User;
use Twig\Error\Error;

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

    /* Moderation workflow
     */

    /**
     * Form for preparing a Learning Moment for publishing to the world
     *
     * @api   GET /learning/evidence/{id}/publish
     * @param Model $evidence
     * @throws Error
     */
    public function getPublish(Model $evidence): void
    {
        // Which variant is being shown?
        // * text
        // * upload
        $momentType = $evidence->getType();
        $template   = $this->getTemplateFromType($momentType);

        /* Attachzone stuff for existing attachments
         */
        $zone = $this->buildAttachzone($momentType);
        if ($evidence->getStorage()) {
            $zone->attachFiles([$evidence->getStorage()->getHash()]);
        }

        /* Students get their learning moment moderation requirement tested;
         * parents and teachers bypass this check
         */
        $moderationRequired = false;

        if ($this->getUser()->getRole()->isStudent()) {
            $moderationRequired = (new ModerationRequired())->isSatisfiedBy($this->getConfigLoader()->get(ConfigMapper::NAME_LEARNING_MOMENTS_MODERATION_ENABLED), $this->getUser()->getYear());
        }

        $params = [
            'evidence'                 => $evidence,
            'users'                    => $evidence->getRecipient(),
            'userIds'                  => $evidence->getRecipient()->getId(),
            'otherUsers'               => [],
            'sharingForSelf'           => $this->getUser() === $evidence->getRecipient(),
            'curriculumTaggingEnabled' => $this->curriculumTaggingEnabled(),
            'moderationRequired'       => $moderationRequired,
            'isCreate'                 => false,
            'momentType'               => $momentType,
            'curriculumCodes'          => $evidence->getViewableCurriculumNodes()?->reindex(),
            'zone'                     => $zone->getTwigContext()
        ];

        $this->response = $this->responseFromTemplate(
            $template,
            $params
        );
    }


    /**
     * Persists any staff-made changes to a Moment and sets it in the published state
     *
     * @api   POST /learning/evidence/{id}/publish
     * @param Model $evidence
     * @return void
     * @throws MapperException
     * @throws Exception
     */
    public function postPublish(Model $evidence): void
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


    /**
     * Helper for building an attachzone for this controller.
     *
     * @param string $momentType
     * @return GenericZone
     */
    private function buildAttachzone(string $momentType): GenericZone
    {
        $zone = new GenericZone('storage', StorageMapper::getInstance());
        $zone->setAttachmentsLabel('')
            ->setAttachPrompt('upload files to this location')
            ->setMimeFilter('*/*')
            ->setFileVar('file')
            ->setMulti(false)
            ->setThumbnailSize(64)
            ->setModalTitle('Select file');
        switch ($momentType) {
            case Model::MOMENT_TYPE_PHOTO:
                $zone->setAttachPrompt('take a picture')
                    ->setIconClass('icon-camera')
                    ->setUseModal(false)
                    ->setCapture(true)
                    ->setMimeFilter('image/*');
                break;
            case Model::MOMENT_TYPE_AUDIO:
                $zone->setAttachPrompt('record audio')
                    ->setIconClass('icon-audio')
                    ->setUseModal(false)
                    ->setCapture(true)
                    ->setMimeFilter('audio/*');
                break;
            case Model::MOMENT_TYPE_VIDEO:
                $zone->setAttachPrompt('record video')
                    ->setIconClass('icon-video')
                    ->setUseModal(false)
                    ->setCapture(true)
                    ->setMimeFilter('video/*');
                break;
            case Model::MOMENT_TYPE_UPLOAD:
            case Model::MOMENT_TYPE_TEXT:
            default:
                break;
        }
        return $zone;
    }
}
