<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api\StandardParser;

/**
 * Parser for standards from ACARA.
 */
class Acara implements IStrategy
{
    /**
     * @var array[]
     */
    private $resultCache = [];

    /**
     * @inheritdoc
     */
    public function parseStandard(string $standard): ?array
    {
        // Have I already found this result?
        if (array_key_exists($standard, $this->resultCache)) {
            return $this->resultCache[$standard];
        }


        // No? Load the document
        $domDocument = new \DOMDocument();
        $domDocument->loadHTML($standard, LIBXML_NOWARNING | LIBXML_NOERROR);


        // Find a link to Scootle
        $xpath = new \DOMXPath($domDocument);
        $link  = $xpath->query('//a[contains(@href, "scootle.edu.au")]')->item(0);


        if ($link) {
            // Found one: use it as the code
            $code = trim($link->textContent);

            // Use the text of the standard as the title
            $title = trim(strip_tags($domDocument->saveHTML()));

            $result = [
                'code'  => $code,
                'title' => $title,
            ];
        } else {
            $result = null;
        }

        $this->resultCache[$standard] = $result;
        return $result;
    }
}
