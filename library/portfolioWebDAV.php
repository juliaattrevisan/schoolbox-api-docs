<?php


set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/../include/PEAR');
require_once 'HTTP/WebDAV/Client.php';


/**
 * Rubbish library is inadequate and requires modifications
 */
class HTTP_WebDAV_Client_Stream_Extended extends HTTP_WebDAV_Client_Stream {
	
	/**
	 * Overwrite to request all property information on files
	 * and to remove basename() filter
	 */
    function dir_opendir($path, $options)
    {
		
        // rewrite the request URL
        if (!$this->_parse_url($path)) return false;

        // query server for WebDAV options
        if (!$this->_check_options())  return false;

        if (!isset($this->dav_allow[HTTP_REQUEST_METHOD_PROPFIND])) {
            return false;
        }

        // now read the directory
        $req = &new HTTP_Request($this->url);
        $req->setMethod(HTTP_REQUEST_METHOD_PROPFIND);
        if (is_string($this->user)) {
            $req->setBasicAuth($this->user, @$this->pass);
        }
        $req->addHeader("Depth", "1");
        $req->addHeader("Content-Type", "text/xml");
		
		// empty prop request means return all properties
        $req->addRawPostData('<?xml version="1.0" encoding="utf-8"?>
<propfind xmlns="DAV:">
 <prop>

 </prop>
</propfind>
');
        $req->sendRequest();


        switch ($req->getResponseCode()) {
        case 207: // multistatus content
            $this->dirfiles = array();
            $this->dirpos = 0;

            // for all returned resource entries
            foreach (split("\n", $req->getResponseBody()) as $line) {
                // get the href URL
                if (ereg("href>([^<]*)", $line, $matches)) {
                    // skip the directory itself
                    if ($matches[1] == $this->path) {
                        continue;
                    }

                    // remember the path, return them later with readdir()
                    $this->dirfiles[] = $matches[1]; // basename($matches[1]);
				
                }
            }
            return true;

        default:
            // any other response state indicates an error
            error_log("file not found");
            return false;
        }
    }

    /**
     * Helper function for WebDAV OPTIONS detection
     *
     * @access private
     * @return bool    true on success else false
     */
    function _check_options() 
    {
        // now check OPTIONS reply for WebDAV response headers
        $req = &new HTTP_Request($this->url);
        $req->setMethod(HTTP_REQUEST_METHOD_OPTIONS);
        if (is_string($this->user)) {
            $req->setBasicAuth($this->user, @$this->pass);          
        }
        $req->sendRequest();
		if ($req->getResponseCode() != 200) {
            print $req->getResponseBody();
			die();
			
			return false;
        }

        // get the supported DAV levels and extensions
        $dav = $req->getResponseHeader("DAV");
        $this->dav_level = array();
        foreach (explode(",", $dav) as $level) {
            $this->dav_level[trim($level)] = true;
        }
        if (!isset($this->dav_level["1"])) {
            // we need at least DAV Level 1 conformance
            return false;
        }
        
        // get the supported HTTP methods
        // TODO these are not checked for WebDAV compliance yet
        $allow = $req->getResponseHeader("Allow");
        $this->dav_allow = array();
        foreach (explode(",", $allow) as $method) {
            $this->dav_allow[trim($method)] = true;
        }

        // TODO check for required WebDAV methods

        return true;
    }
	
}

class PortfolioWebDAV {

	protected $client;

	protected $scheme;
	
	protected $host;
	
	protected $cache;
	
	protected $root;
	
	public function __construct ($url, $username, $password) {
		
		$url = str_replace(array('http://', 'https://'), array('webdav://', 'webdavs://'), $url);
				
		$url = parse_url($url);
		
		$this->scheme = $url['scheme'];
		$this->host = $url['host'];
		$this->root = '/' . trim($url['path'], '/') . '/';
		
		
		$this->client = new HTTP_WebDAV_Client_Stream_Extended;
		$this->client->user = $username;
		$this->client->pass = $password;
		
		if (!isset($_SESSION['WebDAVIDCache']))
			$_SESSION['WebDAVIDCache'] = array(0 => $this->root);
		
	}
	
