<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Attachment\Filesystem;

use OutOfRangeException;
use Schoolbox\IConfigLoader;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Resource\Attachment\AbstractController;
use Schoolbox\User;
use SitePage;

/**
 * Class TeamsController
 *
 * Microsoft Teams segment in the File Picker modal
 *
 * @package Schoolbox\Resource\Attachment\Filesystem
 */
class TeamsController extends AbstractController
{
    /**
     * The icon for this controller; appears in the accordion
     */
    private const ICON = 'microsoft-teams';


    /**
     * The name of this controller; appears in the accordion
     */
    private const LABEL = 'Microsoft Teams Meetings';


    /**
     * Enums for the different teams_enabled values
     */
    private const TEAMS_ENABLED_NONE = 0;
    private const TEAMS_ENABLED_STAFF = 1;
    private const TEAMS_ENABLED_STAFF_STUDENT = 2;


    /**
     * Url to load the Microsoft Teams React App
     *
     * @var string
     */
    private $teamsUrl;


    /**
     * Where this schoolbox instance currently resides
     * We set up a redirect to dump the meeting url on our own doorstep
     * This tells us which doorstep to dump upon
     *
     * @var string
     */
    private $domain;


    /**
     * Constructor
     *
     * @param bool               $multi
     * @param string             $mimeFilter
     * @param IConfigLoader|null $conf
     * @param User|null          $user
     */
    public function __construct(
        $multi,
        $mimeFilter = null,
        $conf = null,
        User $user = null
    ) {
        parent::__construct($multi, $mimeFilter);

        if (!$conf) {
            throw new OutOfRangeException('Config not supplied.');
        }

        /* Decide if Microsoft Teams is enabled for the given user
         */
        if (!$user) {
            throw new OutOfRangeException('Microsoft Teams may only be used by logged-in users');
        }
        switch ((int)$conf->get('teams_enabled')) {
            case self::TEAMS_ENABLED_STAFF:
                $teamsEnabled = $user->getRole()->isStaff();
                break;
            case self::TEAMS_ENABLED_STAFF_STUDENT:
                $teamsEnabled = $user->getRole()->isStaff() || $user->getRole()->isStudent();
                break;
            case self::TEAMS_ENABLED_NONE:
            default:
                $teamsEnabled = false;
                break;
        }
        if (!$teamsEnabled) {
            throw new OutOfRangeException('Microsoft Teams not enabled for users of this type');
        }

        /* Must supply a url
         */
        if ('' === (string)$conf->get('teams_url')) {
            throw new OutOfRangeException('Microsoft Teams app url not provided');
        }

        $this->teamsUrl = (string)$conf->get('teams_url');
        $this->domain = '' !== $conf->get('site_hostname') ? $conf->get('site_hostname') . '.' . $conf->get('site_domain') : $conf->get('site_domain');
    }


    /**
     * @inheritDoc
     */
    protected function pparse(SitePage $page)
    {
        $page->setVar([
            'TEAMS_URL' => $this->teamsUrl,
            'DOMAIN' => $this->domain
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
