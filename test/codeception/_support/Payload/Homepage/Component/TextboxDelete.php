<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload\Homepage\Component;

use Payload\GetTrait;
use Payload\Request;

class TextboxDelete extends Request
{
    use GetTrait;

    public function __construct(int $homepageId, int $textboxId)
    {
        $this->setReferer("/homepage/$homepageId");

        $this->setQueryParam('delete', $textboxId);
        $this->setQueryParam('originID', $homepageId);
    }

    /** @inheritDoc */
    protected function getUrl(): string
    {
        return '/textbox/textBoxForm.php';
    }
}
