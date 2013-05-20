<?php
/**
 * Import a bunch of ePortfolio pre-defined templates.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once('../../../include/Bootstrap.php');

$allTemplates = new \Schoolbox\Collection();

$allTemplates->add(
	array(
		'name' => 'About Me',
		'leftColumnWidth' => 69.9,
		'rightColumnWidth' => 29.9,
		'components' => array(
			array(
				'class' => 'Component_Eportfolio_TextboxController',
				'keys' => array(
					'componentname' => 'Values and Beliefs',
					'description' => 'Describe what is important to you and what you believe in',
					'position' => 'left',
					'sequence' => 1,
				),
			),
			array(
				'class' => 'Component_Eportfolio_AdvancedListController',
				'keys' => array(
					'componentname' => 'My Favourite Things',
					'description' => 'Enter a list of your most important possessions, provide photos if you would like',
					'position' => 'left',
					'sequence' => 2,
				),
			),
			array(
				'class' => 'Component_Eportfolio_AdvancedListController',
				'keys' => array(
					'componentname' => 'Places I\'ve been',
					'description' => 'Enter a list of all your holiday destinations and travel',
					'position' => 'right',
					'sequence' => 1,
				),
			),
			array(
				'class' => 'Component_Eportfolio_BasicListController',
				'keys' => array(
					'componentname' => 'Co-curricular Activities',
					'description' => 'List all your sports, clubs and camps that you\'re involved with',
					'position' => 'right',
					'sequence' => 2,
				),
			),
			array(
				'class' => 'Component_Eportfolio_BasicListController',
				'keys' => array(
					'componentname' => 'My Hobbies',
					'description' => 'Provide a list of all your interests and hobbies that you undertake in your spare time',
					'position' => 'right',
					'sequence' => 3,
				),
			),
		),
	)
);

$allTemplates->add(
	array(
		'name' => 'Career Aspirations',
		'leftColumnWidth' => 69.9,
		'rightColumnWidth' => 29.9,
		'components' => array(
			array(
				'class' => 'Component_Eportfolio_BlogController',
				'keys' => array(
					'componentname' => 'My Career Aspirations',
					'description' => 'Provide a regular personal journal detailing what you would like to achieve in the future and how you will achieve your goals',
					'position' => 'left',
					'sequence' => 1,
				),
			),
			array(
				'class' => 'Component_Eportfolio_BasicListController',
				'keys' => array(
					'componentname' => 'My Goals',
					'description' => 'List your career goals and the steps required to achieve your dream career',
					'position' => 'right',
					'sequence' => 1,
				),
			),
			array(
				'class' => 'Component_Eportfolio_ImageListController',
				'keys' => array(
					'componentname' => 'Images that inspire me',
					'description' => 'Post some images that represent the career that you aspire to and what you would like to achieve',
					'position' => 'right',
					'sequence' => 2,
				),
			),
		),
	)
);

$allTemplates->add(
	array(
		'name' => 'My Resume',
		'leftColumnWidth' => 69.9,
		'rightColumnWidth' => 29.9,
		'components' => array(
			array(
				'class' => 'Component_Eportfolio_TextboxController',
				'keys' => array(
					'componentname' => 'Introduction',
					'description' => 'Provide a brief overview of who you are and skills that you believe you possess',
					'position' => 'top',
					'sequence' => 1,
				),
			),
			array(
				'class' => 'Component_Eportfolio_TextboxController',
				'keys' => array(
					'componentname' => 'My Dream Job',
					'description' => 'Explain what it is you are looking for in the perfect job',
					'position' => 'left',
					'sequence' => 1,
				),
			),
			array(
				'class' => 'Component_Eportfolio_ImageListController',
				'keys' => array(
					'componentname' => 'Photos of me',
					'description' => 'Post some photos that represent you and your interests',
					'position' => 'left',
					'sequence' => 2,
				),
			),
			array(
				'class' => 'Component_Eportfolio_TextboxController',
				'keys' => array(
					'componentname' => 'Work Experience',
					'description' => 'Enter any work experience that you have completed, for example babysitting or mowing the lawns',
					'position' => 'left',
					'sequence' => 3,
				),
			),
			array(
				'class' => 'Component_Eportfolio_BasicListController',
				'keys' => array(
					'componentname' => 'Hobbies',
					'description' => 'Provide a list of all your interests and hobbies that you undertake in your spare time ',
					'position' => 'right',
					'sequence' => 1,
				),
			),
		),
	)
);

$allTemplates->add(
	array(
		'name' => 'My Learning',
		'leftColumnWidth' => 69.9,
		'rightColumnWidth' => 29.9,
		'components' => array(
			array(
				'class' => 'Component_Eportfolio_BlogController',
				'keys' => array(
					'componentname' => 'My Reflections Blog',
					'description' => 'Provide a regular update on your work and school life',
					'position' => 'left',
					'sequence' => 1,
				),
			),
			array(
				'class' => 'Component_Eportfolio_TextboxController',
				'keys' => array(
					'componentname' => 'My Goals',
					'description' => 'List your goals that you would like to achieve, both in the short term and long term',
					'position' => 'left',
					'sequence' => 2,
				),
			),
			array(
				'class' => 'Component_Eportfolio_AdvancedListController',
				'keys' => array(
					'componentname' => 'Awards and Achievements',
					'description' => 'As you achieve your goals or are recognised for your efforts store a list here',
					'position' => 'right',
					'sequence' => 1,
				),
			),
		),
	)
);

$templateMapper = new \Eportfolio\TemplateMapper();
$templateMapper->import($allTemplates);


