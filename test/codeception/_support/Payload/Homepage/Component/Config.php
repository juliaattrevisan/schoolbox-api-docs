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
 * Encapsulates a homepage component create request
 */
class Config extends Request
{
    public const HOMEPAGE_COMPONENT_POSITION = [
        'left'  => 'column1',
        'right' => 'column2',
        'top'   => 'column3',
    ];

    use PostTrait;

    public function __construct(int $folderId)
    {
        $this->setReferer("/homepage/$folderId");
        $this->setFormParam('cid', $folderId);
    }

    /** @inheritDoc */
    protected function getUrl(): string
    {
        return '/homepage/configForm.php';
    }
}
