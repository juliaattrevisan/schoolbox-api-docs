<?php
/**
 * The base context for all context classes (even the feature context!)
 *
 * Defines all sorts of useful generic methods
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Behat\Mink\Element;
use Behat\MinkExtension\Context\MinkContext;
use Behat\MinkExtension\Context\RawMinkContext;

abstract class BaseContext extends RawMinkContext {

    /**
     * @return Subcontext_CoreContext
     */
    public function getCoreContext() {
        return $this->getMainContext()->getSubcontext('core');
    }

    /**
     * @return MinkContext
     */
    public function getMinkContext() {
        return $this->getMainContext()->getSubcontext('mink');
    }

    /**
     * Gets the beginning text out of a node (presuming it starts with text)
     *
     * @example Calling this on a node containing "some text <div> blah</div>"
     *          returns "some text"
     *
     * @param  Element\Element $node
     * @return string
     */
    protected function getBeginningText(Element\Element $node) {
        return htmlspecialchars_decode(trim(preg_replace('/<.*/', '', $node->getHtml())));
    }

    /**
     * Switches off all jQuery animation - all transiations now run immediately.
     * Required for some tests when using Selenium (which doesn't work when
     * manipulating invisible elements - what if an element is transitioning
     * from visible to invisible?)
     */
    protected function disableAnimation() {
        $this->getSession()->executeScript('jQuery.fx.off = true');
    }

    /**
     * Sends a POST request through jQuery.
     *
     * @param string $url       The URL to post to.
     * @param array  $post_vars An array of post variables to send.
     */
    protected function ajaxPost($url, $post_vars) {
//        $this->printDebug(sprintf('Post to "%s"', $url));
        $this->getSession()->executeScript(
            sprintf('jQuery.post(%s, %s)', json_encode($url), json_encode($post_vars))
        );
    }

    /**
     * Attempts to run $callback($this) once per second for $timeout seconds.
     * Returns true as soon as this callback returns true.
     * Any exceptions the callback throws are ignored.
     *
     * @see http://docs.behat.org/cookbook/using_spin_functions.html
     *
     * @param  callable  $callback
     * @param  int       $timeout
     * @throws Exception if $timeout is reached and the callback still hasn't
     *                   returned true.
     * @return bool
     */
    protected function spin($callback, $timeout = 15) {
        $finish_time = microtime(true) + $timeout;
        do {
            try {
                if ($callback($this)) {
                    return true;
                }
            }
            catch (Exception $e) {
                // do nothing
            }
            // Try again in 100 milliseconds (100 * 10e3 microseconds)
            usleep(100 * 10e3);
        }
        while (microtime(true) < $finish_time);

        $backtrace = debug_backtrace();

        throw new Exception(
            "Timeout thrown by " . $backtrace[1]['class'] . "::" . $backtrace[1]['function'] . "()\n" .
            $backtrace[1]['file'] . ", line " . $backtrace[1]['line']
        );
    }
}
