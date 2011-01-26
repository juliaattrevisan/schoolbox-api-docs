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
  <resourcetype/>
  <getcontentlength/>
  <getlastmodified />
  <creationdate/>
 </prop>
</propfind>
');
        $req->sendRequest();
		//print $req->getResponseBody();
		switch ($req->getResponseCode()) {
        case 207: // multistatus content
            $this->dirfiles = array();
            $this->dirpos = 0;

			if (preg_match_all('/href>([^<]+)/',  $req->getResponseBody(), $matches, PREG_SET_ORDER)) {
			
				// for all returned resource entries
				foreach ($matches as $match) {
					
					// get the href URL
					$url = parse_url($match[1]);
					
					// skip the directory itself
					if ($url['path'] == $this->path) {
						continue;
					}
					
					// remember the path, return them later with readdir()
					
					$this->dirfiles[] = str_replace(array('http://', 'https://'), array('webdav://', 'webdavs://'), $match[1]); // basename($matches[1]);
					
				}	
				
			}
			
            return true;

        default:
		
            // any other response state indicates an error
			preg_match('/h2>([^<]+)/', $req->getResponseBody(), $match);
			$this->errorMessage = $match[1];
            
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
			preg_match('/h2>([^<]+)/', $req->getResponseBody(), $match);
			$this->errorMessage = $match[1];
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

    /**
     * Stream wrapper interface write() method
     *
     * @access public
     * @param  string data to write
     * @return int    number of bytes actually written
     */
    function stream_write($buffer) 
    {
        // do some math
        $start = $this->position;
        $end   = $this->position + strlen($buffer);

        // create a partial PUT request
        $req = &new HTTP_Request($this->url);
        $req->setMethod(HTTP_REQUEST_METHOD_PUT);
        if (is_string($this->user)) {
            $req->setBasicAuth($this->user, @$this->pass);          
        }
        //$req->addHeader("Content-Range", "bytes $start-$end/*");
        if ($this->locktoken) {
            $req->addHeader("If", "(<{$this->locktoken}>)");
        }
        $req->addRawPostData($buffer);

        // go! go! go!
        $req->sendRequest();
		
        // check result
        switch ($req->getResponseCode()) {
        case 200:
        case 201:
        case 204:
            $this->position += strlen($buffer);
            return $end - $start;
            
        default: 
            return false;
        }

        /* 
         We do not cope with servers that do not support partial PUTs!
         And we do assume that a server does conform to the following 
         rule from RFC 2616 Section 9.6:

         "The recipient of the entity MUST NOT ignore any Content-* 
         (e.g. Content-Range) headers that it does not understand or 
         implement and MUST return a 501 (Not Implemented) response 
         in such cases."
           
         So the worst case scenario with a compliant server not 
         implementing partial PUTs should be a failed request. A 
         server simply ignoring "Content-Range" would replace 
         file contents with the request body instead of putting
         the data at the requested place but we can blame it 
         for not being compliant in this case ;)

         (TODO: maybe we should do a HTTP version check first?)
 
         we *could* emulate partial PUT support by adding local
         cacheing but for now we don't want to as it adds a lot
         of complexity and storage overhead to the client ...
        */
    }
	
}

class PortfolioWebDAV {

	protected $client;

	protected $scheme;
	
	protected $host;
	
	protected $cache;
	
	protected $root;
	
	public $errorMessage;
	
	public function __construct ($pathList, $username, $password) {
		
		$md5Path = md5('');
		
		$_SESSION['WebDAVIDCache'][0] = '';
		$i = 0;
		
		foreach ($pathList as $path) {
			$path = str_replace(array('http://', 'https://'), array('webdav://', 'webdavs://'), $path);
						
			$path = parse_url($path);
			
			$temp = $path['scheme'] . '://' . $path['host'] . '/' . trim($path['path'], '/') . '/';
			$this->rootNodes[] = $temp;
			
			$i++;
			
			$_SESSION['WebDAVIDCache'][$i] = $temp;

			$filename = basename($temp);

			$cache = array('id'			=> $i,
						  'parent'		=> 0,
						  'path'		=> $temp,
						  'name' 		=> urldecode($filename), 
						  'description'	=> urldecode($filename), 
						  'type'		=> 'dir',
						  );
			
			$this->cache[$md5Path]['all'][] = $cache;
			$this->cache[$md5Path]['dir'][] = $cache;	
			
		}
		
		$this->cache[$md5Path]['file'][] = true;
		
		
		$this->client = new HTTP_WebDAV_Client_Stream_Extended;
		$this->client->user = $username;
		$this->client->pass = $password;
		
	}
	
