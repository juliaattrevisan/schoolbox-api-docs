<?php
/**
 * Matches a path from a router against a list of regexps and corresponding
 * callbacks, and runs the callback for each regexp the path matches.
 *
 * The callback can adjust the route's params or throw an exception of some sort.
 *
 * This is a generic form of Alaress\Authorizer\Authorizer.
 *
 * I could use this to inject the user into the params as well, and use the item
 * and user in auth checks.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Web\Router;

use Alaress\Module\Loader as ModuleLoader;

class PathMatcher {

    /**
     * A list of rules that have been defined.
     *
     * @var array
     */
    protected $ruleList = array();

    /**
     * Module loader
     *
     * @var ModuleLoader
     */
    protected $moduleLoader;

    /**
     * Constructor
     *
     * @param ModuleLoader $moduleLoader
     */
    public function __construct(ModuleLoader $moduleLoader)
    {
        $this->moduleLoader = $moduleLoader;
    }

    /**
     * Adds a rule for a path.
     *
     * @example
     * <code>
     * $pathMatcher->add('/^.*$/', function($params) {
     *     // set up session, and get user out of it
     *     // This augments $params with 'user' key
     *     return array('user' => $user);
     * });
     * $pathMatcher->add('/^resourceItem\\.read$/', function($params) use($itemMapper) {
     *     // This augments $params with 'item' key
     *     return array('item' => $item->findById($params['id']));
     * });
     * $pathMatcher->add('/^resourceItem\\.edit$/', function($params) use($itemMapper) {
     *     // This reads both $item and $user, and throws exception if $user
     *     // can't read item
     *     if ($item->getOwner()->getId() != $user->getId()) {
     *         throw new AuthException('User isn\'t owner of item');
     *     }
     * });
     * </code>
     *
     * @param string $pathMatch A regexp to match a path against
     * @param mixed  $action    The rule to check against.
     * @param array  $tags      Any tags to give this action.
     *                          Tags to run may be specified in $this->check()
     * @return $this
     */
    public function add($pathMatch, $action, $tags = array()) {
        $this->ruleList[] = array(
            'pathMatch' => $pathMatch,
            'action'    => $action,
            'tags'      => $tags,
        );
        return $this;
    }

    /**
     * Authorizes whether a user may access a given path.
     *
     * @param  string $path   The path to check. Often refers to a controller
     *                        name, which may have been determined by a route
     *                        matched by Aura\Router\Router->match().
     * @param  array  $params Any params to pass as part of the route.
     * @param  array  $tags   If provided, run only actions added with one of
     *                        these tags.
     * @return array
     */
    public function check($path, $params, $tags = array()) {
        // First load the module
        $this->moduleLoader->loadModule($path);
        if (!is_array($tags)) {
            $tags = array($tags);
        }
        $usesTags = count($tags) > 0;
        // Anything after the : in a path is ignored.
        // (example: "update:post" matches all rules "update" does)
        $mainPath = preg_replace('/:.*$/', '', $path);
        foreach ($this->ruleList as $rule) {
            // Don't run this rule if we're only running items with a tag.
            if ($usesTags && count(array_intersect($tags, $rule['tags'])) === 0) {
                continue;
            }
            if (preg_match($rule['pathMatch'], $mainPath)) {
                $action = $rule['action'];

                // Did the callback return anything? update $params if it did
                $result = $this->getActionResult($action, $params);
                if (is_array($result)) {
                    $params = array_merge($params, $result);
                }
            }
        }
        return $params;
    }

    /**
     * Gets the result of calling an action.
     *
     * @param  callable $callback
     * @param  array    $params
     * @return array    An array to override $params with, or null to leave
     *                  untouched.
     */
    protected function getActionResult($callback, $params) {
        return call_user_func($callback, $params);
    }

}
