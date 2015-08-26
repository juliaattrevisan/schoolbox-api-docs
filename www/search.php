<?php
require_once('./resource/searchConfig.php');
require_once('./resource/searchClean.php');

/**
 * Execute searches for all handlers and mappers
 */
if ($type == '') {
    $type = 'all';
}

/* iterate through the handle types
 * search() calls access a global counter array, $count
 */
foreach ($handle as $typeName => $config) {
    if ($typeName != 'all') {
        $result[$typeName] = $config['handler']->search($keyword, 0, Cfg::get('search_all_limit'), $count[$typeName]);
    }
}

$count['all'] = array_sum($count);

/* Omaha Beach!
 */
$mapper = new \Schoolbox\Resource\Search\Results($this, $type, $keyword);
$mapper->search( $_SESSION['user'], $keyword, 0, Cfg::get('search_all_limit'), 'score');

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
$this->setBlock(TEMPLATE_content, 'has_options', 'has_options_block');
$this->setBlock('has_options', 'type', 'type_block');
$this->setBlock('has_options', 'result', 'result_block');
$this->setBlock(TEMPLATE_content, 'no_result', 'no_result_block');

/* Initialise blocks
 */
$this->setVar(array(
    'type_block' => '',
    'result_block' => '',
    'no_result_block' => '',
    'NO_RESULTS_BECAUSE' => '',
    'FOR' => ' for ',
    'ENABLED' => AJAX_ENABLED,
    'RESULT' => '',
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

    $this->parse('no_result_block', 'no_result');
    $this->setVar(array(
        'has_options_block' => ''
    ));
} elseif (!is_numeric($keyword) && (strlen($keyword) < MIN_KEYWORD_LENGTH)) {
    $enabled = AJAX_DISABLED;

    $this->setVar(array(
        'NO_RESULTS_BECAUSE' => ' because it needs to be longer, or wrapped in "quotes"',
        'ENABLED' => AJAX_DISABLED,
    ));

    $this->parse('no_result_block', 'no_result');
    $this->setVar(array(
        'has_options_block' => ''
    ));

} /* Also parse the no_result block if no results were found
 * when using the default 'all' selection
 */
elseif ($count['all'] == 0) {
    $enabled = AJAX_DISABLED;

    $this->setVar(array(
        'NO_RESULTS_BECAUSE' => '',
        'ENABLED' => AJAX_DISABLED,
    ));

    $this->parse('no_result_block', 'no_result');
    $this->setVar(array(
        'has_options_block' => ''
    ));
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
 */
if ($enabled) {
    /* Always render all tabs, hidden by default
     * Upon ajax completion, populate the tabs with counts and
     * phplib the result html fragment
     */
    foreach ($handle as $typeName => $config) {
        /* Render "All" tab option always
         * Unholy mess
         */
        if ($typeName == 'all') {
            $this->setVar(array(
                'TYPE_TYPE' => 'all',
                'TYPE_NAME' => 'All',
                'TYPE_CLASS' => '',
                'TYPE_COUNT' => $count[$typeName],
            ));

            $this->setVar(array(
                'RESULT_TYPE' => 'all',
            ));

            $this->parse('type_block', 'type', true);
            $this->parse('result_block', 'result', true);
        } else {
            $config['renderer']->renderTab($count);
        }
    }

    /* Omaha Beach
     * The individual renderers should only render tabs when there are
     * positive result counts for their respective items
     */
    $mapper->renderTabs();

    $this->parse('has_options_block', 'has_options');
}

/**
 * End
 *
 * Render search outputs
 * These are ajaxed in individually
 */
