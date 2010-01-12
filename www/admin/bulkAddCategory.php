<?php

/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/


/* 
CSV fields

	0. parent
	1. title
	2. codes

*/

require_once('../conf/conf.php');
//require_once("../include/session.php");
//require_once("../include/adminonly.php");
require_once("../../library/category.php");

class BulkAdd {

	function parseCSV($file) {
		$catHandler = new Category;

		$groups = array(1=>2,
						2=>2,);


		if (is_file($file)){
			$handle = fopen($file, 'r');
			while (($category = fgetcsv($handle, 1000, ",")) !== FALSE) {
				if($category[0] != '' && $category[1] != '' )
				{
					//check parent exists
					if( $catHandler->getByID($category[0]) == FALSE) {
						print "Failed to find parent folder ({$category[0]})\n";
						continue;
					}

					//check if subject code already exists
					$codeExists = false;
					$code = '';
					for($i=2;$category[$i];$i++) {
						if( $catHandler->getByCode($category[$i]) != FALSE ) {
							print "Subject code already exists ({$category[$i]})\n";
							$codeExists = true;
						}

						$code .= $category[$i].',';

					}

					if($codeExists)
						continue;
		
					$code = substr($code,0,-1);


					$newCat = array('name' => $category[1],
									'parent'=> $category[0],
									'template'=> 1,
									'group'		=> $groups,
									'code'		=> $code,
						);

					//print_r($newCat);
					$catHandler->create($newCat);

				}
			}
		}
	}
}

$page = new BulkAdd;


        
//two types of upload.. either single user.. or via csv file..
if(isset($_FILES['bulkadd']['tmp_name']) && $_FILES['bulkadd']['tmp_name'])
{
        //file submitted
        //parse file
        $page->parseCSV($_FILES['bulkadd']['tmp_name']);
        
        print "<br>Add Completed";
}
else if(is_file($argv[1])) {
	$page->parseCSV($argv[1]);
}
else
{   
        //not enough info submitted fail
        //$page->displayPage();
}
?>