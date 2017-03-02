<?php

use Alaress\File\Remote\FetcherInterface;
use Schoolbox\Collection;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\Resource;
use Schoolbox\Resource\File\IFile as FileInterface;
use Schoolbox\Resource\File\ILocator as FileLocatorInterface;
use Schoolbox\Resource\Folder\IFolder as FolderInterface;
use Schoolbox\Resource\Folder\ILocator as FolderLocatorInterface;

class PortfolioWebDAV implements FileLocatorInterface, FolderLocatorInterface, FetcherInterface
{
    /**
     * @var DAV_ExtendedClient
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
     * Whether this instance will redirect on an authentication fail, or throw an exception
     * @var bool
     */
    private $lite;

    /** @var Resource\Folder\Mapper */
    protected $folderMapper;

    /** @var Resource\File\Mapper */
    protected $fileMapper;

    /** @var MimetypeMapper */
    protected $mimeMapper;

    /**
     * @param \DAV_ExtendedClient $client
     * @param array               $paths List of paths from the baseUri to access
     * @param bool                $lite
     */
    public function __construct(DAV_ExtendedClient $client, array $paths = array('/'), $lite = false)
    {
        $this->client = $client;

        $this->rootPaths = $paths;

        $this->lite = $lite;

        $this->folderMapper = Resource\Folder\Mapper::getInstance();
        $this->fileMapper   = Resource\File\Mapper::getInstance();
        $this->mimeMapper   = MimetypeMapper::getInstance();

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

        $this->cache[$rootPath]['file'] = array();


    }

