<?php
require_once('./resource/searchConfig.php');
require_once('./resource/searchClean.php');

/**
 * Execute searches for all handlers and mappers
 */
// iterate through the handle types
foreach ($handle as $type_name => &$type_handle) {
    // include & instantiate
    $type_class = ucfirst($type_handle);
    $type_handle = new $type_class;

    /* search() calls access a global counter array, $count
     */
    $result[$type_name] = $type_handle->search($keyword, 0, Cfg::get('search_all_limit'), $count[$type_name]);
}

$count['all'] = array_sum($count);

/* Omaha Beach!
 */
$mapper = new \Schoolbox\Resource\Search\Results($this, $type);
$mapper->search( $_SESSION['user'], $keyword, 0, Cfg::get('search_all_limit'));

/* Omaha Beach
 *
 * There's a sequential dependency here, which prevents us from moving
 * up the beach in a nice way. 2RS uses $count as a global var, a terrible
 * move, and it's even more annoying to use a $temp var to counter it
 */
$count['all'] += $mapper->countTotalResults();

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

/* Initialise blocks
 */
$this->setVar(array(
    'type_block' => '',
    'result_block' => '',
    'no_result_block' => '',
    'NO_RESULTS_BECAUSE' => '',
    'FOR' => ' for ',
    'ENABLED' => 1,
));

/* Special error case
 * Search keyword must be > 2 characters long, if not int
 *
 * test the search as to why it failed, and return meaningful
 * response to the user
 */
$enabled = AJAX_ENABLED;

if (!is_numeric($keyword) && (strlen($keyword) == 0)) {
    $enabled = AJAX_DISABLED;

    $this->setVar(array(
        'FOR' => '',
        'NO_RESULTS_BECAUSE' => ' because it\'s empty',
        'ENABLED' => AJAX_DISABLED,
    ));
}
elseif (!is_numeric($keyword) && (strlen($keyword) < MIN_KEYWORD_LENGTH)) {
    $enabled = AJAX_DISABLED;

    $this->setVar(array(
        'NO_RESULTS_BECAUSE' => ' because it needs to be longer, or wrapped in "quotes"',
        'ENABLED' => AJAX_DISABLED,
    ));

    $this->parse('no_result_block', 'no_result');
}
/* Also parse the no_result block if no results were found
 */
elseif ($count['all'] == 0) {
    $enabled = AJAX_DISABLED;

    $this->setVar(array(
        'NO_RESULTS_BECAUSE' => '',
        'ENABLED' => AJAX_DISABLED,
    ));

    $this->parse('no_result_block', 'no_result');
}

// setup default values
// escape any quotes in the keyword string, or the javascript will explode.
$this->setVar(array(
    'KEYWORD' => str_replace('"', '\"', $keyword),
    'ENCODED_KEYWORD' => htmlentities($keyword),
    'UNESCAPED_KEYWORD' => $keyword,
));

/**
 * phpliberalise the menu tabs
 * render tabs "All" and any with a search result count
 */
if ($enabled) {
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
     * positive result counts for their respective items
     */
    $mapper->renderTabs();
}

/**
 * End
 *
 * Render search outputs
 * These are ajaxed in individually
 */

