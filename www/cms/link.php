<?php

require_once '../library/group.php';
$groupHandler = new Group;

if(is_numeric($this->arg[0])) {

	require_once '../library/link.php';
	$linkHandler = new Link;

	/* Modify existing link */
	$link = $linkHandler->getByID($this->arg[0]);
	if(!is_array($link))
		error(41);

	/* Check permissions to submit */
	if($groupHandler->getPermission($link['category']) < WRITE_ACCESS )
		error(6);

	$this->setFile(TEMPLATE_content, 'cms/modify-link.html');

	$this->setTitle('Modify Link');
	$this->setTab('resources');

	$output = array('ACTION'			=> 'Modify',
					'LINK_ID'			=> $link['id'],
					'URL'				=> $link['url'],
					'DESCRIPTION'		=> Scrub::text($link['description']),
					'COMMENT'			=> Scrub::text($link['comment']),
					'CATEGORY_NAME'		=> Scrub::text($link['categoryName']),
					'CATEGORY_ID'		=> $link['cid'],
					'HIDDEN_SELECTED'	=> 	$link['hidden'] > 0 ? 'checked="checked"' : '',
					);
	$this->setVar($output);


} else if($this->arg[0] == 'new' && is_numeric($this->arg[1])) {

	require_once '../library/category.php';
	$categoryHandler = new Category;

	/* Add new link to system */
	$this->setFile(TEMPLATE_content, 'cms/modify-link.html');
	$this->setTitle('Add Link');
	$this->setTab('resources');
    $this->setBlock(TEMPLATE_content, 'delete_button','delete_button_block');
    $this->setVar('delete_button_block','');

	/* Check folder exists */
	$category = $categoryHandler->getByID($this->arg[1]);
	if(!is_array($category))
		error(13);

	/* Check permissions to submit */
	if($groupHandler->getPermission($category['id']) < WRITE_ACCESS )
		error(6);

	$output = array('ACTION'			=> 'Add',
					'LINK_ID'			=> '',
					'URL'				=> '',
					'DESCRIPTION'		=> '',
					'COMMENT'			=> '',
					'HIDDEN_SELECTED'	=> '',
					'CATEGORY_NAME'		=> Scrub::text($category['name']),
					'CATEGORY_ID'		=> $category['id'],
					);
	$this->setVar($output);

}
else if($this->arg[0] == 'multicat') {
	/* Upload a link to multiple categories */
	require_once '../library/group.php';

	$this->setFile(TEMPLATE_content, 'cms/add-link-multi-cat.html');
	$this->setBlock(TEMPLATE_content, 'category_list', 'category_list_block');

	$this->setTitle('Upload a Link to Multiple Locations');
	$this->setTab('resources');

	$groupHandler = new Group;
	$categoryList = $groupHandler->involvedCategoryList($_SESSION['id'], $_SESSION['subjects'], 2);

	if(is_array($categoryList)) {
		foreach($categoryList as $category) {
			$name = $category['name'];

			/* Optionally append the categoryCode if one exists
			 */
			if (strlen(trim($category['code']))) {
				$name .= ' (' . $category['code'] . ')';
			}

			$this->setVar(array(
				'CATEGORY_NAME'	=> Scrub::text($name),
				'CATEGORY_ID' => $category['id'],
			));

			$this->parse('category_list_block', 'category_list', true);
		}
	}
}
else {
	error(2);
}

?>
