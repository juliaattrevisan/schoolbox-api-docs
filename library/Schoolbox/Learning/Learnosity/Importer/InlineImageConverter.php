<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer;

use Psr\Log\LoggerInterface;
use Schoolbox\Resource\Storage\Mapper;

/**
 * Converts a base64 encoded image into a storage reference with a relative url
 */
class InlineImageConverter
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
     * Relative url to a storage file that we're about to make
     */
    private const STORAGE_URL = '/storage/fetch.php?hash=%s';


    /**
     * An arbitrary name for an inline image.
     */
    private const NEW_FILENAME = '%s.%s';


    /**
     * Default filename if an alt/title tag didn't exist
     */
    private const DEFAULT_NEW_FILENAME = 'inline image';


    /**
     * Content type prefix: required for forcing the DOM parser to recognise
     * the HTML to convert as UTF-8 encoded.
     */
    private const META_CONTENT_TYPE_PREFIX = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';


    /**
     * Constructor.
     *
     * @param Mapper $storageMapper
     * @param null|LoggerInterface $logger
     */
    public function __construct(private \Schoolbox\Resource\Storage\Mapper $storageMapper, private ?LoggerInterface $logger)
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
            $dom->loadHTML(self::META_CONTENT_TYPE_PREFIX . $html);
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

                $imageData = base64_decode($matches[2]);
                $extension = $matches[1];

                /* Calculate new filename
                 */
                $newFilename = $this->generateFilename($extension);

                /* Shank it into storage
                 */
                $storage = $this->storageMapper->acceptStream($newFilename, $imageData);

                /* Replace the node with a relative url equivalent
                 */
                $newSrc = sprintf(self::STORAGE_URL, $storage->getHash());

                $replacement = $dom->createElement('img');
                $replacement->setAttribute('src', $newSrc);
                $replacement->setAttribute('alt', $storage->getFilename());

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


    /**
     * Decide on a filename based on the attributes found in the img node
     *
     * @param string $extension
     * @return string
     */
    private function generateFilename(string $extension): string
    {
        return sprintf(
            self::NEW_FILENAME,
            self::DEFAULT_NEW_FILENAME,
            $extension
        );
    }
}
