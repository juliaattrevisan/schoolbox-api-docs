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
        return array('Content-Type: text/xml; charset=UTF-8');
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

        /*
        echo "TWIG NOT, WANT NOT";
        return;
        */

        $skin = $response->getPage('skin');

        // register Twig
        $twigLoadPath = Config::get('siteRoot') . '/include/Twig/Autoloader.php';
        require_once $twigLoadPath;
        Twig_Autoloader::register();

        // override the template if an error needs to be presented
        if($response->hasError()) {
            switch($response->getStatusCode()) {
                case Response_HTTP::HTTP_NOT_FOUND:
                    $response->overrideTemplate("templates/$skin/common/error.html");
                    break;
                case Response_HTTP::HTTP_UNAUTHORIZED:
                case Response_HTTP::HTTP_BAD_REQUEST:
                case Response_HTTP::HTTP_FORBIDDEN:
                case Response_HTTP::HTTP_SERVER_ERROR:
                default:
                    //$response->overrideTemplate('templates/core/common/error.html');
                    break;
            }
        }

        // TODO: should this be  /templates/{school}/mobile/  or  /templates/mobile/{school}/

        $dirs = array(
            //Config::get('siteRoot') . "/templates/$skin/include/",
            //Config::get('siteRoot') . "/templates/core/include/"
        );

        // if the template has been overridden
        if($response->isTemplateOverridden()) {
            $template = Config::get('siteRoot') . '/' . $response->getTemplate();
            $dirs[] = dirname($template);
            $page = basename($template);
        }

        // no template override
        else {
            $tdir = Config::get('siteRoot') . '/app/' . str_replace('_', '/', $response->getCore('className'));
            $dirs[] = "$tdir/templates/$skin/";
            $dirs[] = "$tdir/templates/core/";
            $dirs[] = "$tdir/templates/";
            $page = $response->getCore('methodName') . '.html';
        }

        // iterate over the $dirs array and ignore directories that don't exist because Twig will get upset
        $templatePaths = array();
        for($i = 0; $i < count($dirs); $i++) {
            if(is_dir($dirs[$i])) {
                $templatePaths[] = $dirs[$i];
            }
        }

        Debug::add('Template paths = ' . print_r($templatePaths, true));

        try {
            // instantiate Twig with template path
            $loader = new Twig_Loader_Filesystem($templatePaths);

            $twig = new Twig_Environment($loader);
            $twig->addExtension(new Twig_Extension_Debug());

            // insert the content html name config into the rdo

            // render the output and execute
            $response->sendHeaders();
            echo $twig->render($page, $response->getData());
        }

            // could not load the templates. Fall back to something of a default nature...
        catch (Twig_Error_Loader $e) {
            // don't recommend a redirect here, but can't think of anything better
            // ...go home
            die('TRY PREPENDING core. TO THE URL: ' . $e);
            $response->addInternalRedirectHeader('mobile');
            $response->sendHeaders();
        }

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
