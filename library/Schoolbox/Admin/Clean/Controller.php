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

    // For later implementation if required

    //	function cleanLoginLog()
    //	{
    //		$query = "DELETE FROM login_log";
    //		$this->db->query($query);
    //		print "Login records cleaned<br>";
    //	}
    //	function cleanLogs()
    //	{
    //		//remove all log entries
    //		$this->cleanLoginLog();
    //        $query = "DELETE FROM homepage_log";
    //        $this->db->query($query);
    //        print "Homepage last visit records cleaned<br>";
    //        $query = "DELETE FROM homepage_visits";
    //        $this->db->query($query);
    //        print "Homepage visit records cleaned<br>";
    //        $query = "DELETE FROM submission_log";
    //		$this->db->query($query);
    //		print "Submission system last visit records cleaned<br>";
    //	}
    //	function cleanNews()
    //	{
    //		//deletes all news that is either deleted or has expired
    //		$query = "DELETE news_items, newsGroup, newsCampus FROM news_items, newsCampus, newsGroup
    //					WHERE news_items.item_id = newsGroup.id AND news_items.item_id = newsCampus.id
    //						AND (item_datetime_expires < NOW() || item_deleted = '1')";
    //		$this->db->dbDoQuery($query);
    //		print "Deleted ".$this->db->affectedRows()." news items<br>";
    //	}
    //	function cleanSubNews()
    //	{
    //		//deletes all news that is either deleted or has expired
    //		$query = "DELETE FROM subject_news WHERE datetime_expires < NOW() || deleted = '1'";
    //		$this->db->query($query);
    //		print "Deleted ".$this->db->affectedRows()." subject news items<br>";
    //	}
    //	function cleanDeletedFiles() {
    //		//deletes all files that are deleted
    //		$query = "SELECT id FROM file WHERE deleted = '1'";
    //		$this->db->query($query);
    //		while($file = $this->db->getNextArray()) {
    //			if(!@unlink(Cfg::get('file_dir')."/".$file['id'].".dat"))
    //			print "Unable to find ".Cfg::get('file_dir')."/".$file['id'].".dat<br>";
    //		}
    //		$query = "DELETE FROM file WHERE deleted = '1'";
    //		$this->db->query($query);
    //		print "Deleted ".$this->db->affectedRows()." files<br>";
    //	}
    //	function cleanFileSystem() {
    //		//delete all files from the filesystem that do no have a corresponding record in the database
    //		//normal files
    //		foreach(glob(Cfg::get('file_dir').'/*.dat') as $file) {
    //			preg_match('/.*\/([0-9]+).dat/', $file, $matches);
    //			if($matches[1]) {
    //				$query = "SELECT id FROM file WHERE id = '{$matches[1]}'";
    //				if(!$this->db->getRow($query)) {
    //					print "Deleting $file<br>";
    //					if(!@unlink($file))
    //						print "Failed to delete $file<br>";
    //				}
    //			}
    //		}
    //		//thumbnails
    //		foreach(glob(Cfg::get('thumbnail_dir').'/*.dat') as $file) {
    //			preg_match('/.*\/([0-9]+)-.*\.dat/', $file, $matches);
    //			if($matches[1]) {
    //				$query = "SELECT id FROM file WHERE id = '{$matches[1]}'";
    //				if(!$this->db->getRow($query)) {
    //					print "Deleting $file<br>";
    //					if(!@unlink($file))
    //						print "Failed to delete $file<br>";
    //				}
    //			}
    //		}
    //		//student submissions
    //		foreach(glob(Cfg::get('submission_dir').'/*.dat') as $file) {
    //			preg_match('/.*\/([0-9]+).dat/', $file, $matches);
    //			if($matches[1]) {
    //				$query = "SELECT id FROM student_submission WHERE id = '{$matches[1]}'";
    //				if(!$this->db->getRow($query)) {
    //					print "Deleting $file<br>";
    //					if(!@unlink($file))
    //						print "Failed to delete $file<br>";
    //				}
    //			}
    //		}
    //		//returns
    //		foreach(glob(Cfg::get('returns_dir').'/*.dat') as $file) {
    //			preg_match('/.*\/([0-9]+).dat/', $file, $matches);
    //			if($matches[1]) {
    //				$query = "SELECT id FROM submission_return WHERE id = '{$matches[1]}'";
    //				if(!$this->db->getRow($query)) {
    //					print "Deleting $file<br>";
    //					if(!@unlink($file))
    //						print "Failed to delete $file<br>";
    //				}
    //			}
    //		}
    //	}
    //	function cleanImages() {
    //        /* Delete SBFS Image Cache */
    //foreach(glob(Cfg::get('thumbnail_dir').'/*.jpg') as $file) {
    //print "Deleting $file<br>";
    //if(!@unlink($file))
    //print "Failed to delete $file<br>";
    //}
    ///* Delete MyFiles Image Cache */
    //foreach(glob(Cfg::get('myfiles_cache_dir').'/*.jpg') as $file) {
    //    print "Deleting $file<br>";
    //    if(!@unlink($file))
    //        print "Failed to delete $file<br>";
    //}
    ///* Delete Portrait Cache */
    //foreach(glob(Cfg::get('stream_dir').'/portrait/*.jpg') as $file) {
    //    print "Deleting $file<br>";
    //    if(!@unlink($file))
    //        print "Failed to delete $file<br>";
    //}
    ///* Delete social stream images */
    //foreach(glob(Cfg::get('stream_dir') . '/socialstream/thumbnails/*.jpg') as $file) {
    //    print "Deleting $file<br>";
    //    if(!@unlink($file))
    //        print "Failed to delete $file<br>";
    //}
    //foreach(glob(Cfg::get('stream_dir') . '/socialstream/socialstream_thumbnail_*') as $file) {
    //    print "Deleting $file<br>";
    //    if(!@unlink($file))
    //        print "Failed to delete $file<br>";
    //}
    ///* Delete storage images */
    //foreach(glob(Cfg::get('stream_dir').'/storage/*.jpg') as $file) {
    //    print "Deleting $file<br>";
    //    if(!@unlink($file))
    //        print "Failed to delete $file<br>";
    //}
    //}
}
