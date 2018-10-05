<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Resource\Attachment\Collector;

use InvalidArgumentException;
use PortfolioWebDAV;
use Schoolbox\Config\Loader;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Browser\Office365\Locator;
use Schoolbox\Resource\Browser\Office365\Tenant;
use Schoolbox\Resource\My\File\Mapper as MyFileMapper;
use Schoolbox\Resource\Browser\NodeFactory;
use Schoolbox\Resource\File\IStorageWrapper;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

class CollectorFactory
{

    /** @var IStorageWrapper */
    private $wrapper;

    /** @var StorageMapper */
    private $storage;

    /** @var FileMapper */
    private $file;

    /** @var MyFileMapper */
    private $myFile;

    /** @var IConfigLoader */
    private $conf;

    /**
     * Set to false if not available so we can distinguish between not yet loaded, and not available
     *
     * @var TypeHandlerInterface[]|bool[]
     */
    private $loaders;


    /**
     * The mapper passed in here defines the model that will be returned by self->build()->get*();
     * eg. by default, assumes StorageMapper, which means that Collector would return Storage objects.
     *
     * @param IStorageWrapper $wrapper
     */
    public function __construct(IStorageWrapper $wrapper = null)
    {
        $this->wrapper = $wrapper;

        // TODO inject these service dependencies
        $this->storage = StorageMapper::getInstance();
        $this->file    = FileMapper::getInstance();
        $this->myFile  = MyFileMapper::getInstance();
        $this->conf    = new Loader();
    }


    /**
     * @param string $type
     * @throws InvalidArgumentException  If code attempts to create a DAV client with invalid HTTP method
     * @throws \UnexpectedValueException If you call this when no user is logged on for the webDav loader
     * @return TypeHandlerInterface
     */
    private function get(string $type): TypeHandlerInterface
    {
        // check cache
        if (!isset($this->loaders[$type])) {
            // Try to load cache
            switch ($type) {
                case NodeFactory::STORE:
                    $this->loaders[$type] = new StorageHandler($this->storage, $this->wrapper);
                    break;
                case NodeFactory::SBFS:
                    $this->loaders[$type] = new FileSystemHandler($this->file, $this->wrapper);
                    break;
                case NodeFactory::MYFILE:
                    $this->loaders[$type] = new FileSystemHandler($this->myFile, $this->wrapper);
                    break;
                case NodeFactory::WEBDAV:
                    try {
                        $dav = PortfolioWebDAV::factory();
                        $this->loaders[$type] = new ExternalFileSystemHandler($dav, $this->storage, $dav, $this->wrapper);
                    } catch (\DomainException $e) {
                        // DAV isn't configured. we're ok with that.
                        $this->loaders[$type] = false;
                    }
                    break;
                case NodeFactory::OFFICE:
                    try {
                        $locator = new Locator(new Tenant($this->conf->get('office365_tenant')), $_SESSION['azureAccessToken']);
                        $this->loaders[$type] = new ExternalFileSystemHandler($locator, $this->storage, $locator, $this->wrapper);
                    } catch (InvalidArgumentException $e) {
                        // Office 365 isn't configured. we're ok with that.
                        $this->loaders[$type] = false;
                    }
                    break;
            }
        }
        if (empty($this->loaders[$type])) {
            throw new InvalidArgumentException("Loader for '$type' not defined.");
        }

        return $this->loaders[$type];
    }


    /**
     * Build method for the default Collector used most places
     *
     * @param string[] $attachments
     * @return Collector
     * @throws InvalidArgumentException  If code attempts to create a DAV client with invalid HTTP method
     * @throws \UnexpectedValueException If you attempt to build a WebDav attachment and no user is logged in
     */
    public function build(array $attachments): Collector
    {
        $handlers = [];

        $map = [
            NodeFactory::STORE  => Collector::TYPE_STORAGE,
            NodeFactory::SBFS   => Collector::TYPE_SBFS,
            NodeFactory::MYFILE => Collector::TYPE_MYFILE,
            NodeFactory::WEBDAV => Collector::TYPE_WEBDAV,
            NodeFactory::OFFICE => Collector::TYPE_OFFICE,
        ];

        $indexedAttachments = [];

        // detect which handlers we are going to need and lazy them in
        foreach ($attachments as $attachment) {
            $xpl = explode(':', $attachment, 2);

            // check valid count
            if (\count($xpl) !== 2) {
                continue;
            }

            $mapped = $map[$xpl[0]];

            if (empty($handlers[$xpl[0]])) {
                $handlers[$mapped] = $this->get($xpl[0]);
            }

            $indexedAttachments[$mapped][] = $xpl[1];
        }

        return new Collector($indexedAttachments, $handlers);
    }
}
