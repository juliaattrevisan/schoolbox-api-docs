<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api\StandardTransformer;

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
        $links = $xpath->query('//a[contains(@href, "scootle.edu.au")]');

        if (\count($links)) {
            // Found one: use it as the code
            $link = $links->item(0);
            $code = trim($link->textContent);

            // For the title: strip HTML and the code
            $title = strip_tags($domDocument->saveHTML());
            $title = preg_replace('/\\(' . preg_quote($title, '/') . '\\)', '', $title);
            $title = trim($title);

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
