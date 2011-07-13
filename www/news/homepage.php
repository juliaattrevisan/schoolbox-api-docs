<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/categoryNews.php';

$groupHandler = new Group;

if(is_numeric($this->arg[0])) {

	/* Modify existing news item */
	$catNewsHandler = new CategoryNews;
	
	if(!$newsItem = $catNewsHandler->getByID($this->arg[0]))
		error(2);
	
	if (($permission = $groupHandler->getPermission($newsItem['code'])) < WRITE_ACCESS)
		error(23);
		
	// check user is owner
	if ($permission == WRITE_ACCESS && $newsItem['owner'] != $_SESSION['id'] && !NEWS_teacher_edit_other)
		error(23);
	
	$this->setFile(TEMPLATE_content, 'news/homepage.html');
	$this->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');
	
	$this->setTitle('Modify Homepage News');
	$this->setTab('resources');

    $publishedDate = new DateTime( $news['item_datetime_published'] );
    $expiryDate = new DateTime( $news['item_datetime_expires'] );

    $output = array(
        'ACTION'      => 'Modify',
        'NEWS_ID'     => $newsItem['id'],
        'HEADING'     => $newsItem['heading'],
        'BODY'        => $newsItem['body'],
        'CATEGORY_ID' => $newsItem['code'],
        'DATETIME_PUBLISHED' => $publishedDate->format(Cfg::get('date_select')),
        'DATETIME_EXPIRES' => $expiryDate->format(Cfg::get('date_select')),
    );
						
	$this->setVar($output);
	
	$this->parse('delete_button_block', 'delete_button');
	
}
else if($this->arg[0] == 'new' && is_numeric($this->arg[1])) {
	
	/* Create a new news item */
	
	if (($permission = $groupHandler->getPermission($this->arg[1])) < WRITE_ACCESS)
		error(23);

			
	$this->setFile(TEMPLATE_content, 'news/homepage.html');
	$this->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');
	
	$this->setTitle('Create Homepage News');
	$this->setTab('resources');

    /* Default DateTime */
    $published = new DateTime();
    $expires = new DateTime('+1 month');

    /* Set default output */
	$output = array(	'ACTION'				=> 'Create',
						'HEADING'				=> '',
						'BODY'					=> '',
						'NEWS_ID'				=> '',
						'CATEGORY_ID'			=> $this->arg[1],
						'delete_button_block'	=> '',
                        'DATETIME_PUBLISHED' => $published->format( Cfg::get('date_select') ),
                        'DATETIME_EXPIRES' => $expires->format( Cfg::get('date_select') ),
					);
						
	$this->setVar($output);
	
	
}
else
	error(2);

?>