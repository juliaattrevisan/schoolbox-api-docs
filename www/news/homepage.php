<?php

use Schoolbox\Resource\Folder\Mapper as FolderMapper;

$groupHandler = new Group;

if(is_numeric($this->arg[0])) {

	/* Modify existing news item */
	$catNewsHandler = new CategoryNews;
	
	if(!$newsItem = $catNewsHandler->getByID($this->arg[0])) {
		error(2);
	}

	try{
		$folder = FolderMapper::getInstance()->findById($newsItem['code']);
	} catch (\Schoolbox\Mapper\Exception $e) {
		error(23);
	}

	if (($permission = $groupHandler->getPermission($newsItem['code'])) < WRITE_ACCESS) {
		error(23);
	}

	// check user is owner, and if not whether users can edit other's news
	if ($permission == WRITE_ACCESS
		&& $newsItem['owner'] != $_SESSION['id']
		&& !Cfg::get('news_edit_other')
	) {
		error(23);
	}

	$this->setFile(TEMPLATE_content, 'news/homepage.html');
	$this->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');
	
	$this->setTitle('Modify Homepage News');
	$this->setTab('resources');

	$publishedDate = new Alaress_Date_Format($newsItem['datetime_published'], 'Y-m-d');
	$expiryDate = new Alaress_Date_Format($newsItem['datetime_expires'], 'Y-m-d');

    $output = array(
        'ACTION'      => 'Modify',
        'NEWS_ID'     => $newsItem['id'],
        'HEADING'     => $newsItem['heading'],
        'BODY'        => $newsItem['body'],
        'CATEGORY_ID' => $newsItem['code'],
		'CATEGORY_NAME' => Scrub::text($folder->getName()),
		'DATETIME_PUBLISHED' => $publishedDate->getLocalShort(),
		'DATETIME_EXPIRES' => $expiryDate->getLocalShort(),
    );
						
	$this->setVar($output);
	
	$this->parse('delete_button_block', 'delete_button');
	
}
else if($this->arg[0] == 'new' && is_numeric($this->arg[1])) {
	
	/* Create a new news item */
	try{
		$folder = FolderMapper::getInstance()->findById($this->arg[1]);
	} catch (\Schoolbox\Mapper\Exception $e) {
		error(23);
	}

	if (($permission = $groupHandler->getPermission($this->arg[1])) < WRITE_ACCESS)
		error(23);

			
	$this->setFile(TEMPLATE_content, 'news/homepage.html');
	$this->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');
	
	$this->setTitle('Create Homepage News');
	$this->setTab('resources');

    /* Default DateTime */
	$publishedDate = new Alaress_Date_Format(time(), 'U');
	$expiryDate = new Alaress_Date_Format(strtotime(Cfg::get('homepage_news_expiry')), 'U');

    /* Set default output */
	$output = array(	'ACTION'				=> 'Create',
						'HEADING'				=> '',
						'BODY'					=> '',
						'NEWS_ID'				=> '',
						'CATEGORY_ID'			=> $this->arg[1],
						'CATEGORY_NAME'			=> scrub::Text($folder->getName()),
						'delete_button_block'	=> '',
		                'DATETIME_PUBLISHED' => $publishedDate->getLocalShort(),
		                'DATETIME_EXPIRES' => $expiryDate->getLocalShort(),
					);
						
	$this->setVar($output);
	
	
}
else
	error(2);