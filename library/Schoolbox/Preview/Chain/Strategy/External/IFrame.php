<?php

/**
 * IFrame
 *
 * Load url in an iframe
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Preview\Chain\Strategy\External;

class IFrame extends Base
{

    /**
     * Test the Content-Types
     *
     * @return array
     */
    public function test()
    {
        foreach ($this->getAllowedURLs() as $allowedURL) {
            if (false !== stripos($this->getUrl(), trim($allowedURL))) {
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
     * Get allowed URLS
     *
     * @return array
     */
    private function getAllowedURLs()
    {
        return $this->getConfig();
    }
}
