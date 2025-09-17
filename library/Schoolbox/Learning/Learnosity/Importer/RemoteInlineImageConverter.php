<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer;

use DOMElement;
use Psr\Log\LoggerInterface;
use Schoolbox\Learning\Learnosity\Api;

/**
 * Converts a base64 encoded image into a storage reference with a relative url
 */
class RemoteInlineImageConverter
{
    /**
     * Regex for detecting base64 encoded inline images,
     * using the first match as the file extension and the
     * second match as the base64 string itself.
     *
     * It searches over the entire html, over multiple lines,
     * looking for all the images that match this
     *
     * TODO There might be a smarter way to use xpath to do this,
     *  but I haven't encountered/found it
     */
    private const REGEX = '/data:image\/([a-zA-Z]+);base64,(.*)/m';


    /**
     * Content type prefix: required for forcing the DOM parser to recognise
     * the HTML to convert as UTF-8 encoded.
     */
    private const META_CONTENT_TYPE_PREFIX = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';


    /**
     * Constructor.
     *
     * @param Api $api
     * @param null|LoggerInterface $logger
     */
    public function __construct(private Api $api, private ?LoggerInterface $logger)
    {
    }


    /**
     * Convert base64 encoded images to storage images,
     * replacing their inline code with an equivalent
     *
     * @param string $html
     * @return string
     * @throws \DOMException
     * @throws \RuntimeException
     */
    public function convert(string $html): string
    {
        $dom = new \DOMDocument();

        try {
            /* Suppress these obnoxious warnings from test xml file as per Gordon's reply in
             * https://stackoverflow.com/questions/9149180/domdocumentloadhtml-error
             */
            libxml_use_internal_errors(true);
            $dom->loadHTML(self::META_CONTENT_TYPE_PREFIX . $html);
            libxml_use_internal_errors(false);
        } catch (\ErrorException $e) {
            /* Could not parse document; it's been truncated due to maximum length somewhere in our db.
             * It's literally half an html...I guess we have to skip it for now?
             */
            throw new \RuntimeException('Truncated data: could not parse xml');
        }

        $xpath     = new \DOMXPath($dom);
        $nodes     = $xpath->query('//*[@src]');
        $hasInline = false;

        foreach ($nodes as $node) {
            /* Not sure when this is not a DOMElement instance, but apparently occurs
             * as per SA-18321: maybe occurs when xpathing a nonexistent node
             */
            if (!$node instanceof DOMElement) {
                continue;
            }

            /* The src tag, a mega-huge base64 string
             */
            $src = $node->getAttribute('src');

            /* Shank out the parts you need
             */
            $isInline = preg_match(self::REGEX, $src, $matches);

            /* base64 inline image detected; perform replacement
             */
            if ($isInline) {
                $hasInline = true;

                $this->logger?->debug('Generating storage file for inline image');

                /* Some info about the image based on its html node
                 * There might not be much here, so we have to fill in the blanks ourselves
                 */
                $imageData = base64_decode($matches[2]);
                $extension = $matches[1];

                $filename = sprintf(
                    '%s.%s',
                    sha1($imageData),
                    $extension
                );

                /* Obtain a pre-signed url. This is the destination url that you'll ultimately
                 * end up using in the replacement node. Use a sha1 hash for uniqueness, not user-friendliness,
                 * because learnosity gives you this url:
                 *
                 * [public] => https://assets.learnosity.com/organisations/1013/4214eb709146635d37f90597a1ac545b2a9709c5.png
                 *
                 * ...which maybe conflicts with anyone else uploading the same filename. Anyway, this
                 * is probably functionally better than "inline image.jpg".
                 */
                $result = $this->api->dataGetRequest('/itembank/upload/assets', [
                    'subkeys' => [
                        $filename
                    ]
                ]);

                /* We'll use this to reference learnosity media from now on
                 */
                $storageUrl = $result['data'][0]['public'];

                /* POST data into this pre-signed url. This puts binary content into learnosity
                 */



                /* Replace the node with a relative url equivalent
                 */
                $replacement = $dom->createElement('img');
                $replacement->setAttribute('src', $storageUrl);
                $replacement->setAttribute('alt', $filename);

                $node->parentNode->replaceChild($replacement, $node);
            }
        }

        // No inline elements? Just return $html unmodified
        if (!$hasInline) {
            return $html;
        }

        // Only include the body parts in the converted HTML, not the entire
        // HTML document which loadHTML generates
        $bodyParts = [];
        foreach ($xpath->query('//body/*') as $bodyPart) {
            $bodyParts[] = trim($dom->saveHTML($bodyPart));
        }

        // clean up the spawned classes
        unset($dom, $xpath);

        return implode("\n", $bodyParts);
    }
}
