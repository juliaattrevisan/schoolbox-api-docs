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
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Preview\Registry\Decoder;

use Schoolbox\Resource\Link;
use Schoolbox\Resource\LinkMapper;

class LinkRedir extends Base
{

    /**
     * Regex
     *
     * @var string
     */
    private $regex = '/redir\.php\?id=(\d+)/';


    /**
     * Distill a url from a Link instance
     *
     * @param string $url
     * @return string
     */
    public function decode($url)
    {
        $id = $this->isThisDecoder($url);

        if (!is_numeric($id)) {
            return '';
        }

        $linkMapper = LinkMapper::getInstance();

        try {
            /** @var Link $link */
            $link = $linkMapper->findById($id);
        } catch (\Exception $e) {
            return '';
        }

        $linkMapper->logAction($_SESSION['user'], 'access', $link);
        return $link->getUrl();
    }


    /**
     * Get the regex to use when deciding which decoder to use
     *
     * @return string
     */
    protected function getRegex()
    {
        return $this->regex;
    }
}
