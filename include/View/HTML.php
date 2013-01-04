<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class View_HTML implements View_Interface {

    public function getDefaultHeaders() {
        return array('Content-Type: text/html; charset=UTF-8');
    }

    public function addHeader($header) {

    }

    public function sendHeaders() {
        foreach($this->getDefaultHeaders() as $header)
            header($header);
    }

    public function render(Response_HTTP $response) {

        // imma cheat and grab JTML's response
        $jtml = new View_JTML();
        $output = $jtml->render($response, true);

        $this->sendHeaders();

        if(isset($output['page']) && is_array($output['page'])) {

            echo "<section>\n  <header>{$output['page']['title']}</header>\n";

            if(isset($output['page']['articles']) && is_array($output['page']['articles'])) {
                foreach($output['page']['articles'] as $a) {

                    if(isset($a['icon']) && isset($a['class']) && isset($a['title']) && isset($a['content'])) {
                        printf("  <article%s%s>\n    <h1>%s%s%s</h1>\n    %s  </article>\n",
                            $a['icon']            ? ' data-icon="' . $a['icon'] . '"'   : '',
                            $a['class']           ? ' class="' . $a['class'] . '"'      : '',
                            $a['title']           ? $a['title']                         : '',
                            isset($a['subtitle']) ? "<span>{$a['subtitle']}</span>"     : '',
                            $a['aside']           ? "<aside>{$a['aside']}</aside>"      : '',
                            $a['content']         ? $a['content']                       : ''
                        );
                    }
                }
            }
            echo "</section>";
        }

        die();

    }

    public function generatePage() {
        $vars = array();

        $vars['sidebar'] = array(
            'home' => 'lol',
            'foo'  => 'bar',
        );

        return $vars;
    }

    public function getName() {
        return get_called_class();
    }
}
