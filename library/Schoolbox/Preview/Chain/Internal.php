<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Preview\Chain;

use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper as DueWorkResponseFileMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Preview\Chain\Strategy\Internal\PDF;
use Schoolbox\Preview\Chain\Strategy\Internal\Image;
use Schoolbox\Preview\Chain\Strategy\Internal\Media;
use Schoolbox\Preview\Chain\Strategy\Internal\IFrame;
use Schoolbox\Preview\Chain\Strategy\Internal\Redirect;
use Schoolbox\Resource\File;
use Schoolbox\Resource\Storage;
use Schoolbox\Url\Uri;

/**
 * Class Internal
 *
 * Seeks preview from an Internal (local) url
 *
 * @package Schoolbox\Preview\Chain
 */
class Internal extends Base
{
    /**
     * Storage urls
     */
    private const URL_STORAGE = ['/storage/fetch.php', '/storage/image.php'];


    /**
     * Send url
     */
    private const URL_SEND = '/send.php';


    /**
     * Pattern for due work response file regexps
     */
    private const URL_DUE_WORK_RESPONSE_FILE_REGEXP = '`^/learning/assessments/\\d+/\\d+/responses/\\d+/files/(\\d+)`';


    /**
     * Constructor
     *
     * @param string $url
     */
    public function __construct(string $url)
    {

        /* Storage item: find the hash, and analyse it to find out
         * how to display it (or redirect, if it can't be displayed)
         */
        $uriObject = new Uri($url);

        /* $item can be one of:
         * Schoolbox\Resource\Storage\Model
         * Schoolbox\Resource\File\Model
         * Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model
         */
        if ($hash = $this->isStorage($uriObject)) {
            try {
                $item = Storage\Mapper::getInstance()->findByHash($hash);
                $this->processStorage($url, $item);
            } catch (Exception $e) {
                //noop
            }
        } elseif ($id = $this->isSend($uriObject)) {
            try {
                $item = File\Mapper::getInstance()->findById($id);
                $this->processStorage($url, $item);
            } catch (Exception $e) {
                //noop
            }
        } elseif ($id = $this->isDueWorkResponseFile($uriObject)) {
            try {
                $item = DueWorkResponseFileMapper::getInstance()->findById($id);
                $this->processStorage($url, $item);
            } catch (Exception $e) {
                //noop
            }
        } else {
            /* Just redirect straight there
             */
            $this->start([
                new Redirect($url, null),
            ]);
        }
    }


    /**
     * Get the hash out of a storage url
     *
     * @param  Uri $url
     * @return string|null
     */
    private function isStorage(Uri $url): ?string
    {
        if (\in_array($url->getPath(), self::URL_STORAGE, true)) {
            $query = $url->getQueryAsArray();

            return $query['hash'];
        }

        return null;
    }


    /**
     * Get the id from a send URL
     *
     * @param  Uri $url
     * @return string|null
     */
    private function isSend(Uri $url): ?string
    {
        if ($url->getPath() === self::URL_SEND) {
            $query = $url->getQueryAsArray();

            return $query['id'];
        }

        return null;
    }


    /**
     * Get the ID from a due work response file URL.
     *
     * @param Uri $uri
     * @return string|null
     */
    private function isDueWorkResponseFile(Uri $uri): ?string
    {
        if (preg_match(self::URL_DUE_WORK_RESPONSE_FILE_REGEXP, $uri->getPath(), $matches)) {
            return $matches[1];
        }
        return null;
    }


    /**
     * Process Storage file analysis
     *
     * @param string $url
     * @param Storage\IStorage $item
     */
    private function processStorage(string $url, Storage\IStorage $item): void
    {
        $this->start([
            new PDF($url, $item),
            new Image($url, $item),
            new Media($url, $item),
            new IFrame($url, $item),
            new Redirect($url, $item),
        ]);
    }
}
