<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Attachment\Internal;

use OutOfRangeException;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Attachment\AbstractController;
use SitePage;

/**
 * Class TeamsController
 *
 * Microsoft Teams segment in the File Picker modal
 *
 * @package Schoolbox\Resource\Attachment\External
 */
class TeamsController extends AbstractController implements InternalInterface
{

    /**
     * @param bool          $multi
     * @param string        $mimeFilter
     * @param IConfigLoader $conf
     * @throws OutOfRangeException
     */
    public function __construct($multi, $mimeFilter = null, $conf = null)
    {
        parent::__construct($multi, $mimeFilter);

        /* Decide if  Microsoft Teams is enabled
         */
        if (!(bool)$conf->get('teams_enabled')) {
            throw new OutOfRangeException('Microsoft Teams not enabled');
        }
    }

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
