<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer;

use Alaress\Image\TransformFormat;
use DOMElement;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use LearnositySdk\Exceptions\ValidationException;
use Psr\Log\LoggerInterface;
use Schoolbox\Learning\Learnosity\Api;

/**
 * Converts a base64 encoded image into a learnosity media asset with an absolute url
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
     * Request timeout
     */
    private const DEFAULT_TIMEOUT = 30;


    /**
     * A separate guzzle instance for transferring binary data
     * Set upon first discovering the need
     *
     * @var Client|null
     */
    private ?Client $client = null;


    /**
     * Number of seconds to take a nap before retrying a failed endpoint
     *
     * Sometimes endpoints fail for no obvious reason and trying again seems
     * to work - instead of doing it manually, try insisting programmatically
     * waiting NAP_TIMEOUT seconds between each attempt
     */
    private const NAP_TIMEOUT = 3;


    /**
     * Number of times to retry after having NAP_TIMEOUT seconds' rest before
     * finally giving up
     */
    private const NUM_RETRY = 5;


    /**
     * Jitter litter that gets appended to an exponential backoff
     * to kind of randomise when a request can be retried, to prevent
     * multiple requests inevitably occurring again at the same time
     * to cause another rate limit
     */
    private const JITTER_LOWER = 0;
    private const JITTER_UPPER = 3;


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
     * @throws GuzzleException
     * @throws \JsonException
     * @throws ValidationException
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

            /* Check if this is an image and not some other random element
             * that just so happens to have a src attribute...like an iframe
             * See https://www.w3schools.com/tags/att_src.asp for a list of stuff
             *
             * Shank out the parts you need
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

                /* Anticipate the need for a guzzle instance because learnosity doesn't use their
                 * sdk to transmit binary data apparently. That's a bit weird. Anyway, spawn one
                 * upon first requirement: otherwise the xml doesn't contain any images
                 */
                if (null === $this->client) {
                    /* Infer a mime from the regex. It's a bit roundabout
                     */
                    $mime = 'image/' . $extension;

                    $headers = [
                        /* Supposedly important
                         * https://help.learnosity.com/hc/en-us/articles/26076399578397-Upload-assets-Endpoints-Data-API
                         */
                        'Expect' => '100-continue',
                        'Content-Type' => (new TransformFormat($mime))->getFormat(),
                    ];

                    $this->client = new Client([
                        'headers' => $headers,
                        'timeout' => self::DEFAULT_TIMEOUT,
                        /* Loudly die, forcing devs to process exceptions thrown by guzzle, preventing you from
                         * inspecting it via getStatusCode() the same way you would with a successful request.
                         */
                        'http_errors' => true,
                    ]);
                }

                /* Obtain a pre-signed url. This is the destination url that you'll ultimately
                 * end up using in the replacement node. Use a sha1 hash for uniqueness, not user-friendliness,
                 * because learnosity gives you this url:
                 *
                 * [public] => https://assets.learnosity.com/organisations/1013/4214eb709146635d37f90597a1ac545b2a9709c5.png
                 *
                 * ...which maybe conflicts with anyone else uploading the same filename. Anyway, this
                 * is probably functionally better than "inline image.jpg".
                 */
                $this->logger?->debug(sprintf(
                    'Obtaining pre-signed url for %s...',
                    $filename
                ));

                $result = $this->api->dataGetRequest('/itembank/upload/assets', [
                    'subkeys' => [
                        $filename
                    ]
                ]);

                /* TODO Find out how many we can transmit in one go: maybe we can do all of this
                 *  upload in one big request at the end instead of lots of individual calls.
                 *  Not sure it really matters in the long run though, maybe imperceptible difference
                 */
                $resultNode = $result['data'][0];

                /* We'll use this to reference learnosity media from now on
                 */
                $src = $resultNode['public'];

                /* POST data into this pre-signed url. This puts binary content into learnosity
                 */
                $destination = $resultNode['upload'];

                $this->logger?->debug(sprintf(
                    'src url: %s via pre-signed url %s',
                    $filename,
                    $destination
                ));

                /* This is supposed to return http 200, but not sure what *else* it could return
                 * as the documentation is particularly sparse.
                 *
                    $response = $this->client->put(...);

                    if ($response->getStatusCode() !== 200) {
                        // Log it, I suppose? But what comes back?
                    }
                */
                /* TODO Maybe add a retry mechanism here, but I'm not sure how often this fails
                 *  to warrant it: I also don't know the manner in which Learnosity announces
                 *  its failures. I thunk about it tho
                 */
                $this->logger?->debug(sprintf(
                    'Transmitting image binary into %s',
                    $destination
                ));

                /* Mine out an error message if you try to access a thing that doesn't exist
                 */
                $fails = 0;

                while ($fails < self::NUM_RETRY) {
                    try {
                        $response = $this->client->put($destination, [
                            'body' => $imageData
                        ]);

                        /* Get out of here: we succeeded
                         */
                        if (200 === $response->getStatusCode()) {
                            break;
                        }

                        /* Something died along the way: but not sure what
                         */
                        if (200 !== $response->getStatusCode()) {
                            $this->logger?->warning(sprintf(
                                'Learnosity returned HTTP status %d.',
                                $response->getStatusCode()
                            ));

                            $jitter = $this->calculateJitter($fails);

                            $this->induceNap($fails, $jitter, 'Received non-200 response while transmitting binary to presigned url on request %s: napping for %s seconds');

                            $fails++;
                        }
                    } catch (\Exception $e) {
                        $jitter = $this->calculateJitter($fails);

                        $this->induceNap($fails, $jitter, 'Cannot transmit binary to presigned url on request %s: napping for %s seconds');

                        $fails++;
                    }
                }

                /* TODO Now what?
                 *  I guess we could retry N times, then log the ultimate failure,
                 *  and leave the html untouched...or...?
                 */
//                if ($fails === (self::NUM_RETRY - 1)) {
//                    /* Normally this is an error message on an error screen
//                     * but we don't have one of those.
//                     */
//                }

                /* Replace the node with a relative url equivalent
                 */
                $replacement = $dom->createElement('img');
                $replacement->setAttribute('src', $src);
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


    /**
     * Calculate a random amount of jitter to sprinkle on an exponential backoff
     *
     * @param int $fails
     * @return int
     * @throws \Exception
     */
    private function calculateJitter(int $fails): int
    {
        /* To ensure the first failure's nap is 5 seconds long,
         * as per learnosity's default setting/suggestion
         */
        $jitter = 1;

        /* If we've failed previously, jitter litter some extra seconds
         * to randomise a bit more so we don't all try to start again
         * at the same time (always naps 5sec the first time we fail)
         */
        if ($fails) {
            $jitter = random_int(self::JITTER_LOWER, self::JITTER_UPPER);
        }

        return $jitter;
    }


    /**
     * Take a nap for a period of time
     * "some random jitter" is randomly between 0 and 3 sec by default
     *
     * Sleep for 3+2 seconds
     * then 3+4 seconds (2^2) + some random jitter,
     * then 3+8 seconds (2^3) + some random jitter,
     * then 3+16 seconds (2^4) + some random jitter,
     * then 3+32 seconds (2^5) + some random jitter...
     *
     * ...then give up after multiple full rate limited cycles
     */
    private function induceNap(int $fails, int $jitter, string $logMessage): void
    {
        $napTime = self::NAP_TIMEOUT + pow(2, $fails) + $jitter;

        $this->logger?->debug(sprintf(
            $logMessage,
            $fails,
            $napTime
        ));

        /* Back to sleep Jeffrey
         */
        sleep($napTime);
    }
}
