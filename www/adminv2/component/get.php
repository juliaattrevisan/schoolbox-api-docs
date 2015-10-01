<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

// require SitePage context
if (!isset($this)) {
    require_once('../../../include/Bootstrap.php');
    httpError(400, 101); // resource not available
}

$this->setLite(true);

$context = $this->arg[0];
$groupId = $this->arg[1];

if (empty($context) || empty($groupId)) {
    httpError(400, 2); // info missing
}
$componentFactory = buildComponentFactory($context, new Schoolbox\User(), $this);
if (!$componentFactory) {
    httpError(400, 'Invalid context specified.');
}
$componentCollection = $componentFactory->buildComponentList($groupId);

$renderer = new Component_Renderer();
$renderer->renderAllAdminComponents($this, $componentCollection);

/**
 * Factory function to produce...uh, a Factory -_-
 * @param $context
 * @param Schoolbox\User $user
 * @param SitePage $sitepage
 * @return Component_FactoryAbstract|null
 */
function buildComponentFactory($context, Schoolbox\User $user, SitePage $sitepage)
{
    $contextMap = array(
        'dashboard' => false,
        'homepage' => true,
        'eportfolio' => true
    );

    if (isset($contextMap[$context])) {
        $class = sprintf('Component_%s_Factory', ucfirst(strtolower($context)));

        if ($contextMap[$context]) {
            return new $class($user, $sitepage, true);
        } else {
            return new $class($user, $sitepage);
        }
    }
    return null;
}
