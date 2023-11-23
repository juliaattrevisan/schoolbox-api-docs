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
     * Constructor.
     *
     * @param Mapper $storageMapper
     * @param LoggerInterface $logger
     */
    public function __construct(private \Schoolbox\Resource\Storage\Mapper $storageMapper, private LoggerInterface $logger)
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
            $dom->loadHTML($html);
        } catch (\ErrorException $e) {
            /* Could not parse document; it's been truncated due to maximum length somewhere in our db.
             * It's literally half an html...I guess we have to skip it for now?
             */
            throw new \RuntimeException('Truncated data: could not parse xml');
        }

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//*[@src]');

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
                $this->logger->debug('Generating storage file for inline image');

                $imageData = base64_decode($matches[2]);
                $extension = $matches[1];

                /* Calculate new filename
                 */
                $newFilename = $this->generateFilename($node, $extension);

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

        // Only include the body parts in the converted HTML, not the entire
        // HTML document which loadHTML generates
        $bodyParts = [];
        foreach ($xpath->query('//body/*') as $bodyPart) {
            $bodyParts[] = trim($dom->saveHTML($bodyPart));
        }
        return implode("\n", $bodyParts);
    }


    /**
     * Decide on a filename based on the attributes found in the img node
     *
     * @param \DOMElement $node
     * @param string $extension
     * @return string
     */
    private function generateFilename(\DOMElement $node, string $extension): string
    {
        /* If there's a meaningful alt attribute, use that
         * Note that for some reason, alt text might be encoded as well
         * Not sure what kind of encoding it is, but it's not base64
         */
        $alt = trim($node->getAttribute('alt'));

        if ('' !== $alt) {
            return sprintf(
                self::NEW_FILENAME,
                $alt,
                $extension
            );
        }

        /* Otherwise, just use the default filename for lack of anything better
         */
        return sprintf(
            self::NEW_FILENAME,
            self::DEFAULT_NEW_FILENAME,
            $extension
        );
    }
}
