<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Calendar\Event\Type\Day\Renderer;

use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Class Html
 *
 * Render a event's details into day.twig
 *
 * This appears in dashboard and homepage calendar components and anything before the Vue Calendar
 *
 * The creation of querystring params is presumptive in that returnTo is expected to now be the first parameter
 * because we rebuilt the Modify Event url
 *
 * @package Calendar\Event\Type\Day\Renderer
 */
class Html extends Base
{

    /**
     * Render this event as html using twig
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function render(): string
    {

        $returnUrl = $_SERVER['REQUEST_URI'];

        $path = explode('/', $returnUrl);

        $returnStr = '?returnTo=';

        if ($returnUrl === '/') {
            $returnStr .= 'dashboard';
        } elseif ($path[1] == 'homepage' && (int)$path[2] > 0) {
            $returnStr .= 'homepage&homepageId=' . $path[2];
        } elseif ($path[1] === 'calendar') {
            $returnStr .= 'calendar';
        } elseif (isset($this->returnStr)) {
            $returnStr .= $this->returnStr;
        } else {
            //Return the URL as is
            $returnStr .= urlencode($returnUrl);
        }

        return $this->getTemplate()->render('calendar/type/day.twig', [
            'event'     => $this->getEvent(),
            'renderer'  => $this,
            'returnStr' => $returnStr,
        ]);
    }
}
