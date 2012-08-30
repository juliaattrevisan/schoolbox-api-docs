<?php
// gets teachers involved in subject, outputs links to their addressbook page
$this->setBlock($componentHandle, "person", "person_block");
$this->setBlock('person', 'has_portrait', 'has_portrait_block');
$this->setBlock('person', 'has_group', 'has_group_block');
$this->setBlock('config', 'displayportraits', 'displayportraits_block');

$this->setVar('displayportraits_block', '');
$this->setVar('has_portrait_block', '');
$this->setVar('has_group_block', '');
$this->setVar('person_block', '');

/* Display options
 */
$chosenStyle = array(
	'name' => 'Grid',
	'value' => 'grid',
	'css' => 'thumb',
);

$allOptions = array(
	$chosenStyle,
	array(
		'name' => 'List',
		'value' => 'list',
		'css' => 'bar',
	),
);

foreach($allOptions as $currentOption) {
	$this->setVar(array(
		'COMPONENT_CONFIG_DISPLAYPORTRAITS_VALUE' => $currentOption['value'],
		'COMPONENT_CONFIG_DISPLAYPORTRAITS_NAME' => $currentOption['name'],
		'COMPONENT_CONFIG_DISPLAYPORTRAITS_SELECTED' => $currentOption['value'] == $config['displayPortraits'] ? ' selected="selected"' : '',
	));

	if ($currentOption['value'] == $config['displayPortraits']) {
		$chosenStyle = $currentOption;
	}

	$this->parse('displayportraits_block', 'displayportraits', true);
}

/* $code can be empty when added for the first time
 *
 * Find out what the class code is first from the posted cid
 */
if ($code == '') {
	$categoryHandler = new Category();

	$category = $categoryHandler->getByID($_POST['cid']);
	$code = $category['code'];
}

/* Continue normally
 */
if (($code != '') && Cfg::get('external_enable')) {
	$externalClassHandler = new External_Class();
	$userHandler = new User();
    $groupHandler = new Group();

	$listSyns = $externalClassHandler->getTeacherName($code);
    $staffGroups = $groupHandler->getStaffGroupTypeIds();

	if (is_array($listSyns)) {
		foreach($listSyns as $synid) {
			$teacher = $userHandler->getBySynergyAndGroup($synid, $staffGroups);
			$meta = $userHandler->getByID($teacher['uid']);

			if (is_array($teacher)) {
				$name = $userHandler->formatName($teacher);

				$this->setVar(array(
					'PERSON_UID' => $teacher['uid'],
					'PERSON_NAME' => $teacher['firstname'] . ' ' . $teacher['lastname'],
					'PERSON_EXTERNAL_ID' => $synid,
					'PERSON_GROUP' => $meta['gname'],
					'LIST_STYLE' => $chosenStyle['css'],
				));

				/* Don't display images in list view
				 */
				if ($chosenStyle['value'] == 'grid') {
					$this->parse('has_portrait_block', 'has_portrait');
					$this->parse('has_group_block', 'has_group');
				}
				else {
					$this->setVar('has_portrait_block', '');
					$this->setVar('has_group_block', '');
				}

				$this->parse('person_block', 'person', true);

				/* Clear children after parsing out the parent
				 */
				$this->setVar('has_portrait_block', '');
				$this->setVar('has_group_block', '');
			}
		}
	}
}

