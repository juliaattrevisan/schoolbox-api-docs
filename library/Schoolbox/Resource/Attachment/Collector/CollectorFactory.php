<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Attachment\Collector;


use InvalidArgumentException;
use PortfolioWebDAV;
use Schoolbox\Config\Loader;
use Schoolbox\MyFileMapper;
use Schoolbox\Resource\Browser\NodeFactory;
use Schoolbox\Resource\File\IFileMapper;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

class CollectorFactory {

    /** @var StorageMapper */
    protected $storage;

    /** @var FileMapper */
    protected $file;

    /** @var MyFileMapper */
    protected $myFile;

    /** @var PortfolioWebDAV */
    protected $dav;

    /** @var Loader */
    protected $conf;

    /** @var callable[] */
    protected $loaders;

    /**
     * The mapper passed in here defines the model that will be returned by self->build()->get*();
     * eg. by default, assumes StorageMapper, which means that Collector would return Storage objects.
     *
     * @param IFileMapper $mapper
     */
    public function __construct(IFileMapper $mapper = null)
    {
        $storage  = StorageMapper::getInstance();
        $file     = FileMapper::getInstance();
        $myFile   = MyFileMapper::getInstance();

        $this->add(NodeFactory::STORE, function () use ($storage, $mapper) {
            return new StorageHandler($storage, $mapper);
        });

        $this->add(NodeFactory::SBFS, function () use ($file, $mapper) {
            return new FileSystemHandler($file, $mapper);
        });

        $this->add(NodeFactory::MYFILE, function () use ($myFile, $mapper) {
            return new FileSystemHandler($myFile, $mapper);
        });

        try {
            $dav = PortfolioWebDAV::factory();
            $this->add(NodeFactory::WEBDAV, function () use ($dav, $mapper) {
                return new ExternalFileSystemHandler($dav, $mapper, $dav); // yup. totally passed $dav in twice
            });

        } catch (\DomainException $e) {
            // DAV isn't configured. we're ok with that.
        }
    }


    /**
     * @param string $type
     * @param callable $lazyload
     */
    protected function add($type, $lazyload) {
        if (is_callable($lazyload)) {
            $this->loaders[$type] = $lazyload;
        }
    }


    /**
     * @param string $type
     * @throws InvalidArgumentException
     * @return TypeHandlerInterface
     */
    protected function get($type)
    {
        if(empty($this->loaders[$type])) {
            throw new InvalidArgumentException("Loader for '$type' not defined.");
        }

        return call_user_func($this->loaders[$type]);
    }


    /**
     * Build method for the default Collector used most places
     *
     * @param array $attachments
     * @return Collector
     */
    public function build(array $attachments)
    {
        $handlers = array();

        $map = array(
            NodeFactory::STORE  => Collector::TYPE_STORAGE,
            NodeFactory::SBFS   => Collector::TYPE_SBFS,
            NodeFactory::MYFILE => Collector::TYPE_MYFILE,
            NodeFactory::WEBDAV => Collector::TYPE_WEBDAV
        );

        $indexedAttachments = array();

        // detect which handlers we are going to need and lazy them in
        foreach ($attachments as $attachment) {
            $xpl = explode(':', $attachment, 2);

            // check valid count
            if(count($xpl) != 2) {
                continue;
            }

            $mapped = $map[$xpl[0]];

            if(empty($handlers[$xpl[0]])) {
                $handlers[$mapped] = $this->get($xpl[0]);
            }

            $indexedAttachments[$mapped][] = $xpl[1];
        }

        return new Collector($indexedAttachments, $handlers);
    }

}