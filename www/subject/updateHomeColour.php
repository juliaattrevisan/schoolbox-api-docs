<?php

require_once("../include/include.php");

$db = new dbManager(SQL_db, 1);
//build lookup table

$query = "SELECT id, hex FROM homepage_colours";
$colourResult = $db->getAll($query);

foreach($colourResult as $colour) {
 $colourTable[$colour['id']] = $colour['hex'];
}

//select homepages
$query = "SELECT cid, large_head_fontcolour, large_head_bgcolour, large_text_fontcolour, large_text_bgcolour, small_head_fontcolour, small_head_bgcolour, small_text_fontcolour, small_text_bgcolour FROM homepage_config";
$result = $db->query($query);

while($homepage = $db->getNextArray($result) ) {
//use lookup table to insert new colours into 
	$update = array(	'cid'	=> $homepage['cid'],
						'large_head_fontcolour' => $colourTable[$homepage['large_head_fontcolour']],
						'large_head_bgcolour'	=> $colourTable[$homepage['large_head_bgcolour']], 
						'large_text_fontcolour'	=> $colourTable[$homepage['large_text_fontcolour']], 
						'large_text_bgcolour'	=> $colourTable[$homepage['large_text_bgcolour']], 
						'small_head_fontcolour'	=> $colourTable[$homepage['small_head_fontcolour']], 
						'small_head_bgcolour'	=> $colourTable[$homepage['small_head_bgcolour']], 
						'small_text_fontcolour'	=> $colourTable[$homepage['small_text_fontcolour']], 
						'small_text_bgcolour'	=> $colourTable[$homepage['small_text_bgcolour']],
					);
	$db->dbUpdateRow('homepage_config','cid',$update);
}

?>