<?php

require_once("news/whatsnew.php");
$whatnew = new WhatsNew();

$catList = involvedCategoryList($_SESSION['id'], $_SESSION['subjects'], 1);

$this->setBlock($component['name'], 'category_block', 'new_category');
$this->setBlock($component['name'], 'mail_block', 'new_mail_block');
$this->setBlock('category_block', 'due_work', 'due_block');
$this->setBlock('category_block', 'ret_work', 'ret_block');
$this->setBlock('category_block', 'sub_work', 'sub_block');
$this->setBlock('category_block', "new_news", "new_news_item"); 
$this->setBlock("category_block", "new_file", "new_file_item"); 
$this->setBlock("category_block", "new_url", "new_url_item"); 
$this->setBlock("category_block", "new_forum", "new_forum_item"); 
$flag = 0;

//check mail status
if(MAIL_enable && !(SITE_location == 'mgs' && isStaff($_SESSION['gid'])) ) {

	$mailbox = $whatnew->newEmail();
	if($mailbox->unseen > 0) {
		$this->setVar('NEW_MAIL', $mailbox->unseen);
		$this->parse('new_mail_block','mail_block'); 
	}
	else {
		$this->setVar('new_mail_block','');
	}
}
else
	$this->setVar('new_mail_block','');

//for each subject
if(is_array($catList))
{
	foreach($catList as $catInfo)
	{
		$sub = $whatnew->submissionDetails($catInfo['id']);
		if($sub['duework'])
			$this->parse('due_block', 'due_work');
		else
			$this->setVar('due_block', '');
		
		if($sub['retwork']) 
			$this->parse('ret_block', 'ret_work');
		else
			$this->setVar('ret_block', '');
			
		if(is_array($sub['subboxes'])) {
			foreach($sub['subboxes'] as $subid => $subdetails) {
				$subout = array('BOX_ID'	=> $subid,
								'BOX_NAME'	=> $subdetails['description'],
								'BOX_COUNT'	=> $subdetails['count'],
								);
				$this->setVar($subout);
			
				$this->parse('sub_block', 'sub_work');
			}
		}
		else
			$this->setVar('sub_block', '');

	
		$cat = $whatnew->newCategory($catInfo['id']);
		if($cat['news'])
		{
			if($cat['news'] > 1)
				$this->setVar('NEW_PLURAL_NEWS', 's');
			else
				$this->setVar('NEW_PLURAL_NEWS', '');
			$this->setVar("NEW_NEWS", $cat['news']);
			$this->parse("new_news_item", "new_news");
		}
		else
			$this->setVar("new_news_item", "");
			
		if($cat['files'])
		{
			if($cat['files'] > 1)
				$this->setVar('NEW_PLURAL_FILE', 's');
			else
				$this->setVar('NEW_PLURAL_FILE', '');
			$this->setVar("NEW_FILES", $cat['files']);
			$this->parse("new_file_item", "new_file");
		}
		else
			$this->setVar("new_file_item", "");
		
		if($cat['links'])
		{
			if($cat['links'] > 1)
				$this->setVar('NEW_PLURAL_LINK', 's');
			else
				$this->setVar('NEW_PLURAL_LINK', '');
			$this->setVar("NEW_URLS", $cat['links']);
			$this->parse("new_url_item", "new_url");
		}
		else
			$this->setVar("new_url_item", "");
		
		if($cat['forum'])
		{
			if($cat['forum'] > 1)
				$this->setVar('NEW_PLURAL_FORUM', 's');
			else
				$this->setVar('NEW_PLURAL_FORUM', '');
			$this->setVar("NEW_FORUM", $cat['forum']);
			$this->parse("new_forum_item", "new_forum");
		}
		else
			$this->setVar("new_forum_item", "");
		
		
		if($sub['duework'] || $sub['retwork'] || $sub['subboxes'] || $cat['news'] || $cat['files'] || $cat['links'] || $cat['forum'])
		{
			if($catInfo['code'])
				$this->setVar("NEW_CATEGORY_NAME", $catInfo['name'].' ('.$catInfo['code'].')');
			else
				$this->setVar("NEW_CATEGORY_NAME", $catInfo['name']);
			$this->setVar("NEW_CATEGORY_ID", $catInfo['id']);
			$this->parse("new_category", "category_block", true);
			$flag = 1;
		}
		
	}
}

if(!$flag)
{
	$this->setVar('new_category','');
	$this->setVar('POS'.$component['position'], '');
}
else
	$this->parse('POS'.$component['position'], $component['name']);
	
?>