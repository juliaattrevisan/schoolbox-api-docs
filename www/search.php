<?php

$handle = array(
    'user' => 'user',
    'file' => 'file',
    'category' => 'category',
    'sitenews' => 'news',
    'homepagenews' => 'categoryNews',
    'blog' => 'blog',
    'wiki' => 'wiki',
    'forum' => 'forum',
    'event' => 'calendar',
);

$type_list = array(
    'all' => 'All',
    'user' => 'People',
    'file' => 'Files',
    'category' => 'Folders',
    'sitenews' => 'School News',
    'homepagenews' => 'Homepage News',
    'blog' => 'Blogs',
    'wiki' => 'Wikis',
    'forum' => 'Forums',
    'event' => 'Calendar',
);

/* Omaha Beach
 * These handlers have been mapperised and shouldn't appear in the
 * old search.php contexts
 */
$omaha = array(
    'link'
);

/* Request params
 */
$type = $_GET['type'];
$current_page = empty($_GET['page']) ? 1 : $_GET['page'];
$count = array();

// strip out any injection attempts
$keyword = trim(preg_replace('/[^\ A-Za-z0-9\"\'\+\-_=\@\?!\.]/', '', $this->arg[0]));

/**
 * Execute searches for all handlers and mappers
 */
// iterate through the handle types
foreach ($handle as $type_name => &$type_handle) {
    // include & instantiate
    $type_class = ucfirst($type_handle);
    $type_handle = new $type_class;

    $result[$type_name] = $type_handle->search($keyword, 0, Cfg::get('search_all_limit'), $count[$type_name]);
}

/* Omaha Beach!
 */
$mapper = new \Schoolbox\Resource\Search\Results($this, $type);
$mapper->search( $_SESSION['user'], $keyword, 0, Cfg::get('search_all_limit'));

/**
 * phpliberalise the basic template output
 * If there are any messages, display a message block (to be further phplibbed)
 */

/* Global search result page
 */
$this->setFile(TEMPLATE_content, 'search.html');
$this->setBlock(TEMPLATE_content, 'type', 'type_block');
$this->setBlock(TEMPLATE_content, 'result', 'result_block');
$this->setBlock(TEMPLATE_content, 'no_result', 'no_result_block');

if (is_numeric($keyword) || ($keyword && strlen($keyword) > 2)) {
    $this->setVar(array(
        'UNESCAPED_KEYWORD' => $keyword,
    ));
}
// test the search as to why it failed, and return meaningful response to the user
else {
    if (strlen($keyword) == 0) {
        $this->setVar(array(
            'FOR' => '',
            'NO_RESULTS_BECAUSE' => ' because it\'s empty'
        ));
    }
    elseif (strlen($keyword) < 3) {
        $this->setVar(array(
            'NO_RESULTS_BECAUSE' => ' because it needs to be longer, or wrapped in "quotes"'
        ));

        $this->parse('no_result_block', 'no_result');
    }
}

// setup default values
// escape any quotes in the keyword string, or the javascript will explode.
$output = array(
    'KEYWORD' => str_replace('"', '\"', $keyword),
    'ENCODED_KEYWORD' => htmlentities($keyword),
    'UNESCAPED_KEYWORD' => $keyword,
    'FOR' => ' for ',
    'NO_RESULTS_BECAUSE' => '',
    'type_block' => '',
    'result_block' => '',
    'no_result_block' => ''
);

$this->setVar($output);

/**
 * phpliberalise the menu tabs
 * render tabs "All" and any with a search result count
 */
foreach ($type_list as $type_name => $type_desc) {

    /* Always render "All" tab
     */
    if ($type_name != 'all') {

        /* Skip any tabs that have no results
         */
        if (!$count[$type_name]) {
            continue;
        }
    }

    /* Unholy mess
     */
    $this->setVar(array(
        'TYPE_COUNT' => $count[$type_name],
        'TYPE_TITLE' => $type_name,
        'TYPE_CLASS' => $count[$type_name] == 0 ? 'disabled' : '',
        'TYPE_NAME' => $type_desc
    ));

    $this->setVar(array(
        'RESULT_TITLE' => $type_name,
        'RESULT_CLASS' => $type_name == 'all' ? 'hide loaded' : 'hide',
    ));

    if ($type_name != 'all') {
        $this->setVar('RESULT');
    }

    $this->parse('type_block', 'type', true);
    $this->parse('result_block', 'result', true);
}

/* Omaha Beach
 * The individual renderers should only render tabs when there are
 * positve result counts for their respective items
 */
$mapper->renderTabs();
