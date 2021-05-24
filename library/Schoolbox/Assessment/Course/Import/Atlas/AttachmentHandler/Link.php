<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\AttachmentHandler;

use Schoolbox\Assessment\Course\Import\Atlas\Api\Attachment;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\LinkMapper;
use Schoolbox\User;

/**
 * Handler for importation of links from Atlas to Schoolbox.
 */
class Link implements IAttachmentHandler
{
    /**
     * @var LinkMapper
     */
    private $linkMapper;


    /**
     * Constructor
     *
     * @param LinkMapper $linkMapper
     */
    public function __construct(LinkMapper $linkMapper)
    {
        $this->linkMapper = $linkMapper;
    }


    /**
     * Create a component for this item to live in.
     *
     * TODO implement component creation
     *
     * @param Folder $folder
     */
    public function createComponent(Folder $folder): void
    {
        // Does this already have a Link component for this folder?
        // Create it if it doesn't.
    }


    /**
     * @inheritdoc
     */
    public function attach(Attachment $attachment, Folder $folder, bool $private, User $owner): void
    {
        $linkModel = $this->linkMapper->createModel()
            ->setFolder($folder)
            ->setDescription($attachment->getTitle())
            ->setUrl($attachment->getUrl())
            ->setOwner($owner)
            ->setHidden($private);
        $this->linkMapper->save($linkModel);
    }


    /**
     * Links are enabled and private by default.
     *
     * @param Attachment $attachment
     * @return array
     */
    public function getDefaultSettings(Attachment $attachment): array
    {
        return [
            'enabled' => true,
            'private' => true,
        ];
    }
}