	public function factory() {
		require_once SITE_root . '/library/group.php';
		$groupHandler = new Group;

                if(SITE_location == 'wenona') {
                    if ($groupHandler->isStaff()) {
                            $pathList = array( 'http://gold/StaffHome/',
                                                               'http://gold/StaffData/',
                                                              );
                    } else {
                            $pathList = array( 'http://silver/StudentHome/' . (11 - $_SESSION['year'] + 11 ) . '/' . $_SESSION['username'] . '/',
                                                               'http://silver/StudentData/',
                                                              );
                    }
                    return new self($pathList , "wgs/" . $_SESSION['username'], $_SESSION['password']);
                }
                 else if(SITE_location == 'jpc') {
                   if ($groupHandler->isStaff()) {
                            $pathList = array( 'http://kazon/Staff/'.$_SESSION['username'].'/',
                                                               'http://kazon/doclib/',
                                                              );
                    } else {
                            $pathList = array( 'http://kazon/Student/' . (date('Y') - ($_SESSION['year'] - 7) ) . '/' . $_SESSION['username'] . '/',
                                                              );
                    }
                    return new self($pathList , "jpc/".$_SESSION['username'], $_SESSION['password']);
                 }
		
		
	}
	
	private function fixPath ($path) {
		
		if ($path == '')
			return $path;
		
		$path = preg_replace("#[\\:*\?\"<>|]#", "", $path);
		foreach ($this->rootNodes as $root) {
			if ($root == substr($path, 0, strlen($root))) {
				return '/' . trim($path, '/');
			}
		}
		
		return $this->rootNodes[0];
	}
	
	public function getList($path = '', $filter = 'all') {
		
		$md5Path = md5($path);
		
		if (isset($this->cache[$md5Path][$filter]))
			return $this->cache[$md5Path][$filter];
		
		$this->client->dir_opendir( $path , array());
		if (!empty($this->client->errorMessage))
			$this->errorMessage = $this->client->errorMessage;

		$temp = array_flip($_SESSION['WebDAVIDCache']);
		$parentID = $temp[$path];
		
		while ($filepath = $this->client->dir_readdir()) {
			
			$filename = basename($filepath);
			
			$this->client->stream_open( $filepath, 'r', array(), $eh);
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
			
			$temp = array('id'			=> $index,
						  'parent'		=> $parentID,
						  'path'		=> $filepath,
						  'name' 		=> urldecode($filename), 
						  'description'	=> urldecode($filename), 
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
		
		if ($path == '')
			return false;
		
		if (in_array($path, $this->rootNodes))
			return '';
		
		$parent = dirname($path);
		
		return rtrim($parent, '/') . '/';
	}
	
	public function getFolderNode($id) {
		
		$path = $this->fixPath($path);
		
		// get folder contents
		$directoryList = $this->getDirectoryList($path);
		
		$parent = $this->getParent($path);

		
		if ($parent && !in_array($parent, $this->rootNodes)) {
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
		
		$this->client->stream_open( $_SESSION['WebDAVIDCache'][$id], 'r', array(), $a);
		
		
		while ($data = $this->client->stream_read(1024)) {
			$buffer .= $data;	
		}
		
		
		$temp = tempnam(sys_get_temp_dir(), uniqid());
		file_put_contents($temp, $buffer);
		return $temp;
	}
	
	public function getByID ($id) {
		$this->client->stream_open( $_SESSION['WebDAVIDCache'][$id], 'r', array(), $a);
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
	
	public function isRoot($id) {
		return in_array($_SESSION['WebDAVIDCache'][$id], $this->rootNodes);
	}
	
	public function create ($data, $file = false) {
		
		if ($data['parent'] == 0)
			return false;			
		
		if ($file) {
			
			$this->client->stream_open( $_SESSION['WebDAVIDCache'][$data['parent']] .  $file['name'], 'w', array(), $a);
			$length = 1024;
			$offset = 0;
			
			$res = $this->client->stream_write(file_get_contents($file['tmp_name']));
																	   
			//while ($res = $this->client->stream_write(file_get_contents($file['tmp_name'], 0, null, $offset, $length))) {
			  
			  $offset += $length;
			//}
			
		} else {
	
			
			$this->client->mkdir( $_SESSION['WebDAVIDCache'][$data['parent']] .  $data['name'] . '/');
		}
		//die();
		
	}
	
	public function update($data) {
		
		if (isset($data['name']))
			$data['description'] = $data['name'];
			
		if (isset($data['description'])) {
			$parent = $this->getParent($_SESSION['WebDAVIDCache'][$data['id']]);
			$this->client->rename( $_SESSION['WebDAVIDCache'][$data['id']], $parent . $data['description']);
		
		} else {
			
					
			if ($this->isRoot($data['id']) || $data['parent'] == 0)
				return false;
			
			$destination = $_SESSION['WebDAVIDCache'][$data['parent']] . basename($_SESSION['WebDAVIDCache'][$data['id']]);
			$this->client->rename( $_SESSION['WebDAVIDCache'][$data['id']], $destination);
		}
		
		
		unset($_SESSION['WebDAVIDCache'][$data['id']]);
	}
	
	public function delete($id) {

		if ($this->isRoot($id) || $id == 0)
			return false;
				
		if (!$this->client->unlink( $_SESSION['WebDAVIDCache'][$id]))
			$this->client->rmdir( $_SESSION['WebDAVIDCache'][$id]);
		
		unset($_SESSION['WebDAVIDCache'][$id]);
	}
}

?>