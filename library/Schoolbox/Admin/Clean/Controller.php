<?php
/**
 * Course builder admin page
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\Clean;

use Alaress\Web\Controller\Base;
use PDepend\Util\Log;
use Schoolbox\Resource\LogMapper;
use Schoolbox\User;
use Schoolbox\Resource\Storage;
use Schoolbox\Config\ConfigMapper;
use Proxy\Server as ProxyServer;
use Schoolbox\Admin\Navigation as AdminNavigation;

class Controller extends Base
{
    public function getIndex()
    {
        // Render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@adminClean/index.twig',
                'data'     => array(
                    'navigation' => AdminNavigation::getNavigation(),
                ),
            )
        );
    }

    function getClean()
    {
        $this->cleanStream();
        $this->cleanZip();

        $this->getResponse()->redirect->afterPost('/adminv2/cleanup');

    }

    function cleanStream()
    {
        /* Clean downloaded stream files (twitter, rss, weather) */
        foreach(glob(ConfigMapper::getInstance()->get('stream_dir').'/*.txt') as $file) {
            print "Deleting $file<br>";
            if(!@unlink($file))
                print "Failed to delete $file<br>";
        }
    }

    function cleanZip()
    {
        foreach(glob(ConfigMapper::getInstance()->get('zip_dir').'/*') as $file) {
            print "Deleting $file<br>";
            if (!@unlink($file)) {
                print "Failed to delete $file<br>";
            }
        }
    }
}
