<?php
class Sabre_DAV_Client_Extended extends Sabre_DAV_Client {

	/**
	 * Overridden to implement Content-Length header in $this->request call
	 *
	 * @param $url
	 * @param $settings
	 *
	 * @return array
	 */
	protected function curlRequest($url, $settings) {
		if ($settings[CURLOPT_CUSTOMREQUEST] == 'PROPFIND') {
			$settings[CURLOPT_HTTPHEADER][] = 'Content-Length: 0';
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
	 * Performs an actual HTTP request, and returns the result.
	 *
	 * If the specified url is relative, it will be expanded based on the base
	 * url.
	 *
	 * The returned array contains 3 keys:
	 *   * body - the response body
	 *   * httpCode - a HTTP code (200, 404, etc)
	 *   * headers - a list of response http headers. The header names have
	 *     been lowercased.
	 *
	 *
	 * Note that this method is overridden due authentication methods being
	 * incorrectly set.
	 *
	 * This is a bug in the SabreDAV client. It was using CURLAUTH_BASIC and
	 * CURLAUTH_DIGEST as some kind of bitmask, eventually assigning
	 * CURLOPT_HTTPAUTH to integer 3, a nonexistent authentication method.
	 *
	 * Fixed on 14/5/2012, hardcoded to CURLAUTH_BASIC
	 *
	 * @param string $method
	 * @param string $url
	 * @param string $body
	 * @param array $headers
	 * @return array
	 */
	public function request($method, $url = '', $body = null, $headers = array()) {

		$url = $this->getAbsoluteUrl($url);

		$curlSettings = array(
			CURLOPT_RETURNTRANSFER => true,
			// Return headers as part of the response
			CURLOPT_HEADER => true,
			CURLOPT_POSTFIELDS => $body,
			// Automatically follow redirects
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 5,
		 );

		switch ($method) {
			case 'PUT':
				$curlSettings[CURLOPT_PUT] = true;
				break;
			case 'HEAD' :

				// do not read body with HEAD requests (this is neccessary because cURL does not ignore the body with HEAD
				// requests when the Content-Length header is given - which in turn is perfectly valid according to HTTP
				// specs...) cURL does unfortunately return an error in this case ("transfer closed transfer closed with
				// ... bytes remaining to read") this can be circumvented by explicitly telling cURL to ignore the
				// response body
				$curlSettings[CURLOPT_NOBODY] = true;
				$curlSettings[CURLOPT_CUSTOMREQUEST] = 'HEAD';
				break;

			default:
				$curlSettings[CURLOPT_CUSTOMREQUEST] = $method;
				break;

		}

		// Adding HTTP headers
		$nHeaders = array();

		foreach ($headers as $key => $value) {
			$nHeaders[] = $key . ': ' . $value;
		}

		$curlSettings[CURLOPT_HTTPHEADER] = $nHeaders;

		if ($this->proxy) {
			$curlSettings[CURLOPT_PROXY] = $this->proxy;
		}

		if ($this->userName) {

			/* Alaress
			 * This is a bug in the SabreDAV client. It was using CURLAUTH_BASIC and
			 * CURLAUTH_DIGEST as some kind of bitmask, eventually assigning
			 * CURLOPT_HTTPAUTH to integer 3, a nonexistent authentication method.
			 *
			 * Fixed on 14/5/2012, hardcoded to CURLAUTH_BASIC
			 */
			$curlSettings[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
			$curlSettings[CURLOPT_USERPWD] = $this->userName . ':' . $this->password;
		}

		list(
			$response,
			$curlInfo,
			$curlErrNo,
			$curlError
		) = $this->curlRequest($url, $curlSettings);

		$headerBlob = substr($response, 0, $curlInfo['header_size']);
		$response = substr($response, $curlInfo['header_size']);

		// In the case of 100 Continue, or redirects we'll have multiple lists
		// of headers for each separate HTTP response. We can easily split this
		// because they are separated by \r\n\r\n
		$headerBlob = explode("\r\n\r\n", trim($headerBlob, "\r\n"));

		// We only care about the last set of headers
		$headerBlob = $headerBlob[count($headerBlob) - 1];

		// Splitting headers
		$headerBlob = explode("\r\n", $headerBlob);

		$headers = array();

		foreach ($headerBlob as $header) {
			$parts = explode(':', $header, 2);
			if (count($parts) == 2) {
				$headers[strtolower(trim($parts[0]))] = trim($parts[1]);
			}
		}

		$response = array(
			'body' => $response,
			'statusCode' => $curlInfo['http_code'],
			'headers' => $headers
		);

		if ($curlErrNo) {
			throw new Sabre_DAV_Exception('[CURL] Error while making request: ' . $curlError . ' (error code: ' . $curlErrNo . ')');
		}

		if ($response['statusCode'] >= 400) {
			switch ($response['statusCode']) {
				case 404:
					throw new Sabre_DAV_Exception_NotFound('Resource ' . $url . ' not found.');
					break;

				default:
					throw new Sabre_DAV_Exception('HTTP error response. (errorcode ' . $response['statusCode'] . ')');
			}
		}

		return $response;
	}

	/**
     * Uploads a file via a PUT request
     *
     * @param string $path Destination path on the server
     * @param string $file Path to source file
     *
     * @return array
     */
    public function put($path, $file) {

        $curl = curl_init($this->getAbsoluteUrl($path));

        curl_setopt_array($curl, array(
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->userName . ':' . $this->password,
            CURLOPT_PUT => true,
            CURLOPT_INFILE => fopen($file, "r"),
            CURLOPT_INFILESIZE => filesize($file),
        ));

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
    public function move($path, $destination) {
        return $this->request('MOVE', $path, null, array(
            'Destination' => $this->getAbsoluteUrl($destination))
        );
    }
}

class PortfolioWebDAV {

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
    public function __construct(Sabre_DAV_Client_Extended $client, array $paths = array('/')) {
        $this->client = $client;

        $this->rootPaths = $paths;

        $this->buildTree();
    }

    /**
     * Initialises the cache using the rootPaths
     */
    private function buildTree() {

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

            $basename = $path == '/' ? '/' : urldecode(basename($path));

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

    public function factory() {
        require_once SITE_root . '/library/group.php';
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
                    '/StudentHome/' . (12 - $_SESSION['year'] + 11) . '/' . $_SESSION['username'] . '/',
                    '/StudentData/',
                );
            }
        }
        else if ($site_location == 'jpc') {
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
        }
        else if ($site_location == 'cathedral') {

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
                }
                else {

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

        }
        else if ($site_location == 'bialik') {

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
                }
                else {
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
        }
		else if ($site_location == 'core') {
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
    private function fixPath($path) {

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

    public function getList($path = '', $filter = 'dir') {

        $md5Path = md5($path);

        if (isset($this->cache[$md5Path][$filter])) {
            return $this->cache[$md5Path][$filter];
        }

        $this->cache[$md5Path]['dir'] = array();
        $this->cache[$md5Path]['file'] = array();

		try{
			$response = $this->client->propFind($path, array(
				'{DAV:}getcontentlength',
				'{DAV:}resourcetype',
				'{DAV:}getlastmodified',
				'{DAV:}creationdate',),
				1);
		}
		/* Handle Sabre_DAV_Exception specifically
		 */
		catch (Sabre_DAV_Exception $e) {
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
                'name' => urldecode($filename),
                'description' => urldecode($filename),
                'type' => $type,
                'file_size' => $info['{DAV:}getcontentlength'],
                'submitted' => $info['{DAV:}getlastmodified'],
                'ctime' => $info['{DAV:}creationdate'],
            );

            $this->cache[$md5Path][$type][] = $temp;

        }

        return $this->cache[$md5Path][$filter];
    }

    public function getDirectoryList($path) {
        return $this->getList($path, 'dir');
    }

    public function getFileList($path) {
        return $this->getList($path, 'file');
    }

    /* myfolder interface */

    public function getParent($id) {

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

    public function getFolderNode($id) {

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
        }
        else {
            return $directoryList;
        }
    }


    public function hasChildren($id) {

        $childrenList = $this->getDirectoryList($_SESSION['portfolioIdPathMap'][$id]);

        return is_array($childrenList) && count($childrenList);
    }

    public function getFolderList($id) {
        return $this->getDirectoryList($_SESSION['portfolioIdPathMap'][$id]);
    }

    /* MYFILE INTERFACE */

    public function getAllInFolder($id) {

        return $this->getFileList($_SESSION['portfolioIdPathMap'][$id]);
    }

    public function getFile($id) {

        $response = $this->client->request('GET', $_SESSION['portfolioIdPathMap'][$id]);

        $temp = tempnam(sys_get_temp_dir(), uniqid());
        file_put_contents($temp, $response['body']);
        return $temp;
    }

    public function getByID($id) {

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

    public function isRoot($id) {
        return in_array($_SESSION['portfolioIdPathMap'][$id], $this->rootPaths);
    }

    public function create($data, $file = false) {

        if ($data['parent'] == 0) {
            return false;
        }

        if ($file) {
            $path = $_SESSION['portfolioIdPathMap'][$data['parent']] . urlencode($file['name']);
            $this->client->put($path, $file['tmp_name']);
        } else {
            $path = $_SESSION['portfolioIdPathMap'][$data['parent']] . urlencode($data['name']) . '/';
            $this->client->request('MKCOL', $path);
        }

        $_SESSION['portfolioIdPathMap'][] = $path;

    }

    public function update($data) {

        if (isset($data['name'])) {
            $data['description'] = $data['name'];
        }

        if (isset($data['description'])) {
            $destination = dirname($_SESSION['portfolioIdPathMap'][$data['id']]) . urlencode($data['description']);
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

    public function delete($id) {

        if ($this->isRoot($id) || $id == 0) {
            return false;
        }

        $this->client->request('DELETE', $_SESSION['portfolioIdPathMap'][$id]);

        unset($_SESSION['portfolioIdPathMap'][$id]);
    }

    protected function getIndex($path) {
        $path = rtrim($path, '/') . '/';
        return array_search($path, $_SESSION['portfolioIdPathMap']);
    }

    protected function getPath($index) {
        return $_SESSION['portfolioIdPathMap'][$index];
    }

}

