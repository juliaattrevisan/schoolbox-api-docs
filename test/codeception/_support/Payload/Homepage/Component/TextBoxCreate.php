<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Payload\Homepage\Component;

use Payload\PostTrait;
use Payload\Request;

/**
 * Encapsulates a textbox CREATE request
 */
class TextBoxCreate extends Request
{
    use PostTrait;

    /**
     * @var int
     */
    private $homepageId;

    /**
     * @var int
     */
    private $componentInstanceId;

    public function __construct(int $homepageId, int $componentInstanceId)
    {
        $this->setReferer("/textbox/$homepageId");
        $this->setHeading('');
        $this->setBody('');
        $this->homepageId          = $homepageId;
        $this->componentInstanceId = $componentInstanceId;
    }

    public function setHeading(string $heading): void
    {
        $this->setFormParam('heading', $heading);
    }

    public function setBody(string $text): void
    {
        $this->setFormParam('body', $text);
    }

    /** @inheritDoc */
    protected function getUrl(): string
    {
        return "/textbox/folder/{$this->homepageId}/component/{$this->componentInstanceId}";
    }
}
