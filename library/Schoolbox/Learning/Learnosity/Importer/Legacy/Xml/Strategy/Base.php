<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Legacy\Xml\Strategy;

abstract class Base
{
    /**
     * Constructor.
     *
     * $domain is used to replace relative urls with absolute urls
     * Anything that allowed a cke will potentially have relative urls
     *
     * @param string $domain
     */
    public function __construct(protected string $domain)
    {
    }


    /**
     * Prepares an arbitrary question richtext $question contents
     *    * negates attacks
     *    * replaces relative urls with absolute urls
     *
     * @param string $question
     * @return string
     */
    protected function produceQuestionText(string $question): string
    {
        return \Scrub::htmlAbsoluteUri($question, $this->domain);
    }


    /**
     * Decompiles $answer's xml value
     *
     * @param \SimpleXMLElement $answers
     * @return array
     * @throws \RuntimeException
     */
    public function decompileAnswer(\SimpleXMLElement $answers): array
    {

    }
}
