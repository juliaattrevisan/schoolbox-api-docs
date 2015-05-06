<?php
/**
 * Bulk_Category
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Bulk_Category extends Bulk_Abstract
{

    /**
     * Default id for HomepageTemplate instance
     *
     * @var int
     */
    private $templateID = 0;

    /**
    * Constructor
    *
    * @param $file
    * @param $templateID
    */
    public function __construct($file, $templateID)
    {
        $this->setFile($file);
        $this->setTemplateID($templateID);
    }

    /**
    * Adds categories via csv
    * CSV Fields:
    0.	parent
    1.	title
    2-n. codes
    *
    * @return array
    */
    public function addCategories()
    {
        $categoryMapper = new \Schoolbox\Resource\Folder\Mapper();

        /* List of outcomes
		 */
        $output = array();

        if (is_file($this->getFile())) {
            $groups = array_map(array('Scrub', 'text'), $_POST['groupPerm']);

            /* Process rows
			 */
            $handle = fopen($this->getFile(), 'r');
            $headerRow = fgetcsv($handle, 1000, ",");

            while (($row = fgetcsv($handle, 1000, ",")) !== false) {
                if (($row[0] != '') && ($row[1] != '')) {
/* Check parent exists
					 */
                    try {
                        $category = $categoryMapper->findById($row[0]);
                    } catch (\Exception $e) {
                        $output[] = array(
                            'category' => $row[1],
                            'status' => 'Not Added',
                            'error' => "Could not find parent folder {$row[0]}",
                        );

                        continue;
                    }

                    /* Check if subject code already exists
					 */
                    $codeExists = false;
                    $code = array();

                    for ($i=2; $row[$i]; $i++) {
                        try {
                            $category = $categoryMapper->findByCode($row[$i]);

                            $output[] = array(
                                'category' => $row[$i],
                                'status' => 'Skipped',
                                'error' => "Subject code already exists ({$row[$i]})",
                            );

                            $codeExists = true;
                        } catch (\Exception $e) {
                        }

                        $code[] = $row[$i];
                    }

                    if ($codeExists) {
                        continue;
                    }

                    /* Parent folder
                     */
                    $parent = new \Schoolbox\Resource\Folder\Model();
                    $parent->setId($row[0]);

                    /* Homepage config template
                     */
                    $homepageTemplate = new \Schoolbox\Resource\HomepageTemplate();
                    $homepageTemplate->setId($this->getTemplateID());

                    /* Homepage config
                     */
                    $homepageConfig = new \Schoolbox\Resource\HomepageConfig();
                    $homepageConfig->setTemplate($homepageTemplate);

                    /* Set up new folder
                     */
                    $newFolder = new \Schoolbox\Resource\Folder\Model();
                    $newFolder->setName($row[1]);
                    $newFolder->setParent($parent);
                    $newFolder->setHomepage($homepageConfig);

                    /* Accumulate all folder codes
                     */
                    foreach ($code as $currentCode) {
                        $folderCode = new \Schoolbox\Resource\Folder\Code\Model();
                        $folderCode->setCode($currentCode);

                        $newFolder->getCodes()->add($folderCode);
                    }

                    $categoryMapper->save($newFolder);

                    /* Save roles
                     */
                    $roleAccessMapper = new \Schoolbox\Permission\Access\RoleAccessMapper();

                    foreach ($groups as $id => $perm) {
                        try {
                            $roleMapper = new \Schoolbox\RoleMapper();
                            $role = $roleMapper->findById($id);

                            $roleAccess = new \Schoolbox\Permission\Access\RoleAccess();
                            $roleAccess->setCategory($category);
                            $roleAccess->setRole($role);
                            $roleAccess->setType($perm);
                            $roleAccessMapper->save($roleAccess);
                        } catch (\Exception $e) {
                        }
                    }

                    /* Successfully created
					 */
                    if (is_numeric($category->getId())) {
                        $output[] = array(
                            'category' => $row[1],
                            'status' => 'Added',
                            'error' => 'OK',
                        );
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
    */
    private function setTemplateID($templateID)
    {
        $this->templateID = $templateID;
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
