<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Attachment\External;

use Schoolbox\Resource\Attachment\AbstractController;
use SitePage;

/**
 * Class TeamsController
 *
 * Microsoft Teams segment in the File Picker modal
 *
 * @package Schoolbox\Resource\Attachment\External
 */
class TeamsController extends AbstractController implements ExternalInterface
{

    /**
     * @inheritDoc
     */
    protected function pparse(SitePage $page)
    {
        // TODO: Implement pparse() method.
    }

    /**
     * @inheritDoc
     */
    public function getIcon()
    {
        return 'icon-progress';
    }

    /**
     * @inheritDoc
     */
    public function getLabel()
    {
        return 'Microsoft Teams';
    }

    /**
     * @inheritDoc
     */
    public function getRenderMethod()
    {
        return self::RENDER_IFRAME;
    }
}