    public static function factory($lite = false)
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
        } elseif ($site_location == 'jpc') {
            $settings = array(
                'baseUri' => 'https://malastare/',
                'userName' => "jpc/" . strtolower($_SESSION['username']),
                'password' => $_SESSION['password'],
            );

            if ($groupHandler->isStaff()) {
                $paths = array(
                    '/Staff/' . strtolower($_SESSION['username']) . '/',
                    '/doclib/',
                );
            } else {
                $paths = array(
                    '/Students/' . (date('Y') - ($_SESSION['year'] - 7)) . '/' . strtolower($_SESSION['username']) . '/',
                );
            }
        } elseif ($site_location == 'cathedral') {
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

        } elseif ($site_location == 'bialik') {
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

            } elseif ($groupHandler->isStudent()) {
                $paths = array(
                    '/H-DRIVE/USERS/STUDENTS/YEAR%20' . str_pad($_SESSION['year'], 2, '0', STR_PAD_LEFT) . '/' . $_SESSION['username'] . '/',
                );
            }
        } elseif ($site_location=='stmichaels') {
            $settings = array(
                'baseUri' => 'https://drives.stmichaels.vic.edu.au/',
                'userName' => "smgs/" . $_SESSION['username'],
                'password' => $_SESSION['password'],
            );


            if ($groupHandler->isStaff()) {
                $paths = array(
                    $settings['baseUri'].'staff/' . $_SESSION['username'] . '/',
                );
            } elseif ($groupHandler->isStudent()) {
                $paths = array(
                    $settings['baseUri'] . $_SESSION['username'] . '/',
                );
            }

        } elseif ($site_location=='seymour') {
            $settings = array(
                'baseUri' => 'https://webdav.seymour.sa.edu.au/',
                'userName' => "sccampus/" . $_SESSION['username'],
                'password' => $_SESSION['password'],
            );


            if ($groupHandler->isStaff()) {
                $paths = array(
                    $settings['baseUri'] . 'staffhome/' . ucwords($_SESSION['username']) . '/',
                );
            } elseif ($groupHandler->isStudent()) {
                $paths = array(
                    $settings['baseUri'] .'studenthome/' . (12 - $_SESSION['year'] + date('Y')) . '/' . $_SESSION['username'] . '/',
                );
            } else {
                return false;
            }
        } elseif ($site_location == 'mggs') {
            $settings = array(

                'userName' =>  'mggs/'.$_SESSION['username'],
                'password' => $_SESSION['password'],
            );


            if ($groupHandler->isStaff()) {
                $settings['baseUri'] = 'http://mggs-webdav/';

                $paths = array(
                    '/STAFF/' . $_SESSION['username'] . '/',
                    '/EXCHANGE/',
                    '/morrishall/',
                );

            } elseif ($groupHandler->isStudent()) {
                $settings['baseUri'] = 'http://mggs-fs02/';
                $paths = array(
                    '/STUDENTS/'.(12 - $_SESSION['year'] + date('Y')) .'/' . $_SESSION['username'] . '/',
                );
            }

        } elseif ($site_location == 'tintern') {
            $settings = array(
                'userName' =>  'tintern/'.$_SESSION['username'],
                'password' => $_SESSION['password'],
                'baseUri' => 'http://tnvmfs01/',
            );

            if ($groupHandler->isStaff()) {
                $paths = array(
                    '/Staff/' . $_SESSION['username'] . '/'
                );
            } elseif ($groupHandler->isStudent()) {
                $paths = array(
                    '/Student/'.(12 - $_SESSION['year'] + date('Y')) .'/' . $_SESSION['username'] . '/',
                );
            } else {
                return false;
            }

        } elseif ($site_location=='oakburn') {
            $settings = array(
                'baseUri' => 'http://davusers.soc.tas.edu.au/',
                'userName' => "soc/" . $_SESSION['username'],
                'password' => $_SESSION['password'],
            );

            if ($groupHandler->isStaff()) {
                if ($_SESSION['campus'][0] == '1') {
                    $paths = array(
                      $settings['baseUri'].'penquitestaff/' . $_SESSION['username'] . '/Documents/',
                    );
                } else {
                    $paths = array(
                      $settings['baseUri'].'elphinstaff/' . $_SESSION['username'] . '/Documents/',
                    );
                }

                $fileShares = array(
                    'Academic',
                    'BIT',
                    'CommunityRelations',
                    'DramaAndDance',
                    'EdOutdoors',
                    'English',
                    'HPE',
                    'Humanities',
                    'LOTE',
                    'Maths',
                    'Music',
                    'PhotoStore',
                    'Science',
                    'Technology',
                    'VisualArts',
                    'RoundSquare',
                    'AdminDocs',
                    'AdminDocument'
                );

                foreach ($fileShares as $share) {
                    $paths[] = $settings['baseUri'].'/'.$share.'/';
                }

            } elseif ($groupHandler->isStudent()) {
                if ($_SESSION['year']>='6') {
                    $paths = array(
                      $settings['baseUri'].'penquitestudents/Year12in' . (12 - $_SESSION['year'] + date('Y')) . '/' . $_SESSION['username'] . '/Documents/',
                    );
                } else {
                    $paths = array(
                      $settings['baseUri'].'elphinstudents/Year12in' . (12 - $_SESSION['year'] + date('Y')) . '/' . $_SESSION['username'] . '/Documents/',
                    );
                }

            }

        } elseif ($site_location == 'iggs') {
            $settings = array(
                'userName' =>  $_SESSION['username'],
                'password' => $_SESSION['password'],
                'baseUri' => 'https://myschool1.ivanhoegirls.vic.edu.au',
            );

            if ($groupHandler->isStaff()) {
                $paths = array(
                    $settings['baseUri'].'/webdav/staff/' . $_SESSION['username'] . '/',
                    $settings['baseUri'].'/webdav/shared/'
                );
            } elseif ($groupHandler->isStudent()) {
                $paths = array(
                    $settings['baseUri'].'/webdav/student/' . $_SESSION['username'] . '/',
                    $settings['baseUri'].'/webdav/shared/'
                );
            }
        } elseif ($site_location == 'sedc') {
            $settings = array(
                'userName' =>  'SEC/' . $_SESSION['username'],
                'password' => $_SESSION['password'],
                'baseUri' => 'http://myschoolfiles',
            );

            if ($groupHandler->isStaff()) {
                $paths = array(
                    $settings['baseUri'].'/StaffHome/' . $_SESSION['username'] . '/',
                    $settings['baseUri'].'/Assets/',
                    $settings['baseUri'].'/StaffShares/',
                    $settings['baseUri'].'/Submissions/'
                );
            } elseif ($groupHandler->isStudent()) {
                $paths = array(
                    $settings['baseUri'].'/StudentHome/' . (12 - $_SESSION['year'] + date('Y')) . '/' . $_SESSION['username'] . '/'
                );
            }
        } elseif ($site_location == 'btac') {
            $settings = array(
                'userName' =>  $_SESSION['username'],
                'password' => $_SESSION['password'],
                'baseUri' => 'https://wd1.btac.nsw.edu.au',
            );

            if ($groupHandler->isStaff()) {
                $paths = array(
                    $settings['baseUri'].'/home/' . $_SESSION['username'] . '/',
                    $settings['baseUri'].'/vol/shared/',
                );
            }

        } elseif ($site_location=='fintona') {
            $settings = array(
                'baseUri' => 'https://files.fintona.vic.edu.au/',
                'userName' => "fintona/" . $_SESSION['username'],
                'password' => $_SESSION['password'],
            );

            if ($groupHandler->isStaff()) {
                $paths = array(
                    $settings['baseUri'].'staff/',
                    $settings['baseUri'].'public/',
                );
            } elseif ($groupHandler->isStudent()) {
                $paths = array(
                    $settings['baseUri'].'public/',
                );
            }

        } elseif ($site_location=='loyola') {
            $settings = array(
                'baseUri' => 'http://lcapache.loyola.local:8080/',
                'userName' => "loyola/" . $_SESSION['username'],
                'password' => $_SESSION['password'],
            );
            if ($groupHandler->isStaff()) {
                $paths = array(
                    $settings['baseUri'].'Staff%20Home/' . $_SESSION['username'] . '/',
                );
            } elseif ($groupHandler->isStudent()) {
                $paths = array(
                    $settings['baseUri'].'Student%20Home/' . (12 - $_SESSION['year'] + date('Y')) . '/' . $_SESSION['username'] . '/',
                );
            }
        } elseif ($site_location=='sheldon') {
            $settings = array(
                'baseUri' => 'http://svwebdav01.sheldoncollege.com/',
                'userName' => $_SESSION['username'] . "@sheldoncollege.com",
                'password' => $_SESSION['password'],
            );

            if ($groupHandler->isStaff()) {
                $paths = array(
                    $settings['baseUri'].'staffhome/' . $_SESSION['username'] . '/',
                    $settings['baseUri'].'collegeshare/',
                );
                $roleid = $_SESSION['user']->getRole()->getId();
                if ($roleid != '3' and $roleid != '4') {
                    $paths = array_merge($paths, array(
                        $settings['baseUri'].'academicshare/',
                        $settings['baseUri'].'collegeshare/',
                        $settings['baseUri'].'photoshare/',
                        $settings['baseUri'].'studentshare/',
                    ));
                }
            } elseif ($groupHandler->isStudent()) {
                $paths = array(
                    $settings['baseUri'].'studenthome/' . $_SESSION['username'] . '/',
                    $settings['baseUri'].'studentshare/',
                );
            }
        } elseif ($site_location=='ngsl') {

            if ($groupHandler->isStaff()) {
                $settings = array(
                    'baseUri' => 'http://facultycloud.ngs.nsw.edu.au/',
                    'userName' => $_SESSION['username'].'@ngs.nsw.edu.au',
                    'password' => $_SESSION['password'],
                );
                $paths = array(
                    $settings['baseUri'] . 'KDrive/',
                    $settings['baseUri'] . 'staffhome/' . $_SESSION['username'] . '/',
                    $settings['baseUri'] . 'StudentShare/',
                );
            } elseif ($groupHandler->isStudent()) {
                $settings = array(
                    'baseUri' => 'http://studentcloud.ngs.nsw.edu.au/',
                    'userName' => $_SESSION['username'].'@ngs.nsw.edu.au',
                    'password' => $_SESSION['password'],
                );
                $paths = array(
                    $settings['baseUri']. $_SESSION['username'] . '/',
                );
            }

        } elseif ($site_location=='meriden') {
            $settings = array(
                'baseUri' => 'http://webdav.meriden.nsw.edu.au/',
                'userName' => "meriden/" . $_SESSION['username'],
                'password' => $_SESSION['password'],
            );

            if ($groupHandler->isStaff()) {
                $paths = array(
                    $settings['baseUri'].'staff/' . $_SESSION['username'] .'/',
                    $settings['baseUri'].'staffgroup/',
                    $settings['baseUri'].'student/',
                );
            } elseif ($groupHandler->isStudent()) {
                $paths = array(
                    $settings['baseUri'].(12 - $_SESSION['year'] + date('Y')) . '/' . $_SESSION['username'] . '/',
                );
            }
        }
        elseif ($site_location=='diocesan') {
            $settings = array(
                'baseUri'  => 'http://rdrive.diocesan.school.nz/',
                'userName' => 'diont/' . $_SESSION['username'],
                'password' => $_SESSION['password'],
            );
            if ($groupHandler->isStaff()) {
                $paths = array($settings['baseUri'] . 'rdrive/',);
            }
        } elseif ($site_location=='tccll') {
            $settings = array(
                'baseUri' => 'https://files.trinitylismore.nsw.edu.au/',
                'userName' => "TRINITY/" . $_SESSION['username'],
                'password' => $_SESSION['password'],
            );

            if ($groupHandler->isStaff()) {
                $paths = array(
                    $settings['baseUri'].'staffhome/' . $_SESSION['username'] .'/',
                    $settings['baseUri'].'staffshare/',
                );
            } elseif ($groupHandler->isStudent()) {
                $paths = array(
                    $settings['baseUri'].'studentshare/',
                );
            }

        } elseif ($site_location == 'rlc') {
            $settings = array(
                'baseUri' => 'http://WebDavShareDrive.redeemer.net.au',
                'userName' => 'redeemer/'.$_SESSION['username'],
                'password' => $_SESSION['password'],
            );

            if ($groupHandler->isStaff()) {
                $paths = array(
                    'http://WebDavShareDrive.redeemer.net.au/',
                    'http://WebDavMediaDrive.redeemer.net.au/',
                    'http://WebDavHomeStaff.redeemer.net.au/'.$_SESSION['username'].'/',
                    'http://WebDavHomeStudent.redeemer.net.au'
                );
            } elseif ($groupHandler->isStudent()) {
                $paths = array(
                    'http://WebDavShareDrive.redeemer.net.au',
                    'http://WebDavMediaDrive.redeemer.net.au',
                    'http://WebDavHomeStudent.redeemer.net.au/'. $_SESSION['username'] . '/'
                );
            }
	} elseif ($site_location == 'accac') {
            $settings = array(
                'baseUri' => 'http://webdav.aquinas.qld.edu.au:8080',
                'userName' => $_SESSION['username'],
                'password' => $_SESSION['password'],
            );

            if ($groupHandler->isStaff()) {
                $paths = array(
                    '/'
                );
            } elseif ($groupHandler->isStudent()) {
                $paths = array(
                    '/'
                );
            }
        } elseif ($site_location == 'slmp') {
            $settings = array(
                'baseUri' => 'https://sldm-wdav-01.stlouisaspendale.catholic.edu.au/',
                'userName' => 'stlouisdom/'.$_SESSION['username'],
                'password' => $_SESSION['password'],
            );
            if ($groupHandler->isStaff()) {
                $paths = array(
                    $settings['baseUri'] . '/staffshare/',
                    $settings['baseUri'] . '/reports/',
                    $settings['baseUri'] . '/thome/' . $_SESSION['username'] . '/',
                );
            }
            if ($groupHandler->isParent()) {
                $paths = array(
                    $settings['baseUri'] . '/reports/'. $_SESSION['year'] . '/',
                );
            }
        } elseif ($site_location == 'sicol') {
            $settings = [
                'baseUri'  => 'http://sbox_webdav.siena.vic.edu.au:81/',
                'userName' => 'siena/'.$_SESSION['username'],
                'password' => $_SESSION['password'],
            ];
            if ($groupHandler->isStaff()) {
                $paths = [$settings['baseUri'].'StaffHome/' . $_SESSION['username'] .'/',];
            } elseif ($groupHandler->isStudent()) {
                $paths = [$settings['baseUri'].'StudentHome/CL' . (12 - $_SESSION['year'] + date('Y')) . '/' . $_SESSION['username'] .'/',];
            }
        } elseif ($site_location == 'ducv') {
            $settings = array(
                'baseUri'  => 'http://ducvfiles.dulum.vic.edu.au:8461',
                'userName' => $_SESSION['username'],
                'password' => $_SESSION['password'],
            );
            if ($groupHandler->isStaff()) {
                $paths = array(
                    $settings['baseUri'] . '/PublicDrive/',
                    $settings['baseUri'] . '/StaffHome/' . $_SESSION['username'] . '/',
                );
            }
        } elseif ($site_location == 'stpauls') {
            $settings = array(
                'baseUri'  => 'https://files.stpauls.nsw.edu.au/',
                'userName' => $_SESSION['username'],
                'password' => $_SESSION['password'],
            );
            if ($groupHandler->isStaff()) {
                $paths = [
                    $settings['baseUri'] . 'StaffGroup/',
                    $settings['baseUri'] . 'StaffPublic/',
                    $settings['baseUri'] . 'StudentGroup/',
                    $settings['baseUri'] . 'StudentPublic/',
                ];
            } elseif ($groupHandler->isStudent()) {
                $paths = [
                    $settings['baseUri'] . 'StudentGroup/',
                    $settings['baseUri'] . 'StudentPublic/',
                ];
            }

        } elseif ($site_location == 'alpgs') {
            $settings = array(
                'baseUri'  => 'https://webdav.ags.vic.edu.au/',
                'userName' => 'ags\\' . $_SESSION['username'],
                'password' => $_SESSION['password'],
            );
            if ($groupHandler->isStaff()) {
                $paths = [
                    $settings['baseUri'] . 'TDrive/',
                    $settings['baseUri'] . 'StaffHome/' . $_SESSION['username'] . '/',
                ];
            } elseif ($groupHandler->isStudent()) {
                $paths = [
                    $settings['baseUri'] . 'StudentHome/' . $_SESSION['username'] . '/',
                    $settings['baseUri'] . 'StudentCommon/',
                ];
            }

        } elseif ($site_location == 'sohcs') {
            $settings = array(
                'baseUri'  => 'http://10.0.0.1/shares/',
                'userName' => $_SESSION['username'],
                'password' => $_SESSION['password'],
            );
            if ($groupHandler->isStaff()) {
                $paths = [
                    $settings['baseUri'] . 'central/',
                    $settings['baseUri'] . 'staff_data/' . $_SESSION['username'] . '/',
                ];
            } elseif ($groupHandler->isStudent()) {
                $paths = [
                    $settings['baseUri'] . 'student_data/' . $_SESSION['username'] . '/',
                ];
            }

        } elseif (in_array($site_location, array('core', 'demo'))) {
            $settings = array(
                'baseUri' => 'http://webdave.alaress-dev.com.au/',
                'userName' => 'webdave',
                'password' => 'e3IRS043UdNis',
            );

            $paths = array('/');
        }


        if (empty($settings) || empty($paths)) {
            // the next invocation would actually cause a fatal error if $settings is undefined anyway,
            // so this is no worse (but is now catchable)
            throw new DomainException("Drives is not configured");
        }

        $client = new DAV_ExtendedClient($settings);

        return new self($client, $paths, $lite);
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
            $response = $this->client->propFind(
                $path,
                array(
                    '{DAV:}getcontentlength',
                    '{DAV:}resourcetype',
                    '{DAV:}getlastmodified',
                    '{DAV:}creationdate',),
                1
            );
        } /* Handle Sabre_DAV_Exception specifically
         */
        catch (Sabre\DAV\Exception\NotAuthenticated $e) {
            SitePage::localRedir('/cms/networkLogin' . ($this->lite ? '/lite' : ''));
        }
        catch (Sabre\DAV\Exception\NotFound $e) {
            /* In some cases we are told that something exists, only to be thrown a 404 error
                Instead of reacting badly just return false and get on with life */
            return false;
        }
        catch (Sabre\DAV\Exception $e) {
            if($e->getMessage() == "HTTP error: 401") {
                SitePage::localRedir('/cms/networkLogin' . ($this->lite ? '/lite' : ''));
            } else if($e->getMessage() == "HTTP error: 404" OR $e->getMessage() == "HTTP error: 400") {
                return false;
            } else {
                error(141, $e->getMessage() . " at: ".$path);
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
        $temp       = sb_tempnam('dav');
        if ($file_size <= $chunk_size) {
            $response = $this->client->request('GET', $_SESSION['portfolioIdPathMap'][$id]);
            file_put_contents($temp, $response['body'], LOCK_EX);
        } else {
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
        /* $id of zero is always the shadow root node, which resolves to the base URI
           This means if the user has no access to the root of the webdav server '/'
           it will fail with a 403 error, even if they can access all the defined shares.
           Instead send fake data for the "/" root node.
        */
        if($id == "0") {
            $temp = array(
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
            );
            return $temp;
        }
        try {
            $response = $this->client->propFind($_SESSION['portfolioIdPathMap'][$id], array(
                '{DAV:}getcontentlength',
                '{DAV:}resourcetype',
                '{DAV:}getlastmodified',
                '{DAV:}creationdate',
            ), 1);

            $response = array_shift($response);

            $parentID = $this->getParent($id);

            $filename = basename($_SESSION['portfolioIdPathMap'][$id]);

            $temp = array(
                'id' => $id,
                'parent' => $parentID,
                'path' => $_SESSION['portfolioIdPathMap'][$id],
                'name' => $filename,
                'description' => $filename,
                'type' => $this->parseResourceType($response['{DAV:}resourcetype']),
                'file_size' => $response['{DAV:}getcontentlength'],
                'submitted' => $response['{DAV:}getlastmodified'],
                'ctime' => $response['{DAV:}creationdate'],
                'owner' => $_SESSION['id'],
            );

            return $temp;
        }
        /* Handle Sabre_DAV_Exception specifically
         */
        catch (Sabre\DAV\Exception\NotAuthenticated $e) {
            SitePage::localRedir('/cms/networkLogin' . ($this->lite ? '/lite' : ''));
        }
        catch (Sabre\DAV\Exception\NotFound $e) {
            /* In some cases we are told that something exists, only to be thrown a 404 error
                Instead of reacting badly just return false and get on with life */
            return false;
        }
        catch (Sabre\DAV\Exception $e) {
            if($e->getMessage() == "HTTP error: 401") {
                SitePage::localRedir('/cms/networkLogin' . ($this->lite ? '/lite' : ''));
            } else if($e->getMessage() == "HTTP error: 404" OR $e->getMessage() == "HTTP error: 400") {
                return false;
            } else {
                error(141, $e->getMessage(). " at: ".$_SESSION['portfolioIdPathMap'][$id]);
            }
        }
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
            if ($file['resource']) {
                $this->client->put($path, $file['resource']);
            } else {
                $this->client->put($path, $file['tmp_name']);
            }
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

        $owner = new \Schoolbox\User();
        $owner->setId($data['owner']);

        $cat = $this->folderMapper->createModel();
        $cat->setId($data['parent']);

        $file->setId($data['id']);
        $file->setFolder($cat);
        $file->setBasename($data['name']);
        $file->setTitle($data['description']);
        $file->setSize($data['file_size']);
        $file->setOwner($owner);
        try {
            $file->setMimetype($this->mimeMapper->getByFilename($data['name']));
        } catch (\Schoolbox\File\Exception $e) {
            $file->setMimetype($this->mimeMapper->getUnknownMime());
        }

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
        $data  = $this->getAllInFolder($nodeId);

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
        $data    = $this->getFolderList($nodeId);

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
    function fetchToFile($fileId)
    {
        return $this->getFile($fileId);
    }
}
