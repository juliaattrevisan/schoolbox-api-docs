<?php
class Sabre_DAV_Client_Extended extends Sabre\DAV\Client
{

    /**
     * Overridden to implement Content-Length header in $this->request call
     *
     * @param $url
     * @param $settings
     *
     * @return array
     */
    protected function curlRequest($url, $settings)
    {
        if ($settings[CURLOPT_CUSTOMREQUEST] == 'PROPFIND') {
            $settings[CURLOPT_HTTPHEADER][] = 'Content-Length: 0';
        }

        $settings[CURLOPT_HTTPAUTH] = Cfg::get('webdav_allow_any_auth') ? CURLAUTH_ANY : CURLAUTH_BASIC;
        $settings[CURLOPT_COOKIEFILE] = '';

        if (Cfg::get('webdav_allow_insecure')) {
            $settings[CURLOPT_SSL_VERIFYHOST] = false;
            $settings[CURLOPT_SSL_VERIFYPEER] = false;
        }

        $curl = curl_init($url);

        curl_setopt_array($curl, $settings);

        return array(
            curl_exec($curl),
            curl_getinfo($curl),
            curl_errno($curl),
            curl_error($curl)
        );
    }

    /**
     * Uploads a file via a PUT request
     *
     * @param string $path Destination path on the server
     * @param string $file Path to source file
     *
     * @return array
     */
    public function put($path, $file)
    {

        $curl = curl_init($this->getAbsoluteUrl($path));

        $settings = array(
            CURLOPT_HTTPAUTH   => Cfg::get('webdav_allow_any_auth') ? CURLAUTH_ANY : CURLAUTH_BASIC,
            CURLOPT_USERPWD    => $this->userName . ':' . $this->password,
            CURLOPT_COOKIEFILE => '',
            CURLOPT_PUT        => true,
            CURLOPT_INFILE     => fopen($file, "r"),
            CURLOPT_INFILESIZE => filesize($file),
        );
        if (Cfg::get('webdav_allow_insecure')) {
            $settings[CURLOPT_SSL_VERIFYHOST] = false;
            $settings[CURLOPT_SSL_VERIFYPEER] = false;
        }
        curl_setopt_array($curl, $settings);

        return array(
            curl_exec($curl),
            curl_getinfo($curl),
            curl_errno($curl),
            curl_error($curl)
        );
    }

    /**
     * Moves/renames a file or directory
     *
     * @param string $path Source path
     * @param string $destination Destination path
     *
     * @return array
     */
    public function move($path, $destination)
    {
        return $this->request('MOVE', $path, null, array(
                'Destination' => $this->getAbsoluteUrl($destination))
        );
    }
}

class PortfolioWebDAV
{

    /**
     * @var Sabre_DAV_Client_Extended
     */
    private $client;

    /**
     * Stores collection results so files and directories
     * can be returned separately
     * @var array
     */
    private $cache;

    /**
     * Stores top level paths from the baseUri to make accessible
     * @var array
     */
    private $rootPaths;

    /**
     * @param \Sabre_DAV_Client_Extended $client
     * @param array $paths List of paths from the baseUri to access
     */
    public function __construct(Sabre_DAV_Client_Extended $client, array $paths = array('/'))
    {
        $this->client = $client;

        $this->rootPaths = $paths;

        $this->buildTree();
    }

    /**
     * Initialises the cache using the rootPaths
     */
    private function buildTree()
    {
        $rootPath = md5('');

        // give pseudo id numbers to WebDAV files for use between requests
        // array(
        //    <id> => '<path>'
        // )
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

        $this->cache[$rootPath]['file'][] = true;


    }

