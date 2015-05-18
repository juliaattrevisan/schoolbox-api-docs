<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Browser\Office365;


use Alaress\Authorizer\Failure\AuthenticationTimedOut;
use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\File\Remote\FetcherInterface;
use Alaress_Date_Format;
use InvalidArgumentException;
use OutOfBoundsException;
use Proxy;
use RuntimeException;
use Schoolbox\Resource;

class Locator implements Resource\File\ILocator, Resource\Folder\ILocator, FetcherInterface
{
    /** @var Tenant */
    protected $tenant;

    /** @var string */
    protected $oAuthToken;


    /**
     * @param Tenant $tenant
     * @param string $oAuthToken
     */
    public function __construct(Tenant $tenant, $oAuthToken = null)
    {
        $this->tenant = $tenant;
        $this->setOAuthToken($oAuthToken);
    }


    /**
     * @return Tenant
     */
    public function getTenant()
    {
        return $this->tenant;
    }


    /**
     * @param string $oAuthToken
     * @return self
     */
    public function setOAuthToken($oAuthToken)
    {
        $this->oAuthToken = $oAuthToken;
        return $this;
    }


    /**
     * Shared method between the interfaces, because 365 only uses a concept of nodes rather than distinct file/folder entities
     *
     * Return the file or folder by the given ID
     *
     * @param string $nodeId
     * @return Resource\File\IFile|Resource\Folder\IFolder
     */
    public function findById($nodeId)
    {
        $item = $this->fetchMetaData($nodeId);

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
     * @return Resource\File\IFile[]
     */
    public function findByFolderId($nodeId)
    {
        $nodeId = $nodeId ?: 'root';
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
     * @return Resource\Folder\IFolder[]
     */
    public function findByParentId($nodeId)
    {
        $nodeId = $nodeId ?: 'root';
        $items = $this->rq("/files/$nodeId/children");

        // iterate, filtering to folders only and casting to folder objects
        $folders = [];

        foreach ($items['value'] as $item) {
            try {
                $folders[] = $this->parseFolder($item);
            } catch (InvalidArgumentException $e) {
                // noop
            }
        }

        return $folders;
    }


    /**
     * @param $item
     * @return Resource\File\Model
     */
    protected function parseFile($item)
    {
        if ($item['type'] != 'File') {
            throw new InvalidArgumentException('Not a file');
        }

        $file = Resource\File\Mapper::getInstance()->createModel();
        $file->setId($item['id']);
        $file->setFilename($item['name']);
        $file->setTitle($item['name']);
        $file->setSize($item['size']);

        // TODO @armatronic why is this exploding?
        /*
        $file->setCreatedAt(new Alaress_Date_Format(
            $item['dateTimeCreated'], 'c'
        ));

        $file->setModifiedAt(new Alaress_Date_Format(
            $item['dateTimeLastModified'], 'c'
        ));
        */

        $folder = Resource\Folder\Mapper::getInstance()->createModel();
        $folder->setId($item['parentReference']['id']);
        $file->setFolder($folder);


        return $file;
    }


    /**
     * @param array $item
     * @return Resource\Folder\Model
     */
    protected function parseFolder(array $item)
    {
        if ($item['type'] != 'Folder') {
            throw new InvalidArgumentException('Not a folder');
        }

        $folder = Resource\Folder\Mapper::getInstance()->createModel();

        $folder->setId($item['id']);
        $folder->setName($item['name']);

        $parent = Resource\Folder\Mapper::getInstance()->createModel();
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
        $ch = Proxy\Server::getInstance()->curl_init($this->getTenant()->getBaseUri().$path);

        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => [ 'Authorization: Bearer ' . $this->oAuthToken ],
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1
        ]);

        $rs = curl_exec($ch);

        $data = json_decode($rs, true);

        if (empty($data)) {
            // timed out
            throw new AuthenticationTimedOut('Connecting to Office 365 timed out, please try again later.');
        }

        // interpret errors
        if (isset($data['error'])) {
            throw new RuntimeException('Office 365 Exception: '.$data['error']['message']);

        } elseif (isset($data['error_description'])) {
            throw new Unauthorized('Office 365 Security Exception: '.$data['error_description']);

        }

        return $data;
    }


    /**
     * Retrieves the metadata for the given fileId
     *
     * @param int $nodeId
     * @return array
     */
    public function fetchMetaData($nodeId)
    {
        return $this->rq("/files/$nodeId");
    }


    /**
     * Attempts to retrieve remote source to local destination, returns the path of the saved file.
     *
     * @param int $fileId
     * @throws OutOfBoundsException
     * @return string
     */
    public function fetchToFile($fileId)
    {
        $file = $this->fetchMetaData($fileId);

        // "URL for downloading the file's content."
        $url = $file['contentUrl'];

        if (!$url) {
            throw new OutOfBoundsException("No url found for download");
        }

        $destination = tempnam(sys_get_temp_dir(), 'sp');

        if (!($fp = fopen($destination, 'w'))) {
            throw new OutOfBoundsException("Could not open target");
        }

        $ch = Proxy\Server::getInstance()->curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_FILE       => $fp,
            CURLOPT_HTTPHEADER => [ 'Authorization: Bearer ' . $this->oAuthToken ],
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1
        ]);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        return $destination;
    }

}
