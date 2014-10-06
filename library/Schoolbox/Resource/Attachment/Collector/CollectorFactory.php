<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Attachment\Collector;


use PortfolioWebDAV;
use Schoolbox\Config\Loader;
use Schoolbox\MyFileMapper;
use Schoolbox\Resource\Browser\NodeFactory;
use Schoolbox\Resource\FileMapper;
use Schoolbox\StorageMapper;
use Schoolbox\StorageMapperInterface;

class CollectorFactory {

    /** @var StorageMapperInterface */
    protected $storer;

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


    /**
     * The mapper passed in here defines the model that will be returned by self->build()->get*();
     * eg. by default, assumes StorageMapper, which means that Collector would return Storage objects.
     *
     * @param StorageMapperInterface $mapper
     */
    public function __construct(StorageMapperInterface $mapper = null)
    {
        $this->storage = StorageMapper::getInstance();
        $this->file    = FileMapper::getInstance();
        $this->myFile  = MyFileMapper::getInstance();
        $this->dav     = PortfolioWebDAV::factory();
        $this->conf    = new Loader();

        $this->storer  = $mapper ?: $this->storage;
    }


    /**
     * Build method for the default Collector used most places
     *
     * @param array $attachments
     * @return Collector
     */
    public function build(array $attachments)
    {
        return new Collector($attachments, array(
            Collector::TYPE_STORAGE => new StorageHandler($this->storage, $this->storer),
            Collector::TYPE_SBFS    => new FileSystemHandler(NodeFactory::SBFS, $this->conf->get('file_dir'), $this->file, $this->storer),
            Collector::TYPE_MYFILE  => new FileSystemHandler(NodeFactory::MYFILE, $this->conf->get('myfile_dir'), $this->myFile, $this->storer),
            Collector::TYPE_WEBDAV  => new ExternalFileSystemHandler(NodeFactory::WEBDAV, $this->dav, $this->dav, $this->storer) // yup. totally passed it in twice
        ));
    }

}