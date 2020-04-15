<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Preview\Chain;

use Schoolbox\Mapper\Exception;
use Schoolbox\Preview\Chain\Strategy\Internal\Image;
use Schoolbox\Preview\Chain\Strategy\Internal\Media;
use Schoolbox\Preview\Chain\Strategy\Internal\IFrame;
use Schoolbox\Preview\Chain\Strategy\Internal\Redirect;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Zend\Uri\Exception\InvalidArgumentException;
use Zend\Uri\Uri;

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
     * Constructor
     *
     * @param string $url
     * @throws InvalidArgumentException
     */
    public function __construct(string $url) {

        /* Storage item: find the hash, and analyse it to find out
         * how to display it (or redirect, if it can't be displayed)
         */
        $uriObject = new Uri($url);

        /* $item can be one of:
         * Schoolbox\Resource\Storage\Model
         * Schoolbox\Resource\File\Model
         */
        if ($hash = $this->isStorage($uriObject)) {
            try {
                $item = StorageMapper::getInstance()->findByHash($hash);
                $this->processStorage($url, $item);
            } catch (Exception $e ) {
                //noop
            }
        } elseif ($id = $this->isSend($uriObject)) {
            try {
                $item = FileMapper::getInstance()->findById($id);
                $this->processStorage($url, $item);
            } catch (Exception $e ) {
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
     * @return string
     */
    private function isStorage(Uri $url): ?string
    {
        $path = $url->getPath();

        if ($path === '/storage/fetch.php' || $path === '/storage/image.php') {
            $query = $url->getQueryAsArray();

            return $query['hash'];
        }

        return null;
    }


    /**
     * Get the id from a send URL
     *
     * @param  Uri $url
     * @return string
     */
    private function isSend(Uri $url): ?string
    {
        if ($url->getPath() === '/send.php') {
            $query = $url->getQueryAsArray();

            return $query['id'];
        }

        return null;
    }


    /**
     * Process Storage file analysis
     *
     * @param string $url
     * @param \Schoolbox\Resource\Storage\Model|\Schoolbox\Resource\File\Model $item
     */
    private function processStorage(string $url, $item): void
    {
        $this->start([
            new Image($url, $item),
            new Media($url, $item),
            new IFrame($url, $item),
            new Redirect($url, $item),
        ]);
    }
}
