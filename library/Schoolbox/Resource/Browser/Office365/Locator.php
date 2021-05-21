<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Resource\Browser\Office365;

use Alaress\Authorizer\Failure\AuthenticationTimedOut;
use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\File\Remote\FetcherInterface;
use InvalidArgumentException;
use OutOfBoundsException;
use Proxy;
use RuntimeException;
use Schoolbox\GenericCollection;
use Schoolbox\Resource;

/**
 * Class Locator
 *
 * @package Schoolbox\Resource\Browser\Office365
 */
class Locator implements Resource\File\ILocator, Resource\Folder\ILocator, FetcherInterface
{
    /**
     * @var Tenant
     */
    protected $tenant;

    /**
     * @var string
     */
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
     * @throws AuthenticationTimedOut
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws Unauthorized
     * @throws \Schoolbox\Mapper\Exception
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
     * @return GenericCollection|Resource\File\IFile[]
     * @throws AuthenticationTimedOut
     * @throws RuntimeException
     * @throws Unauthorized
     * @throws \Schoolbox\Mapper\Exception
     */
    public function findByFolderId($nodeId)
    {
        $nodeId = $nodeId ?: 'root';
        $items = $this->rq("/files/$nodeId/children");

        // iterate, filtering to files only and casting to file objects
        $files = new GenericCollection(Resource\File\IFile::class);

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
     * @return GenericCollection|Resource\Folder\IFolder[]
     * @throws AuthenticationTimedOut
     * @throws RuntimeException
     * @throws Unauthorized
     */
    public function findByParentId($nodeId)
    {
        $nodeId = $nodeId ?: 'root';
        $items = $this->rq("/files/$nodeId/children");

        // iterate, filtering to folders only and casting to folder objects
        $folders = new GenericCollection(Resource\Folder\IFolder::class);

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
     * @param array $item
     * @return Resource\File\Model
     * @throws InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     */
    protected function parseFile($item)
    {
        if ($item['type'] != 'File') {
            throw new InvalidArgumentException('Not a file');
        }

        $filename = mb_pathinfo($item['name'], PATHINFO_FILENAME);

        $file = Resource\File\Mapper::getInstance()->createModel();
        $file->setId($item['id']);
        $file->setBasename($item['name']);
        $file->setTitle($filename);

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
     * @throws InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
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
     * @throws AuthenticationTimedOut
     * @throws RuntimeException
     * @throws Unauthorized
     */
    protected function rq($path)
    {
        $ch = Proxy\Server::getInstance()->curlInit($this->getTenant()->getBaseUri().$path);

        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => [ 'Authorization: Bearer ' . $this->oAuthToken ],
            CURLOPT_RETURNTRANSFER => 1,
        ]);

        $rs = curl_exec($ch);
        $error = curl_error($ch);
        $data = json_decode($rs, true);

        if (empty($data)) {
            // timed out
            throw new AuthenticationTimedOut('Connecting to Office 365 timed out, please try again later: ' . $error);
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
     * @throws AuthenticationTimedOut
     * @throws RuntimeException
     * @throws Unauthorized
     */
    public function fetchMetaData($nodeId)
    {
        return $this->rq("/files/$nodeId");
    }


    /**
     * Attempts to retrieve remote source to local destination, returns the path of the saved file.
     *
     * @param int $fileId
     * @return string
     * @throws AuthenticationTimedOut
     * @throws OutOfBoundsException
     * @throws RuntimeException
     * @throws Unauthorized
     */
    public function fetchToFile($fileId)
    {
        $file = $this->fetchMetaData($fileId);

        // "URL for downloading the file's content."
        $url = $file['contentUrl'];

        if (!$url) {
            throw new OutOfBoundsException("No url found for download");
        }

        $destination = sb_tempnam('365');

        if (!($fp = fopen($destination, 'w'))) {
            throw new OutOfBoundsException("Could not open target");
        }

        $ch = Proxy\Server::getInstance()->curlInit($url);

        /* TODO: Alaress Curl Url exists; or, replace with guzzle
         */
        curl_setopt_array($ch, [
            CURLOPT_FILE       => $fp,
            CURLOPT_HTTPHEADER => [ 'Authorization: Bearer ' . $this->oAuthToken ],
        ]);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        return $destination;
    }
}
