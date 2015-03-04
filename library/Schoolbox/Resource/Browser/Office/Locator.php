<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Browser\Office;


use InvalidArgumentException;
use Proxy;
use RuntimeException;
use Schoolbox\FileInterface;
use Schoolbox\FolderInterface;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\File;
use Schoolbox\Resource\FileLocatorInterface;
use Schoolbox\Resource\FolderLocatorInterface;

class Locator implements FileLocatorInterface, FolderLocatorInterface
{

    /** @var string */
    protected $baseUri;

    /** @var string */
    protected $oAuthToken;

    /**
     * @param string $tenant
     * @param string $oAuthToken
     * @param string $sitePath if supplied, will operate in SharePoint mode, otherwise OneDrive
     * @param string $version
     */
    public function __construct($tenant, $oAuthToken, $sitePath = null, $version = 'v1.0')
    {
        if ($sitePath) {
            $this->baseUri = sprintf("https://%s.sharepoint.com/%s/_api/%s", $tenant, $sitePath, $version);
        } else {
            $this->baseUri = sprintf("https://%s-my.sharepoint.com/_api/%s/me", $tenant, $version);
        }

        $this->oAuthToken = $oAuthToken;
    }


    /**
     * Shared method between the interfaces, because 365 only uses a concept of nodes rather than distinct file/folder entities
     *
     * Return the file or folder by the given ID
     *
     * @param string $nodeId
     * @return FileInterface|FolderInterface
     */
    public function findById($nodeId)
    {
        $item = $this->rq("/files/$nodeId");

        if ($item['type'] == 'File') {
            return $this->parseFile($item);
        } elseif ($item['type'] == 'Folder') {
            return $this->parseFolder($item);
        }
        throw new InvalidArgumentException('Somehow not a file or folder; got '.$item['type']);
    }


    /**
     * Return the files contained within the folder with the given ID
     *
     * @param string $nodeId
     * @return FileInterface[]
     */
    public function findByFolderId($nodeId)
    {
        $items = $this->rq("/files/$nodeId/children");

        // iterate, filtering to files only and casting to file objects
        $files = [];

        foreach ($items['value'] as $item) {
            try {
                $files[] = $this->parseFile($item);
            } catch (InvalidArgumentException $e) {
                // noop
            }
        }

        return $files;
    }


    /**
     * Return the folders contained within the folder with the given ID
     *
     * @param string $nodeId
     * @return FolderInterface[]
     */
    public function findByParentId($nodeId)
    {
        $items = $this->rq("/files/$nodeId/children");

        // iterate, filtering to folders only and casting to folder objects
        $folders = [];

        foreach ($items['value'] as $item) {
            $folders[] = $this->parseFolder($item);
        }

        return $folders;
    }


    /**
     * @param $item
     * @return File
     */
    protected function parseFile($item)
    {
        if ($item['type'] != 'File') {
            throw new InvalidArgumentException('Not a file');
        }

        $file = new File();
        $file->setId($item['id']);
        $file->setFilename($item['name']);
        $file->setFilesize($item['size']);
        $file->setSubmitted(new \Alaress_Date_Format(
            $item['dateTimeCreated'], 'c'
        ));
        $file->setModified(new \Alaress_Date_Format(
            $item['dateTimeLastModified'], 'c'
        ));

        $folder = new Category();
        $folder->setId($item['parentReference']['id']);
        $file->setFolder($folder);

        return $file;
    }


    /**
     * @param array $item
     * @return Category
     */
    protected function parseFolder(array $item)
    {
        if ($item['Type'] != 'Folder') {
            throw new InvalidArgumentException('Not a folder');
        }

        $folder = new Category();

        $folder->setId($item['id']);
        $folder->setName($item['name']);

        $parent = new Category();
        $parent->setId($item['parentReference']['id']);

        $folder->setParent($parent);

        return $folder;
    }


    /**
     * @param string $path
     * @return array
     */
    protected function rq($path)
    {
        $ch = Proxy\Server::getInstance()->curl_init($this->baseUri.$path);

        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => [ 'Authorization: Bearer ' . $this->oAuthToken ],
            CURLOPT_RETURNTRANSFER => 1
        ]);

        $rs = curl_exec($ch);

        $data = json_decode($rs, true);

        if (isset($data['error'])) {
            throw new RuntimeException('Office 365 Exception: ' . $data['error']['message']);
        }

        return $data;
    }

} 