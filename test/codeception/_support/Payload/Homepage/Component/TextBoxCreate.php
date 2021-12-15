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
 * creates a text box through manage all textbox page via post
 */
class TextBoxCreate extends Request
{
    use PostTrait;

    /**
     * @var int
     */
    private int $homepageId;

    /**
     * @var int
     */
    private int $componentInstanceId;

    public function __construct(int $homepageId, int $componentInstanceId)
    {
        $this->setReferer("/textbox/$homepageId");
        $this->setHeading('');
        $this->setBody('');
        $this->homepageId          = $homepageId;
        $this->componentInstanceId = $componentInstanceId;

        $this->setHeader('content-type', 'application/json');
        $this->setAccepts('application/json, text/javascript, */*; q=0.01');
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
        return "/textbox/folder/{$this->homepageId}";
    }
}