    public static function factory()
    {
        $groupHandler = new Group;

        $site_location = Cfg::get('site_location');
        if ($site_location == 'wenona') {
            $settings = array(
                'baseUri' => 'http://oxygen/',
                'userName' => "wgs/" . $_SESSION['username'],
                'password' => $_SESSION['password'],
            );

            if ($groupHandler->isStaff()) {
                $paths = array(
                    '/StaffHome/',
                    '/StaffData/',
                );
            } else {
                $settings['baseUri'] = 'http://mercury/';

                $paths = array(
                    '/StudentHome/' . (12 - $_SESSION['year'] + date('y')) . '/' . $_SESSION['username'] . '/',
                    '/StudentData/',
                );
            }
        } else if ($site_location == 'jpc') {
            $settings = array(
                'baseUri' => 'http://kazon/',
                'userName' => "jpc/" . $_SESSION['username'],
                'password' => $_SESSION['password'],
            );

            if ($groupHandler->isStaff()) {
                $paths = array(
                    '/Staff/' . $_SESSION['username'] . '/',
                    '/doclib/',
                );
            } else {
                $paths = array(
                    '/Students/' . (date('Y') - ($_SESSION['year'] - 7)) . '/' . $_SESSION['username'] . '/',
                );
            }
        } else if ($site_location == 'cathedral') {

            $settings = array(
                'baseUri' => 'http://senior-server.school.cathedral.qld.edu.au/',
                'userName' => "cathedral/" . $_SESSION['username'],
                'password' => $_SESSION['password'],
            );

            if ($groupHandler->isStaff()) {
                if ($_SESSION['campus'][0] == 4 || $_SESSION['campus'][0] == 5) {

                    $settings['baseUri'] = 'http://junior-server.school.cathedral.qld.edu.au/';

                    $paths = array(
                        '/staffhome/' . $_SESSION['username'] . '/',
                        '/ydrive/',
                    );
                } else {

                    $paths = array(
                        '/staffhome/' . $_SESSION['username'] . '/',
                        '/xdrive/',
                    );
                }

            } else {

                $paths = array(
                    '/studenthome/' . $_SESSION['username'] . '/',
                );

                if ($_SESSION['campus'][0] == 4 || $_SESSION['campus'][0] == 5) {
                    $settings['baseUri'] = 'http://junior-server.school.cathedral.qld.edu.au/';
                }
            }

        } else if ($site_location == 'bialik') {

            $settings = array(
                'baseUri' => 'https://webdav.bialik.vic.edu.au/',
                'userName' => "bialik/" . $_SESSION['username'],
                'password' => $_SESSION['password'],
            );

            if ($groupHandler->isStaff()) {
                if ($_SESSION['campus'][0] == 2) {

                    $paths = array(
                        '/H-DRIVE/USERS/STAFF/TEACHERS/ELC/' . $_SESSION['username'] . '/',
                    );
                } else {
                    $paths = array(
                        '/H-DRIVE/USERS/STAFF/TEACHERS/YEAR02-12/' . $_SESSION['username'] . '/',
                    );
                }

            } else if ($groupHandler->isStudent()) {
                $paths = array(
                    '/H-DRIVE/USERS/STUDENTS/YEAR%20' . str_pad($_SESSION['year'], 2, '0', STR_PAD_LEFT) . '/' . $_SESSION['username'] . '/',
                );
            } else {
                return false;
            }
        } else if ($site_location=='stmichaels') {
            $settings = array(
                'baseUri' => 'https://smgs-data.stmichaels.vic.edu.au/',
                'userName' => "smgs/" . $_SESSION['username'],
                'password' => $_SESSION['password'],
            );


            if ($groupHandler->isStaff()) {
                $paths = array(
                    '/staff/' . $_SESSION['username'] . '/',
                );
            } else if ($groupHandler->isStudent()) {
                $paths = array(
                    '/' . $_SESSION['username'] . '/',
                );
            } else {
                return false;
            }

        }
        else if ($site_location=='seymour') {
            $settings = array(
                'baseUri' => 'https://netstorage.seymour.sa.edu.au/',
                'userName' => $_SESSION['username'],
                'password' => $_SESSION['password'],
            );
            $paths = array('/oneNet/NetStorage/');
        }
        else if (in_array($site_location, array('core', 'demo'))) {
            $settings = array(
                'baseUri' => 'http://webdave.alaress-dev.com.au/',
                'userName' => 'webdave',
                'password' => 'e3IRS043UdNis',
            );

            $paths = array('/');
        }

        $client = new Sabre_DAV_Client_Extended($settings);

        return new self($client, $paths);
    }

    /**
     * @deprecated
     */
    private function fixPath($path)
    {

        if ($path == '') {
            return $path;
        }

        $path = preg_replace("#[\\:*\?\"<>|]#", "", $path);
        foreach ($this->rootPaths as $root) {
            if ($root == substr($path, 0, strlen($root))) {
                return '/' . trim($path, '/');
            }
        }

        return $this->rootPaths[0];
    }

    public function getList($path = '', $filter = 'dir')
    {

        $md5Path = md5($path);

        if (isset($this->cache[$md5Path][$filter])) {
            return $this->cache[$md5Path][$filter];
        }

        $this->cache[$md5Path]['dir'] = array();
        $this->cache[$md5Path]['file'] = array();

        try {
            $response = $this->client->propFind($path, array(
                    '{DAV:}getcontentlength',
                    '{DAV:}resourcetype',
                    '{DAV:}getlastmodified',
                    '{DAV:}creationdate',),
                1);
        } /* Handle Sabre_DAV_Exception specifically
         */
        catch (Sabre\DAV\Exception $e) {
            error(141, DAV_Error::getMeaning($e->getMessage()));
        }

        // remove parent
        array_shift($response);

        $temp = array_flip($_SESSION['portfolioIdPathMap']);
        $parentID = $temp[$path];

        foreach ($response as $filepath => $info) {

            $filename = basename($filepath);

            $type = $info['{DAV:}resourcetype']->is('{DAV:}collection') ? 'dir' : 'file';

            $index = null;

            if (!in_array($filepath, $_SESSION['portfolioIdPathMap'])) {

                $_SESSION['portfolioIdPathMap'][] = $filepath;
                end($_SESSION['portfolioIdPathMap']);
                $index = key($_SESSION['portfolioIdPathMap']);

            } else {
                $temp = array_flip($_SESSION['portfolioIdPathMap']);
                $index = $temp[$filepath];
            }

            $temp = array('id' => $index,
                'parent' => $parentID,
                'path' => $filepath,
                'name' => rawurldecode($filename),
                'description' => rawurldecode($filename),
                'type' => $type,
                'file_size' => $info['{DAV:}getcontentlength'],
                'submitted' => $info['{DAV:}getlastmodified'],
                'ctime' => $info['{DAV:}creationdate'],
            );

            $this->cache[$md5Path][$type][] = $temp;

        }

        return $this->cache[$md5Path][$filter];
    }

