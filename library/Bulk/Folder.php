<?php
/**
 * Bulk_Folder
 *
 * Upload folder structures from a csv
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */

use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Resource\Folder\Code\Mapper as FolderCodeMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;


class Bulk_Folder
{

    /**
     * Default id for HomepageTemplate instance
     *
     * @var int
     */
    private $templateID = 0;

    /**
     * Storage object
     * @var resource
     */
    protected $fp;

    /**
     * Constructor
     *
     * @param $file
     * @param $templateID
     */
    public function __construct($file, $templateID)
    {
        $this->fp = $file;
        $this->setTemplateID($templateID);
    }

    /**
     * Adds folders via csv
     *
     * CSV Fields:
     * 0.	parent
     * 1.	title
     * 2-n. codes
     *
     * @return array
     */
    public function addFolders()
    {
        $folderMapper = FolderMapper::getInstance();
        $folderCodeMapper = FolderCodeMapper::getInstance();
        $roleAccessMapper = RoleAccessMapper::getInstance();
        $roleMapper = RoleMapper::getInstance();
        $homepageTemplateMapper = HomepageTemplateMapper::getInstance();
        $homepageConfigMapper = HomepageConfigMapper::getInstance();

        /* List of outcomes
		 */
        $output = [];

        if (is_resource($this->fp)) {
            $groups = array_map(['Scrub', 'text'], $_POST['groupPerm']);

            /* Process rows
			 */
            $headerRow = fgetcsv($this->fp, 1000, ",");

            while (($row = fgetcsv($this->fp, 1000, ",")) !== false) {

                /* Parent folder and Import Folder name must exist
                 */
                if (($row[0] != '') && ($row[1] != '')) {

                    /* Check parent exists
					 */
                    try {
                        $folder = $folderMapper->findById($row[0]);
                    }
                    catch (MapperException $e) {
                        $output[] = [
                            'id' => '',
                            'name' => $row[1],
                            'status' => 'Not Added',
                            'error' => "Could not find parent folder {$row[0]}",
                        ];

                        continue;
                    }

                    /* Check codes */

                    $codes = [];
                    $skip = false;

                    for ($i=2; $row[$i]; $i++) {
                        $subjectCode = trim($row[$i]);

                        /* Check if code too looong */
                        if(strlen($subjectCode) > 20) {
                            $skip = true;

                            $output[] = [
                                'id' => '',
                                'name' => $row[1],
                                'status' => 'Skipped',
                                'error' => "Subject code exceeds 20 characters ({$subjectCode})",
                            ];

                            break;
                        }

                        /* Check if subject code already exists*/
                        try {
                            $folder = $folderMapper->findByCode($subjectCode);

                            $skip = true;

                            $output[] = [
                                'id' => '',
                                'name' => $row[1],
                                'status' => 'Skipped',
                                'error' => "Subject code already exists ({$subjectCode})",
                            ];

                            break;
                        } catch (MapperException $e) {
                            /* noop - folder doesn't exist
                             */
                            $codes[] = $subjectCode;
                        }
                    }

                    /*skip folder creation if above we found any of the folder subjectCodes exist already
                    */
                    if($skip) {
                        continue;
                    }


                    /* Parent folder
                     */
                    $parent = $folderMapper->createModel();
                    $parent->setId($row[0]);

                    /* Homepage config template
                     */
                    $homepageTemplate = $homepageTemplateMapper->createModel();
                    $homepageTemplate->setId($this->getTemplateID());

                    /* Homepage config
                     */
                    $homepageConfig = $homepageConfigMapper->createModel();
                    $homepageConfig->setTemplate($homepageTemplate);

                    /* Set up new folder
                     */
                    $newFolder = $folderMapper->createModel();
                    $newFolder->setName(trim($row[1]));
                    $newFolder->setParent($parent);
                    $newFolder->setHomepage($homepageConfig);

                    /* Accumulate all folder codes
                     */
                    foreach ($codes as $currentCode) {

                        $folderCode = $folderCodeMapper->createModel();
                        $folderCode->setCode($currentCode);

                        $newFolder->getCodes()->add($folderCode);
                    }

                    /* Inherit perms
                     */
                    if (count($codes) == 0 && $_POST["inherit"]) {
                        $newFolder->setInherit(true);
                    }

                    $folderMapper->save($newFolder);

                    // Save the homepage contents
                    if ($this->getTemplateID()) {
                        $homepageConfigMapper->save($homepageConfig);
                        $homepageConfigMapper->resetToTemplate($homepageConfig);
                    }

                    if (!$_POST["inherit"]) {

                        /* Save roles
                         */
                        foreach ($groups as $id => $perm) {
                            try {
                                /** @var Role $role */
                                $role = $roleMapper->findById($id);

                                $roleAccess = $roleAccessMapper->createModel();
                                $roleAccess->setCategory($newFolder);
                                $roleAccess->setRole($role);
                                $roleAccess->setType($perm);
                                $roleAccessMapper->save($roleAccess);
                            } catch (MapperException $e) {

                                /* noop - role not found
                                 */
                            }
                        }
                    }

                    /* Successfully created
					 */
                    if (is_numeric($newFolder->getId())) {
                        $output[] = [
                            'id' => $newFolder->getId(),
                            'name' => $row[1],
                            'status' => 'Added',
                            'error' => 'OK',
                        ];
                    }
                }
            }
        }

        return $output;
    }

    /* Getters/Setters
	 */

    /**
    * Set template id
    *
    * @param $templateID
    * @return $this
    */
    private function setTemplateID($templateID)
    {
        $this->templateID = $templateID;
        return $this;
    }

    /**
    * Get template id
    *
    * @return int
    */
    private function getTemplateID()
    {
        return $this->templateID;
    }
}
