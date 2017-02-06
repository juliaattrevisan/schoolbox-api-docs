<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Evaluation\Import\Parser;

use SimpleXMLElement;

/**
 * Base 16.0 behaviour for both quiz and survey.
 *
 * Overriding classes can modify for quiz/survey and/or different file format versions
 */
abstract class Base implements IParser
{
    /**
     * @var SimpleXMLElement
     */
    private $xml;

    /**
     * @param SimpleXMLElement $xml
     */
    public function __construct(SimpleXMLElement $xml)
    {
        $this->xml = $xml;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return (string)$this->xml->title;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->parseRichTextNode('description');
    }

    /**
     * @return string
     */
    public function getCompletionMessage()
    {
        return $this->parseRichTextNode('completionmessage');
    }

    /**
     * @return int
     */
    public function getMaxAttempts()
    {
        // Try to get max attempts if it's there, otherwise fall back to
        // allowresit (not allow_resit).
        // if allowresit is true, or is not unspecified:
        // max_attempts = 0 (unlimited), otherwise 1.
        $max_attempts = (int)$this->xml->maxattempts;
        if (strlen($max_attempts) === 0) {
            $allowResit = (string)$this->xml->allowresit;
            $max_attempts = ($allowResit || strlen($allowResit) === 0) ? 0 : 1;
        }
        return $max_attempts;
    }

    /**
     * Parses a rich text node
     *
     * @param string $nodeName
     * @return string
     */
    protected function parseRichTextNode($nodeName)
    {
        // Value should just be a single node, but maybe it wasn't wrapped in
        // <![CDATA[ ]]> tags.
        $value = trim("{$this->getXml()->$nodeName}");
        if (!$this->getXml()->$nodeName) {
            foreach ($this->getXml()->xpath("/quiz/$nodeName/*") as $desc_node) {
                $value .= $desc_node->asXML();
            };
        }
        return $value;
    }

    /**
     * @return SimpleXMLElement
     */
    protected function getXml()
    {
        return $this->xml;
    }
}