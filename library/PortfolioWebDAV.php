<?php

/**
 * WebDAV configuration
 *
 * @author    Everybody <everybody@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\File\Remote\FetcherInterface;
use Sabre\HTTP\ClientHttpException;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\Resource\File\IFile as FileInterface;
use Schoolbox\Resource\File\ILocator as FileLocatorInterface;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\Folder\IFolder as FolderInterface;
use Schoolbox\Resource\Folder\ILocator as FolderLocatorInterface;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Storage\Model as StorageModel;
use Schoolbox\User;
use Schoolbox\WebDav\Mapper as WebDavMapper;
use Schoolbox\WebDav\Model as WebDav;

class PortfolioWebDAV implements FileLocatorInterface, FolderLocatorInterface, FetcherInterface
{
    /** @var DAV_ExtendedClient */
    private $client;

    /** @var array Stores collection results so files and directories can be returned separately */
    private $cache;

    /** @var array Stores top level paths from the baseUri to make accessible */
    private $rootPaths;

    /** @var bool Whether this instance will redirect on an authentication fail, or throw an exception */
    private $lite;

    /** @var FolderMapper */
    protected $folderMapper;

    /** @var FileMapper */
    protected $fileMapper;

    /** @var MimetypeMapper */
    protected $mimeMapper;

    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * @param DAV_ExtendedClient $client
     * @param array $paths List of paths from the baseUri to access
     * @param bool $lite
     */
    public function __construct(DAV_ExtendedClient $client, array $paths = ['/'], $lite = false)
    {
        $this->client = $client;
        $this->rootPaths = $paths;
        $this->lite = $lite;
        $this->folderMapper = FolderMapper::getInstance();
        $this->fileMapper = FileMapper::getInstance();
        $this->mimeMapper = MimetypeMapper::getInstance();
        $this->errorMapper = ErrorMapper::getInstance();

        $this->buildTree();
    }

    /**
     * Initialises the cache using the rootPaths
     */
    private function buildTree()
    {
        // give pseudo id numbers to WebDAV files for use between requests
        // array(
        //    <id> => '<path>'
        // )

        $rootPath = md5('');
        $_SESSION['portfolioIdPathMap'][0] = '';

        $i = 0;
        foreach ($this->rootPaths as $path) {
            $i++;
            $_SESSION['portfolioIdPathMap'][$i] = $path;
            $basename = $path == '/' ? '/' : rawurldecode(basename($path));
            $cache = array(
                'id' => $i,
                'parent' => 0,
                'path' => $path,
                'name' => $basename,
                'description' => $basename,
                'type' => 'dir',
            );

            $this->cache[$rootPath]['dir'][] = $cache;
        }

        $this->cache[$rootPath]['file'] = array();
    }

    /** @var self $instance */
    private static $instance = null;


    /**
     * @param bool $lite
     * @return PortfolioWebDAV
     * @throws DomainException if no legacy config exists
     * @throws UnexpectedValueException if no user is logged in
     * @throws InvalidArgumentException If code attempts to create a DAV client with invalid HTTP method
     */
    public static function factory($lite = false)
    {
        // For some reason this gets called at least three times in my testing, so I'm just shortcutting it by caching the output

        if (self::$instance instanceof PortfolioWebDAV) {
            return self::$instance;
        }

        $paths = [];
        $settings = null;

        $siteSchoolCode = Config::get('site_school_code');

        /** @var Collection $davs */
        $davs = WebDavMapper::getInstance()->findByColumn('enabled', 1);

        // TODO inject user and password as parameter
        $user = $_SESSION["user"];
        if (!$user instanceof User) {
            // no user; abort
            throw new UnexpectedValueException("Anonymous users may not access drives");
        }

        /** @var WebDav $dav */
        foreach ($davs as $dav) {
            if ($dav->matches($user)) {
                // Only one connection can be created.
                // (Multiple paths within the same connection may be created, though.)
                if (!isset($settings)) {
                    $settings = [
                        'baseUri' => $dav->getUri(),
                        'userName' => $dav->getUsernamePrefix() . $_SESSION['username'] . $dav->getUsernameSuffix(),
                        'password' => $_SESSION['password'],
                    ];
                }

                // --------------------------------------------------------------------------------------------------------
                // Replaceable tokens
                // --------------------------------------------------------------------------------------------------------
                // {:yearlevel}        - Student year level
                // {:calendaryear}     - Current calendar year
                // {:graduationyear7}  - Expected graduation year (7 school years) - 4 digit year
                // {:graduationyear12} - Expected graduation year (12 school years) - 4 digit year
                // {:graduationyeartwodigit7}  - Expected graduation year (7 school years) - 2 digit year
                // {:graduationyeartwodigit12} - Expected graduation year (12 school years) - 2 digit year
                // {:startyear7}       - Student start year (7 school years)
                // {:startyear12}      - Student start year (7 school years)
                // {:username}         - Username
                // --------------------------------------------------------------------------------------------------------

                $userYear = $user->getYear() ? $user->getYear()->getName() : '';

                $path = $dav->getPath();
                $path = str_replace("{:username}", $user->getUsername(), $path);
                $path = str_replace("{:yearlevel}", $userYear, $path);
                $path = str_replace("{:calendaryear}", (int) date("Y"), $path);
                if (is_numeric($userYear)) {
                    $path = str_replace("{:graduationyear7}", 7 - (int)$userYear + (int)date("Y"), $path);
                    $path = str_replace("{:graduationyear12}", 12 - (int)$userYear + (int)date("Y"), $path);
                    $path = str_replace("{:graduationyeartwodigit7}", 7 - (int)$userYear + (int)date("y"), $path);
                    $path = str_replace("{:graduationyeartwodigit12}", 12 - (int)$userYear + (int)date("y"), $path);
                    $path = str_replace("{:startyear7}", (int)date("Y") - ((int)$userYear - 7), $path);
                    $path = str_replace("{:startyear12}", (int)date("Y") - ((int)$userYear - 12), $path);
                } else {
                    $path = str_replace(["{:graduationyear7}", "{:graduationyear12}", "{:graduationyeartwodigit7}", "{:graduationyeartwodigit12}","{:startyear7}","{:startyear12}"], '', $path);
                }

                $paths[] = $path;
            }
        }

        if (empty($settings) || empty($paths)) {
            // the next invocation would actually cause a fatal error if $settings is undefined anyway,
            // so this is no worse (but is now catchable)
            throw new DomainException("Drives is not configured");
        }

        $client = new DAV_ExtendedClient($settings);

        self::$instance = new self($client, $paths, $lite);
        return self::$instance;
    }

    /**
     * @param string $path
     * @param string $filter
     * @return mixed
     * @throws SchoolboxException if the WebDAV client threw an exception other than 400, 401 or 404
     */
    public function getList($path = '', $filter = 'dir')
    {
        $md5Path = md5($path);

        if (isset($this->cache[$md5Path][$filter])) {
            return $this->cache[$md5Path][$filter];
        }

        $this->cache[$md5Path]['dir'] = [];
        $this->cache[$md5Path]['file'] = [];

        try {
            $response = $this->client->propFind(
                $path,
                [
                    '{DAV:}getcontentlength',
                    '{DAV:}resourcetype',
                    '{DAV:}getlastmodified',
                    '{DAV:}creationdate',
                ],
                1
            );
        } catch (ClientHttpException $e) {
            switch ($e->getResponse()->getStatus()) {
                case 401:
                    // TODO this should raise a redirect message, not just rudely redirect
                    SitePage::localRedir('/cms/networkLogin' . ($this->lite ? '/lite' : ''));
                    break;

                case 400:
                case 404:
                    // In some cases we are told that something exists, only to be thrown a 404 error
                    // Instead of reacting badly just return false and get on with life
                    return false;

                default:
                    // error out I guess
                    // This gets caught elsewhere but that isn't my problem
                    throw $this->errorMapper->generateException(
                        ErrorMapper::ERROR_WEBDAV,
                        $e,
                        $e->getMessage() . " at: " . $path
                    );
            }
        }

        // remove parent
        array_shift($response);

        $temp = array_flip($_SESSION['portfolioIdPathMap']);
        $parentID = $temp[$path];

        foreach ($response as $filepath => $info) {
            $filename = basename($filepath);

            $type = $this->parseResourceType($info['{DAV:}resourcetype']);

            $index = null;

            if (!in_array($filepath, $_SESSION['portfolioIdPathMap'])) {
                $_SESSION['portfolioIdPathMap'][] = $filepath;
                end($_SESSION['portfolioIdPathMap']);
                $index = key($_SESSION['portfolioIdPathMap']);
            } else {
                $temp = array_flip($_SESSION['portfolioIdPathMap']);
                $index = $temp[$filepath];
            }

            $temp = [
                'id' => $index,
                'parent' => $parentID,
                'path' => $filepath,
                'name' => rawurldecode($filename),
                'description' => rawurldecode($filename),
                'type' => $type,
                'file_size' => $info['{DAV:}getcontentlength'] ?? '',
                'submitted' => $info['{DAV:}getlastmodified'] ?? '',
                'ctime' => $info['{DAV:}creationdate'] ?? '',
            ];

            $this->cache[$md5Path][$type][] = $temp;
        }

        return $this->cache[$md5Path][$filter];
    }

    /**
     * @param string $path
     * @return mixed
     */
    public function getDirectoryList($path)
    {
        return $this->getList($path, 'dir');
    }

    /**
     * @param string $path
     * @return mixed
     */
    public function getFileList($path)
    {
        return $this->getList($path, 'file');
    }

    /* myfolder interface */

    /**
     * @param int $id
     * @return bool|mixed|string
     */
    public function getParent($id)
    {
        $path = $this->getPath($id);

        if ($path == '') {
            return false;
        }

        if (in_array($path, $this->rootPaths)) {
            return '';
        }

        $parentPath = dirname($path);

        return $this->getIndex($parentPath);
    }

    /**
     * @param int $id
     * @return mixed
     */
    public function getFolderNode($id)
    {
        $path = $this->getPath($id);

        // get folder contents
        $directoryList = $this->getDirectoryList($path);

        $parentId = $this->getParent($id);

        if ($id > 0 && $parentId !== false) {
            // get parent folder contents
            $siblingList = $this->getFolderNode($parentId);

            if (is_array($directoryList)) {
                // find index of parent node in siblingList
                $total = count($siblingList);
                for ($i = 0; $i < $total; $i++) {
                    if ($siblingList[$i]['id'] == $id) {
                        break;
                    }
                }

                // insert folder contents next to parent node
                array_splice($siblingList, $i + 1, 0, $directoryList);
            }

            return $siblingList;
        } else {
            return $directoryList;
        }
    }

    /**
     * @param int $id
     * @return bool
     */
    public function hasChildren($id)
    {
        $childrenList = $this->getDirectoryList($_SESSION['portfolioIdPathMap'][$id]);
        return is_array($childrenList) && count($childrenList);
    }

    /**
     * @param int $id
     * @return mixed
     */
    public function getFolderList($id)
    {
        return $this->getDirectoryList($_SESSION['portfolioIdPathMap'][$id] ?? '');
    }

    /* MYFILE INTERFACE */

    /**
     * @param int $id
     * @return mixed
     */
    public function getAllInFolder($id)
    {
        return $this->getFileList($_SESSION['portfolioIdPathMap'][$id] ?? '');
    }

    /**
     * @param int $id
     * @return string
     */
    public function getFile($id)
    {
        //
        // The file may be larger than the memory limit - workaround this by
        // requesting 10MB chunks at a time.
        $chunk_size = 10000000;
        $properties = $this->client->propFind($_SESSION['portfolioIdPathMap'][$id], ['{DAV:}getcontentlength']);
        $file_size = $properties['{DAV:}getcontentlength'];
        $temp = sb_tempnam('dav');
        if ($file_size <= $chunk_size) {
            $response = $this->client->request('GET', $_SESSION['portfolioIdPathMap'][$id]);
            file_put_contents($temp, $response['body'], LOCK_EX);
        } else {
            //
            // Request each chunk and append to the response
            for ($i = 0; $i < $file_size; $i += $chunk_size) {
                $start = $i;
                $end = min($i + $chunk_size, $file_size) - 1;
                $response = $this->client->request(
                    'GET',
                    $_SESSION['portfolioIdPathMap'][$id],
                    null,
                    ['Range' => 'bytes=' . $start . '-' . $end]
                );
                //
                // After all this, does the server not accept range requests?
                // Just dump the whole file out if it doesn't.
                if ($response['headers']['accept-ranges'] == 'none') {
                    file_put_contents($temp, $response['body'], LOCK_EX);
                    break;
                }
                file_put_contents($temp, $response['body'], LOCK_EX | FILE_APPEND);
                unset($response);
            }
        }
        return $temp;
    }

    /**
     * @param int $id
     * @return array|bool
     */
    public function getByID($id)
    {
        /* $id of zero is always the shadow root node, which resolves to the base URI
           This means if the user has no access to the root of the webdav server '/'
           it will fail with a 403 error, even if they can access all the defined shares.
           Instead send fake data for the "/" root node.
        */
        if ($id == "0") {
            $temp = [
                'id' => $id,
                'parent' => false,
                'path' => $_SESSION['portfolioIdPathMap'][$id],
                'name' => '',
                'description' => '',
                'type' => 'dir',
                'file_size' => '',
                'submitted' => '',
                'ctime' => '',
                'owner' => $_SESSION['id'],
            ];
            return $temp;
        }

        $url = $_SESSION['portfolioIdPathMap'][$id] ?? null;

        // Maybe for whatever reason this thing doesn't exist in the session?
        // Return false. As noted below on 404:
        // "Instead of reacting badly just return false and get on with life"
        if (!isset($url)) {
            return false;
        }
        try {
            $response = $this->client->propFind(
                $url,
                [
                    '{DAV:}getcontentlength',
                    '{DAV:}resourcetype',
                    '{DAV:}getlastmodified',
                    '{DAV:}creationdate',
                ],
                1
            );

            $response = array_shift($response);

            $parentID = $this->getParent($id);

            $filename = basename($url);

            $temp = [
                'id' => $id,
                'parent' => $parentID,
                'path' => $url,
                'name' => $filename,
                'description' => $filename,
                'type' => $this->parseResourceType($response['{DAV:}resourcetype']),
                'file_size' => $response['{DAV:}getcontentlength'] ?? '',
                'submitted' => $response['{DAV:}getlastmodified'] ?? '',
                'ctime' => $response['{DAV:}creationdate'] ?? '',
                'owner' => $_SESSION['id'],
            ];

            return $temp;
        } catch (Sabre\DAV\Exception\NotAuthenticated $e) {
            /* Handle Sabre_DAV_Exception specifically
             */
            SitePage::localRedir('/cms/networkLogin' . ($this->lite ? '/lite' : ''));
        } catch (Sabre\DAV\Exception\NotFound $e) {
            /* In some cases we are told that something exists, only to be thrown a 404 error
                Instead of reacting badly just return false and get on with life */
            return false;
        } catch (Sabre\DAV\Exception $e) {
            if ($e->getMessage() == "HTTP error: 401") {
                SitePage::localRedir('/cms/networkLogin' . ($this->lite ? '/lite' : ''));
            } else {
                if ($e->getMessage() == "HTTP error: 404" or $e->getMessage() == "HTTP error: 400") {
                    return false;
                } else {
                    error(141, $e->getMessage() . " at: " . $_SESSION['portfolioIdPathMap'][$id]);
                }
            }
        }

        return false;
    }

    /**
     * Parses the resource type - is it a dir or a file?
     *
     * @param  Sabre\DAV\Xml\Property\ResourceType $resourceType
     * @return string
     */
    protected function parseResourceType($resourceType)
    {
        if (!($resourceType instanceof Sabre\DAV\Xml\Property\ResourceType)) {
            return 'file';
        } else {
            return $resourceType->is('{DAV:}collection') ? 'dir' : 'file';
        }
    }

    /**
     * @param int $id
     * @return bool
     */
    public function isRoot($id)
    {
        return in_array($_SESSION['portfolioIdPathMap'][$id], $this->rootPaths);
    }

    /**
     * @param array $data
     * @param bool $file
     * @return bool
     */
    public function create($data, $file = false)
    {
        if ($data['parent'] == 0) {
            return false;
        }

        if ($file) {
            $path = $_SESSION['portfolioIdPathMap'][$data['parent']] . rawurlencode($file['name']);
            if ($file['resource']) {
                $this->client->put($path, $file['resource']);
            } else {
                $this->client->put($path, $file['tmp_name']);
            }
        } else {
            $path = $_SESSION['portfolioIdPathMap'][$data['parent']] . rawurlencode($data['name']) . '/';
            $this->client->request('MKCOL', $path);
        }

        return true;
    }

    /**
     * @param array $data
     * @return bool
     */
    public function update($data)
    {
        if (isset($data['name'])) {
            $data['description'] = $data['name'];
        }

        if (isset($data['description'])) {
            $destination = dirname($_SESSION['portfolioIdPathMap'][$data['id']]) . '/' . rawurlencode($data['description']);
            $this->client->move($_SESSION['portfolioIdPathMap'][$data['id']], $destination);

            $_SESSION['portfolioIdPathMap'][$data['id']] = $destination;
        } else {
            if ($this->isRoot($data['id']) || $data['parent'] == 0) {
                return false;
            }

            $source = $_SESSION['portfolioIdPathMap'][$data['id']];
            $destination = $_SESSION['portfolioIdPathMap'][$data['parent']] . basename($_SESSION['portfolioIdPathMap'][$data['id']]) . '/';
            $this->client->move($source, $destination);

            // iteratively update path cache
            foreach ($_SESSION['portfolioIdPathMap'] as $i => $path) {
                $_SESSION['portfolioIdPathMap'][$i] = str_replace($source, $destination, $_SESSION['portfolioIdPathMap'][$i]);
            }
        }

        return true;
    }

    /**
     * @param int $id
     * @return bool
     */
    public function delete($id)
    {
        if ($this->isRoot($id) || $id == 0) {
            return false;
        }

        $this->client->request('DELETE', $_SESSION['portfolioIdPathMap'][$id]);

        unset($_SESSION['portfolioIdPathMap'][$id]);
        return true;
    }

    /**
     * @param string $path
     * @return mixed
     */
    protected function getIndex($path)
    {
        $path = rtrim($path, '/') . '/';
        return array_search($path, $_SESSION['portfolioIdPathMap']);
    }

    /**
     * @param int $index
     * @return mixed
     */
    protected function getPath($index)
    {
        return $_SESSION['portfolioIdPathMap'][$index];
    }

    /**
     * Return the resource (file *OR* folder) with the given ID
     *
     * @param int $nodeId
     * @throws InvalidArgumentException
     * @return FileInterface|FolderInterface
     */
    public function findById($nodeId)
    {
        $data = $this->getByID($nodeId);

        switch ($data['type']) {
            case 'dir':
                return $this->parseFolder($data);
            case 'file':
                return $this->parseFile($data);
        }

        throw new InvalidArgumentException("Unable to parse DAV data");
    }

    /**
     * @param array $data
     * @return FolderInterface
     */
    protected function parseFolder($data)
    {
        $folder = $this->folderMapper->createModel();

        $parent = $this->folderMapper->createModel();

        // if no parent, cast to '#'
        $parent->setId($data['parent'] === false ? '#' : $data['parent']);

        $folder->setId($data['id']);
        $folder->setParent($parent);
        $folder->setName(urldecode($data['description'] ?: $data['name'] ?: $data['path']));

        return $folder;
    }

    /**
     * @param array $data
     * @return FileInterface
     */
    protected function parseFile($data)
    {
        $file = $this->fileMapper->createModel();

        $owner = new User();
        if (isset($data['owner'])) {
            $owner->setId($data['owner']);
        }

        $cat = $this->folderMapper->createModel();
        $cat->setId($data['parent']);

        $file->setId($data['id']);
        $file->setFolder($cat);
        $file->setBasename($data['name']);
        $file->setTitle($data['description']);
        $file->setOwner($owner);

        // Don't pretend the WebDAV file has an actual storage hash:
        // just construct what we can and set it in the file.
        $storage = (new StorageModel())
            ->setSize($data['file_size'])
            ->setBasename($data['name']);
        try {
            $storage = $storage->setMimetype($this->mimeMapper->getByFilename($data['name']));
        } catch (\Schoolbox\File\Exception $e) {
            $storage = $storage->setMimetype($this->mimeMapper->getUnknownMime());
        }
        $file->setStorage($storage);

        return $file;
    }

    /**
     * Return the files contained within the folder with the given ID
     *
     * @param int $nodeId
     * @return Collection|FileInterface[]
     */
    public function findByFolderId($nodeId)
    {
        $files = new Collection();
        $data = $this->getAllInFolder($nodeId);

        if (!is_array($data)) {
            return $files;
        }

        foreach ($data as $node) {
            $files->add($this->parseFile($node));
        }

        return $files;
    }

    /**
     * Return the folders contained within the folder with the given ID
     *
     * @param int $nodeId
     * @return Collection|FolderInterface[]
     */
    public function findByParentId($nodeId)
    {
        $folders = new Collection();
        $data = $this->getFolderList($nodeId);

        if (!is_array($data)) {
            return $folders;
        }

        foreach ($data as $node) {
            $folders->add($this->parseFolder($node));
        }

        return $folders;
    }

    /**
     * Retrieves the metadata for the given fileId
     *
     * @param int $fileId
     * @return array
     */
    public function fetchMetaData($fileId)
    {
        return $this->getByID($fileId);
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
        return $this->getFile($fileId);
    }
}
