<?php

/**
 * Vimeo
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream\Post\Metadata;

class MetaTag extends Metadata
{

    /**
     * Fetch information out of the meta tags
     *
     * @return mixed
     */
    public function fetchMetadata()
    {
        $this->analyseTitle();
        $this->analyseDescription();

        if (strlen($this->getTitle())) {
            return $this;
        }

        /* Otherwise get the next class to deal with it
         */
        if ($this->getNext()) {
            return $this->getNext()->fetchMetadata();
        }
    }

    /**
     * Find the title
     */
    private function analyseTitle()
    {
        $allElements = $this->getXml()->query("//title");

        foreach ($allElements as $currentElement) {
            $this->setTitle(trim($currentElement->nodeValue));

            return;
        }
    }

    /**
     * Find the description
     */
    private function analyseDescription()
    {
        $query = "//meta[@name='description']";

        $this->setDescription($this->analyse($query, array('content')));
    }
}