	public function factory() {
		require_once SITE_root . '/library/group.php';
		$groupHandler = new Group;
		
		$directory = $groupHandler->isStaff() ? 'StaffHome/' : 'StudentHome/' . $_SESSION['year'] . '/' . $_SESSION['username'];
		return new self(WEBDAV_url . '/' . $directory , "wgs/" . $_SESSION['username'], $_SESSION['password']);
	}
	
	private function fixPath ($path) {
		
		$path = preg_replace("#[\\:*\?\"<>|]#", "", $path);
		if ($this->root != substr($path, 0, strlen($this->root)))
			return $this->root;
		
		return '/' . trim($path, '/');
	}
	
	public function getList($path = '/', $filter = 'all') {
		
		$path = rtrim ($path, '/') . '/';
	
		$md5Path = md5($path);
		
		if (isset($this->cache[$md5Path][$filter]))
			return $this->cache[$md5Path][$filter];
		
		$this->client->dir_opendir($this->scheme . '://' . $this->host . $path , array());
		
		$temp = array_flip($_SESSION['WebDAVIDCache']);
		$parentID = $temp[$path];
		
		while ($filepath = $this->client->dir_readdir()) {
			
			$filename = basename($filepath);
			
			$this->client->stream_open($this->scheme . '://' . $this->host . $filepath, 'r', array(), $eh);
			$stat =  $this->client->stream_stat();
			
			$type = $stat['mode'] & 040000 ? 'dir' : 'file';
			
			$md5ID = md5($filepath);
			
			$index = null;
			
			if (!in_array($filepath, $_SESSION['WebDAVIDCache'])) {
				
				$_SESSION['WebDAVIDCache'][] = $filepath;
				end($_SESSION['WebDAVIDCache']);
				$index = key($_SESSION['WebDAVIDCache']);
				
			} else {
				$temp = array_flip($_SESSION['WebDAVIDCache']);	
				$index = $temp[$filepath];
			}
			
			
			//var_dump($this->scheme . '://' . $this->host . $path . '/' . $filename, $stat);
			$temp = array('id'			=> $index,
						  'parent'		=> $parentID,
						  'path'		=> $filepath,
						  'name' 		=> $filename, 
						  'description'	=> $filename, 
						  'type'		=> $stat['mode'] & 040000 ? 'dir' : 'file',
						  'mode'		=> $stat['mode'],
						  'file_size'	=> $stat['size'],
						  'atime'		=> $stat['atime'],
						  'submitted'	=> $stat['mtime'],
						  'ctime'		=> $stat['ctime'],
						  );
			
			$this->cache[$md5Path]['all'][] = $temp;
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
	
	public function getParent($path) {
		
		return dirname($path);
	}
	
	public function getFolderNode($path = '/') {
		
		$path = $this->fixPath($path);
		
		// get folder contents
		$directoryList = $this->getDirectoryList($path);
		
		$structure = explode('/', trim($path, '/'));
	
		$parent = $this->getParent($path);

		
		if (strlen($parent) > strlen($this->root)) {
			$siblingList = $this->getFolderNode($parent);
			// get parent folder contents
			if (is_array($directoryList)) {
				// find index of parent node in siblingList
				$total = count($siblingList);
				for ($i = 0; $i < $total; $i++)
					if ($siblingList[$i]['path'] == $path) break;
					
				// insert folder contents next to parent node
				array_splice($siblingList, $i+1, 0, $directoryList);
			}
			return $siblingList;
		}
		else {
			return $directoryList;
		}
	}
	
	
	public function hasChildren ($id) {
		
		$childrenList = $this->getDirectoryList($_SESSION['WebDAVIDCache'][$id]);
		
		return is_array($childrenList) && count($childrenList);
	}
	
	public function getFolderList ($id) {
		return $this->getDirectoryList($_SESSION['WebDAVIDCache'][$id]);
	}
	
	/* MYFILE INTERFACE */
	
	public function getAllInFolder ($id) {
		
		return $this->getFileList($_SESSION['WebDAVIDCache'][$id]);
	}
	
	public function getFile($id) {
		
		$this->client->stream_open($this->scheme . '://' . $this->host . $_SESSION['WebDAVIDCache'][$id], 'r', array(), $a);
		
		
		while ($data = $this->client->stream_read(1024)) {
			$buffer .= $data;	
		}
		
		
		$temp = tempnam(sys_get_temp_dir(), uniqid());
		file_put_contents($temp, $buffer);
		return $temp;
	}
	
	public function getByID ($id) {
		$this->client->stream_open($this->scheme . '://' . $this->host . $_SESSION['WebDAVIDCache'][$id], 'r', array(), $a);
		$stat =  $this->client->stream_stat();
		
		$temp = array_flip($_SESSION['WebDAVIDCache']);
		$parentID = $temp[$this->getParent($_SESSION['WebDAVIDCache'][$id])];
		
		$filename = basename($_SESSION['WebDAVIDCache'][$id]);
		
		$temp = array('id'			=> $id,
					  'parent'		=> $parentID,
					  'path'		=> $_SESSION['WebDAVIDCache'][$id],
					  'name' 		=> $filename, 
					  'description'	=> $filename, 
					  'type'		=> $stat['mode'] & 040000 ? 'dir' : 'file',
					  'mode'		=> $stat['mode'],
					  'file_size'	=> $stat['size'],
					  'atime'		=> $stat['atime'],
					  'submitted'	=> $stat['mtime'],
					  'ctime'		=> $stat['ctime'],
					  'owner'		=> $_SESSION['id'],
					  );
		
		return $temp;
	}
	
	public function create ($data, $file = false) {
		
		if ($file) {
			$this->client->stream_open($this->scheme . '://' . $this->host . $_SESSION['WebDAVIDCache'][$data['parent']] . '/' . $file['name'], 'w', array(), $a);
			$length = 1024;
			$offset = 0;
			while ($this->client->stream_write(file_get_contents($file['tmp_name'], 0, null, $offset, $length))) {
			  $offset += $length;
			}
		} else {
			
			$this->client->mkdir($this->scheme . '://' . $this->host . $_SESSION['WebDAVIDCache'][$data['parent']] .  $data['name'] . '/');
		}
		
	}
	
	public function update($data) {
		
		if (isset($data['name']))
			$data['description'] = $data['name'];
			
		if (isset($data['description'])) {
			$path = dirname($_SESSION['WebDAVIDCache'][$data['id']]);
			$path = rtrim($path, '/') . '/';
			$this->client->rename($this->scheme . '://' . $this->host . $_SESSION['WebDAVIDCache'][$data['id']], $this->scheme . '://' . $this->host . $path . $data['description']);
		
		} else {
			$destination = '/' . trim($_SESSION['WebDAVIDCache'][$data['parent']], '/') . '/' . basename($_SESSION['WebDAVIDCache'][$data['id']]);
			$this->client->rename($this->scheme . '://' . $this->host . $_SESSION['WebDAVIDCache'][$data['id']], $this->scheme . '://' . $this->host . $destination);
		}
		
		
		unset($_SESSION['WebDAVIDCache'][$data['id']]);
	}
	
	public function delete($id) {
		if (!$this->client->unlink($this->scheme . '://' . $this->host . $_SESSION['WebDAVIDCache'][$id]))
			$this->client->rmdir($this->scheme . '://' . $this->host . $_SESSION['WebDAVIDCache'][$id]);
		
		unset($_SESSION['WebDAVIDCache'][$id]);
	}
}

?>