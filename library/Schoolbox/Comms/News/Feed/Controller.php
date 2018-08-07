<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\Feed;

use Alaress\Web\Controller\Base;
use Schoolbox\Resource\Folder;
use Schoolbox\User;

class Controller extends Base
{

    /**
     * Controller constructor.
     */
    public function __construct()
    {
    }


    /**
     * Generate RSS feed
     *
     * @param User              $user
     * @param null|Folder\Model $folder
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getFeed(User $user, ?Folder\Model $folder)
    {
        new \Alaress_Probe($user, $folder);
        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsFeed/rss.twig', [
            'user' => $user,
            'folder' => $folder
        ])->setTitle('Example'));
    }
}
