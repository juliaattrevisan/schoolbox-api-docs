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


    /**
     * Using new inside a factory is totally ok, right?
     */
    public function __construct()
    {
        $this->storage = StorageMapper::getInstance();
        $this->file    = FileMapper::getInstance();
        $this->myFile  = MyFileMapper::getInstance();
        $this->dav     = PortfolioWebDAV::factory();
        $this->conf    = new Loader();
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
            Collector::TYPE_STORAGE => new StorageHandler($this->storage),
            Collector::TYPE_SBFS    => new FileSystemHandler(NodeFactory::SBFS, $this->conf->get('file_dir'), $this->file, $this->storage),
            Collector::TYPE_MYFILE  => new FileSystemHandler(NodeFactory::MYFILE, $this->conf->get('myfile_dir'), $this->myFile, $this->storage),
            Collector::TYPE_WEBDAV  => new ExternalFileSystemHandler(NodeFactory::WEBDAV, $this->dav, $this->dav, $this->storage) // yup. totally passed it in twice
        ));
    }

}