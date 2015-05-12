<?php
/**
 * search.php
 *
 * Spawns search results for all minions
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once('../include/Bootstrap.php');

/* Old handler junk
 */
require_once('./resource/searchConfig.php');

/* Clean params
 */
require_once('./resource/searchClean.php');

/**
 * Execute searches for all handlers and mappers
 */

/* Iterate through the handle types
 *
 * These are used to generate counts for the menu tabs, for handler-based items.
 * Mapper-based ones do it all internally within the \Search\Results composite,
 * avoiding the global $count var (silly old bugalugs, silly old bucket)
 *
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
$mapper = new \Schoolbox\Resource\Search\Results($page, $type);
$mapper->search( $_SESSION['user'], $keyword, 0, Cfg::get('search_all_limit'));

/* Omaha Beach
 *
 * There's a sequential dependency here, which prevents us from moving
 * up the beach in a nice way. 2RS uses $count as a global var, a terrible
 * move, and it's even more annoying to use a $temp var to counter it
 */
$count['all'] += $mapper->countTotalResults();

/* If no result, render error screen
 */
if (!$count['all']) {
    $this->getPage()->setFile('no_results', 'resource/search/noResults.html');

    $this->getPage()->setVar(array(
        'ENCODED_KEYWORD' => $this->getKeyword(),
        'UNESCAPED_KEYWORD' => $this->getKeyword(),
        'ENABLED' => AJAX_DISABLED,
    ));

    $this->getPage()->parse('RESULT', 'no_results', true);
} else {
    /* Omaha Beach
     *
     * These have landed
     * When you add more, remove them from the searchConfig pseudo-registry
     * and remove them from the gigantic phplibbery below
     *
     * Everything here is self-contained
     */
    $mapper->render();

    /* These are old handlers
     *
     * Once you convert them to Mappers, remove their renderer, and remove them
     * from the searchConfig lists.
     *
     * Note that once you convert all to Mappers, you'll have more freedom in
     * choozing where their respective html files shall live. At the moment they
     * are tied in to the keys in the array, which really isn't nice.
     */
    if (!empty($handle[$type])) {
/* Particular ordering and sorting for Categories
         */
        if ($type != 'all') {
            // perform search for individual category (without formatting for ajax call)
            $result[$type] = $handle[$type]['handler']->search($keyword, ($current_page - 1) * Cfg::get('search_type_limit'), Cfg::get('search_type_limit'), $count[$type]);
        }
    }

    /* Dump them
     */
    $oldHandlerRenderers = new \Schoolbox\Collection();
    $oldHandlerRenderers->add(new \Schoolbox\User\Search\Renderer($page, $result, $count, 0));
    $oldHandlerRenderers->add(new \Schoolbox\File\Search\Renderer($page, $result, $count, 0));
    $oldHandlerRenderers->add(new \Schoolbox\News\Search\Renderer($page, $result, $count, 0));
    $oldHandlerRenderers->add(new \Schoolbox\Folder\News\Search\Renderer($page, $result, $count, 0));
    $oldHandlerRenderers->add(new \Schoolbox\Blog\Search\Renderer($page, $result, $count, 0));
    $oldHandlerRenderers->add(new \Schoolbox\Wiki\Search\Renderer($page, $result, $count, 0));
    $oldHandlerRenderers->add(new \Schoolbox\Forum\Search\Renderer($page, $result, $count, 0));
    $oldHandlerRenderers->add(new \Schoolbox\Calendar\Event\Search\Renderer($page, $result, $count, 0));

    /* Draw them
     */
    foreach ($oldHandlerRenderers as $currentRenderer) {
        $currentRenderer->setUseSeymourLinks(true);
        $currentRenderer->render();
    }
}

$page->parse(TEMPLATE_body, 'RESULT', true);
$page->outputPage(true);

exit();