    public function getDirectoryList($path)
    {
        return $this->getList($path, 'dir');
    }

    public function getFileList($path)
    {
        return $this->getList($path, 'file');
    }

    /* myfolder interface */

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


    public function hasChildren($id)
    {

        $childrenList = $this->getDirectoryList($_SESSION['portfolioIdPathMap'][$id]);

        return is_array($childrenList) && count($childrenList);
    }

    public function getFolderList($id)
    {
        return $this->getDirectoryList($_SESSION['portfolioIdPathMap'][$id]);
    }

    /* MYFILE INTERFACE */

    public function getAllInFolder($id)
    {

        return $this->getFileList($_SESSION['portfolioIdPathMap'][$id]);
    }

    public function getFile($id)
    {
        //
        // The file may be larger than the memory limit - workaround this by
        // requesting 10MB chunks at a time.
        $chunk_size = 10000000;
        $properties = $this->client->propFind(
            $_SESSION['portfolioIdPathMap'][$id],
            array('{DAV:}getcontentlength',)
        );
        $file_size  = $properties['{DAV:}getcontentlength'];
        $temp       = tempnam(sys_get_temp_dir(), uniqid());
        if ($file_size <= $chunk_size) {
            $response = $this->client->request('GET', $_SESSION['portfolioIdPathMap'][$id]);
            file_put_contents($temp, $response['body'], LOCK_EX);
        }
        else {
            //
            // Request each chunk and append to the response
            for ($i = 0; $i < $file_size; $i += $chunk_size) {
                $start = $i;
                $end   = min($i+$chunk_size, $file_size) - 1;
                $response = $this->client->request(
                    'GET',
                    $_SESSION['portfolioIdPathMap'][$id],
                    null,
                    array('Range' => 'bytes='.$start.'-'.$end)
                );
                //
                // After all this, does the server not accept range requests?
                // Just dump the whole file out if it doesn't.
                if ($response['headers']['accept-ranges'] == 'none') {
                    file_put_contents($temp, $response['body'], LOCK_EX);
                    break;
                }
                file_put_contents($temp, $response['body'], LOCK_EX|FILE_APPEND);
                unset($response);
            }
        }
        return $temp;
    }

    public function getByID($id)
    {

        $response = $this->client->propFind($_SESSION['portfolioIdPathMap'][$id], array(
            '{DAV:}getcontentlength',
            '{DAV:}resourcetype',
            '{DAV:}getlastmodified',
            '{DAV:}creationdate',
        ), 1);

        $response = array_shift($response);

        $parentID = $this->getParent($id);

        $filename = basename($_SESSION['portfolioIdPathMap'][$id]);

        $temp = array('id' => $id,
            'parent' => $parentID,
            'path' => $_SESSION['portfolioIdPathMap'][$id],
            'name' => $filename,
            'description' => $filename,
            'type' => $type = $response['{DAV:}resourcetype']->is('{DAV:}collection') ? 'dir' : 'file',
            'file_size' => $response['{DAV:}getcontentlength'],
            'submitted' => $response['{DAV:}getlastmodified'],
            'ctime' => $response['{DAV:}creationdate'],
            'owner' => $_SESSION['id'],
        );

        return $temp;
    }

    public function isRoot($id)
    {
        return in_array($_SESSION['portfolioIdPathMap'][$id], $this->rootPaths);
    }

    public function create($data, $file = false)
    {

        if ($data['parent'] == 0) {
            return false;
        }

        if ($file) {
            $path = $_SESSION['portfolioIdPathMap'][$data['parent']] . rawurlencode($file['name']);
            $this->client->put($path, $file['tmp_name']);
        } else {
            $path = $_SESSION['portfolioIdPathMap'][$data['parent']] . rawurlencode($data['name']) . '/';
            $this->client->request('MKCOL', $path);
        }

        //$_SESSION['portfolioIdPathMap'][] = $path;

    }

    public function update($data)
    {

        if (isset($data['name'])) {
            $data['description'] = $data['name'];
        }

        if (isset($data['description'])) {
            $destination = dirname($_SESSION['portfolioIdPathMap'][$data['id']]) . rawurlencode($data['description']);
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

    }

    public function delete($id)
    {

        if ($this->isRoot($id) || $id == 0) {
            return false;
        }

        $this->client->request('DELETE', $_SESSION['portfolioIdPathMap'][$id]);

        unset($_SESSION['portfolioIdPathMap'][$id]);
    }

    protected function getIndex($path)
    {
        $path = rtrim($path, '/') . '/';
        return array_search($path, $_SESSION['portfolioIdPathMap']);
    }

    protected function getPath($index)
    {
        return $_SESSION['portfolioIdPathMap'][$index];
    }

}

