<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer;

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
     * @param \Schoolbox\Resource\Storage\Mapper $storageMapper
     */
    public function __construct(private \Schoolbox\Resource\Storage\Mapper $storageMapper)
    {
    }


    /**
     * Convert base64 encoded images to storage images,
     * replacing their inline code with an equivalent
     *
     * @param string $html
     * @return string
     * @throws \DOMException
     */
    public function convert(string $html): string
    {
        $dom = new \DOMDocument();
        $dom->loadHTML($html);

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
                $imageData = base64_decode($matches[2]);

                /* Calculate new filename
                 */
                $newFilename = $this->generateFilename($node);

//                $newFilename = sprintf(
//                    self::NEW_FILENAME,
//                    $matches[1]
//                );

                /* Shank it into storage
                 */
                $storage = $this->storageMapper->acceptStream($newFilename, $imageData);

                /* Replace the node with a relative url equivalent
                 */
                $newSrc = sprintf(self::STORAGE_URL, $storage->getHash());

                $replacement = $dom->createElement('img');
                $replacement->setAttribute('src', $newSrc);

                $node->parentNode->replaceChild($replacement, $node);
            }
        }

        return $dom->saveHTML();
    }


    /**
     * Decide on a filename based on the attributes found in the img node
     *
     * @param $node
     * @return string
     */
    private function generateFilename($node): string
    {
        /* If there's a meaningful alt attribute, use that
         * Note that for some reason, alt text might be base64-encoded as well
         */

        /* If there's a meaningful title, use that
         * Note that for some reason, title text might be base64-encoded as well
         * (I haven't seen this yet in the wild though
         */

        /* Otherwise, just use the default filename for lack of anything better
         */

        return $filename;
    }
}
