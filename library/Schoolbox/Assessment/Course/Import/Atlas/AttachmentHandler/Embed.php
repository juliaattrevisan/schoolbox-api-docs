<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\AttachmentHandler;

use Component_InstanceMapper;
use Schoolbox\Assessment\Course\Import\Atlas\Api\Attachment;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Textbox\Component\Homepage\Controller as TextboxComponent;
use Schoolbox\Resource\Textbox\Mapper as TextboxMapper;
use Schoolbox\User;

/**
 * Handler for importation of embedded documents from Atlas to Schoolbox.
 *
 * Embedded documents are imported as textboxes, which contain the document
 * in an iframe.
 */
class Embed implements IAttachmentHandler
{
    /**
     * @var TextboxMapper
     */
    private $textboxMapper;


    /**
     * @var Component_InstanceMapper
     */
    private $componentMapper;


    /**
     * Constructor
     *
     * @param TextboxMapper            $linkMapper
     * @param Component_InstanceMapper $componentMapper
     */
    public function __construct(TextboxMapper $linkMapper, Component_InstanceMapper $componentMapper)
    {
        $this->textboxMapper   = $linkMapper;
        $this->componentMapper = $componentMapper;
    }


    /**
     * @inheritdoc
     */
    public function attach(Attachment $attachment, Folder $folder, bool $private, User $owner): void
    {
        $textbox = $this->textboxMapper->createModel()
            ->setCategory($folder)
            ->setHeading($attachment->getTitle())
            ->setBody(sprintf('<iframe src="%s"></iframe>', htmlspecialchars($attachment->getUrl())))
            ->setDeletedAt(null);
        $this->textboxMapper->save($textbox);

        // Create the component
        $this->componentMapper->injectNewComponent(
            $folder,
            ['textbox' => $textbox->getId(), 'componentname' => $attachment->getTitle(),],
            TextboxComponent::class
        );
    }


    /**
     * Embeds can't be private, and are therefore not enabled by default.
     *
     * @param Attachment $attachment
     * @return array
     */
    public function getDefaultSettings(Attachment $attachment): array
    {
        return [
            'enabled' => false,
            'private' => null,
        ];
    }
}
