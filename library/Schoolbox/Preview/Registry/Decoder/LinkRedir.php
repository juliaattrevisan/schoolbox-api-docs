<?php
/**
 * LinkRedir
 *
 * Link Redir - could be a link to anywhere, both
 * internal or external. Decipher what is going on
 * before sending them into the Chains to determine
 * how to display the encoded link's contents.
 *
 * If it's in the form of redir.php?url=abcdefgh then
 * it'll skip this part entirely and end up redirecting
 * straight to that page, which then redirects you again.
 *
 * /redir.php classifies as a Local Url.
 *
 * This also logs the link view, for homepage stats
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Registry\Decoder;

use Schoolbox\Resource\LinkMapper;

class LinkRedir extends Base {

    /**
     * Distill a url from a Link instance
     *
     * @param $url
     * @return mixed|void
     */
    public function decode($url) {
        if ($id = $this->isLinkRedir($url)) {
            $linkMapper = new LinkMapper();

            try {
                $link = $linkMapper->findById($id);
                $linkMapper->logAction($_SESSION['user'], 'access', $link);

                return $link->getUrl();
            }
            catch (\Exception $e) {
            }
        }

        return '';
    }

    /**
     * Get the link id out of the url
     *
     * @param $url
     * @return bool
     */
    private function isLinkRedir($url) {
        $id = false;

        preg_match('/redir\.php\?id=([0-9]+)/', $url, $matches);

        if (count($matches)) {
            $id = $matches[1];
        }

        return $id;
    }
}