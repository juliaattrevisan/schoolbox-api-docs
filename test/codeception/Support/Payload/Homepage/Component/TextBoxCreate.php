<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Support\Payload\Homepage\Component;

use Tests\Support\Payload\PostTrait;
use Tests\Support\Payload\Request;

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

    public function __construct(int $homepageId)
    {
        $this->setReferer("/textbox/$homepageId");
        $this->setHeading('');
        $this->setBody('');
        $this->homepageId = $homepageId;

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
