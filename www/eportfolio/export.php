<?php
require_once '../../include/Bootstrap.php';
require_once '../include/pclzip.lib.php';

// init session
$page = new SitePage();

$userHandler = new User;

$user = $userHandler->getById((int)$_REQUEST['user_id']);

$user_id = $user['uid'];

if (!$user_id) {
    error(31);
}

// check permissions
$container = new Eportfolio_Container($user_id);
if (!$container->canView($user_id)) {
    error(19);
}

// generate html
$page->loadContent('eportfolio/' . $user_id . '/export');
$page->setBlock(TEMPLATE_content, 'custom_head', 'custom_head_block');

$page->setFile(TEMPLATE_body, 'include/body.html');
$page->setFile('headerTpl', 'eportfolio/export/header.html');

$page->setVar(
    array(
        'MAIN_BANNER_REGION' => '',
        'MAIN_TAB_REGION' => '',
        'MAIN_NAV_REGION' => '',
        'MAIN_FOOTER_REGION' => '',
        'custom_head_block' => '',
    )
);

$page->template->parse('MAIN_HEADER_REGION', 'headerTpl');
$page->template->parse('CUSTOM_HEADER_REGION', 'custom_head');
$page->template->parse('MAIN_CONTENT_REGION', TEMPLATE_content);

ob_start();

$page->outputPage(true);
$html = ob_get_contents();

ob_end_clean();


// archive myFiles
$zipFolder = new Eportfolio_ZipFolder(0, $user_id);
mkdir($zipFolder->tmpFolder . '/Private', 0777, true);
$zipFolder->createDirectory($zipFolder->tmpFolder . '/Private', $zipFolder->folder);

$myFilePaths = $zipFolder->getRelativePaths();
if (is_array($myFilePaths)) {

    // find & replace myFile asset paths within html
    // matches paths: /cms/image?* and /send.php?myFile=1*
    $html = preg_replace_callback(
        '#((?:/cms/image.php\?|/cms/sendMyFile.php\?)([^"]+))#', function ($matches) {
            global $myFilePaths;

            // parse query string
            $query = array();
            parse_str($matches[2], $query);

            // take width/height query parameters and use them for <img> attributes
            // only use the lower of the two to avoid stretching the image
            if ($query['width'] > $query['height']) {
                return sprintf('%s " height="%d', $myFilePaths[$query['id']], $query['height']);
            } else {
                return sprintf('%s " width="%d', $myFilePaths[$query['id']], $query['width']);
            }

        }, $html
    );
}

$fileHandler = new File;
$groupHandler = new Group;

mkdir(sprintf('%s/%s', $zipFolder->tmpFolder, 'Shared'));

// find & replace public resources asset paths within html
// only finds /send.php?* paths without myFile=1 as those paths are already replaced
$html = preg_replace_callback(
    '#(/send.php\?([^"]+))#', function ($matches) {
        global $fileHandler;
        global $groupHandler;
        global $zipFolder;

        // parse query string
        $query = array();
        parse_str($matches[2], $query);

        // lookup file reference
        $file = $fileHandler->getByID($query['id']);
        if ($file && $groupHandler->getPermission($file['category']) >= READ_ACCESS) {

            // copy actual file to tmp folder
            $filePath = Cfg::get('file_dir') . '/' . $file['id'] . '.dat';
            if (is_file($filePath)) {

                $relativePath = 'Shared/' . $zipFolder->uniqueFileName($file['realname']);
				if (is_readable($filePath)) {
					copy($filePath, sprintf('%s/%s', $zipFolder->tmpFolder, $relativePath));
				}

                // replace html path
                return $relativePath;
            } else {
                // actual file doesn't exist, leave html path as is
                return $matches[0];
            }
        }

    }, $html
);

// copy reachable css/js assets to tmpFolder (referenced directly from html)
// matches the following tags with asset paths in css/, javascript/ or images/
//  <link ... href="*"
//  <script ... src="*".
//  url(*)
$wwwDir = SITE_root . '/www';
$regex = array(
    '#((?:link|script).*?(?:href|src)=")(/(?:css|javascript|images)[^"]+)#',
    '#(url\()([^\)]+)#'
);
$html = preg_replace_callback(
    $regex, function ($matches) {

        global $zipFolder;
        global $wwwDir;

        $targetPath = sprintf('%s/%s', $zipFolder->tmpFolder, dirname($matches[2]));
        @mkdir($targetPath, 0777, true);
		$src_file = sprintf('%s/%s', $wwwDir, $matches[2]);
		if (is_readable($src_file)) {
			copy($src_file, sprintf('%s/%s', $targetPath, basename($matches[2])));
		}

        return $matches[1] . ltrim($matches[2], '/');

    }, $html
);


// find & replace portrait image
$html = preg_replace_callback(
    '#/portrait\?([^"]+)#', function ($matches) {

        global $serviceController;
        global $zipFolder;

        // parse query string
        $query = array();
        parse_str($matches[1], $query);

        $image = new Alaress_Image_Portrait($query['userId'], $query['size'], new Alaress_Image_Transformation_Portrait());
        $srcFile = $image->save();

        $filename = 'portrait.jpg';
		if (is_readable($srcFile)) {
			copy($srcFile, sprintf('%s/%s', $zipFolder->tmpFolder, $filename));
		}

        return $filename;

    }, $html
);

// manually copy remaining assets (hidden within js or css references)
mkdir(sprintf('%s/images/slimbox', $zipFolder->tmpFolder), 0777, true);
foreach (array('loading.gif', 'closelabel.gif', 'nextlabel.gif', 'prevlabel.gif') as $file) {
	$src_file = sprintf('%s/images/slimbox/%s', $wwwDir, $file);
	$dst_file = sprintf('%s/images/slimbox/%s', $zipFolder->tmpFolder, $file);
	if (is_readable($src_file)) {
		copy($src_file, $dst_file);
	}
}

// save html to tmpFolder
file_put_contents(sprintf('%s/%s', $zipFolder->tmpFolder, 'index.html'), $html);

// archive tmpFolder
$archive = new PclZip($zipFolder->tmpZip);
$archive->create($zipFolder->tmpFolder . '/', PCLZIP_OPT_REMOVE_PATH, $zipFolder->tmpFolder);

// send archive
$filename = $zipFolder->cleanName(sprintf('eportfolio-%s-%s-%s', $user['firstname'], $user['lastname'], date('Y-m-d')));

$filesize = filesize($zipFolder->tmpZip);
header("Pragma: ");
header("Cache-control: ");
header("Content-Type: application/x-zip-compressed");
header("Content-Disposition: attachment; filename=\"" . $filename . ".zip\"");
header("Content-Length: $filesize");
readfile($zipFolder->tmpZip);

$zipFolder->cleanUp();
