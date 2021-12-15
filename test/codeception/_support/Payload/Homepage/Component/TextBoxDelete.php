<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Payload\Homepage\Component;

use Payload\DeleteTrait;
use Payload\Request;

class TextBoxDelete extends Request
{
    use DeleteTrait;

    /**
     * @var int
     */
    private $textboxId;

    public function __construct(int $homepageId, int $textboxId)
    {
        $this->setReferer("/textbox/folder/$homepageId");
        $this->textboxId = $textboxId;
    }

    /** @inheritDoc */
    protected function getUrl(): string
    {
        return "/textbox/{$this->textboxId}";
    }
}
