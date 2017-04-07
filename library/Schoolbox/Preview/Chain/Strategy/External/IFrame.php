<?php
/**
 * IFrame
 *
 * Load url in an iframe
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain\Strategy\External;


class IFrame extends Base {

    /**
     * Content Types for this strategy
     *
     * @var array
     */
    private $allowedUrls = [
        'https://reports.lauriston.vic.edu.au/',
    ];

    /**
     * Test the Content-Types
     *
     * @return string
     */
    public function test() {
        foreach ($this->getAllowedURLs() as $allowedURL) {
            if (false !== stripos($this->getUrl(), $allowedURL)) {
                return [
                    'type' => 'iframe',
                    'src' => $this->getUrl(),
                ];
            }
        }

        return $this->testNext();
    }

    /* Getters/Setters
     */

    /**
     * Set content types
     *
     * @param array $allowedUrls
     */
    private function setAllowedURLs($allowedUrls)
    {
        $this->allowedUrls = $allowedUrls;
    }

    /**
     * Get content types
     *
     * @return array
     */
    private function getAllowedURLs()
    {
        return $this->allowedUrls;
    }
}