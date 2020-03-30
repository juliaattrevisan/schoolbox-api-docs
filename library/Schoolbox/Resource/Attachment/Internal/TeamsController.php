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
     * The icon for this controller; appears in the accordion
     */
    private const ICON = 'progress';


    /**
     * The name of this controller; appears in the accordion
     */
    private const LABEL = 'Microsoft Teams Meetings';


    /**
     * Url to load the Microsoft Teams React App
     *
     * @var string
     */
    private $teamsUrl;


    /**
     * @param bool          $multi
     * @param string        $mimeFilter
     * @param IConfigLoader $conf
     * @throws OutOfRangeException
     */
    public function __construct($multi, $mimeFilter = null, $conf = null)
    {
        parent::__construct($multi, $mimeFilter);

        if (!$conf) {
            throw new OutOfRangeException('Config not supplied.');
        }

        /* Decide if  Microsoft Teams is enabled
         */
        if (!(bool)$conf->get('teams_enabled')) {
            throw new OutOfRangeException('Microsoft Teams not enabled');
        }

        /* Must supply a url
         */
        if ('' === (string)$conf->get('teams_url')) {
            throw new OutOfRangeException('Microsoft Teams app url not provided');
        }

        $this->teamsUrl = (string)$conf->get('teams_url');
    }


    /**
     * @inheritDoc
     */
    protected function pparse(SitePage $page)
    {
        $page->setVar([
            'TEAMS_URL' => $this->teamsUrl
        ]);
    }


    /**
     * @inheritDoc
     */
    public function getIcon()
    {
        return self::ICON;
    }


    /**
     * @inheritDoc
     */
    public function getLabel()
    {
        return self::LABEL;
    }


    /**
     * @inheritDoc
     */
    public function getRenderMethod()
    {
        return self::RENDER_IFRAME;
    }
}
