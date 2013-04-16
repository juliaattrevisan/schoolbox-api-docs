<?php
/**
 *
 * This class is similar in its nature to View_HTML, but its purpose is to return the entire site with all skinning
 * and functionality, rather than just a content-page or component.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class View_Site implements View_Interface {

    /**
     * Implementing classes must indicate their default headers to be sent
     * The assumption is that default can be overridden
     */
    public function getDefaultHeaders() {
        return array('Content-Type: text/html; charset=UTF-8');
    }

    /**
     * Implementing classes must be able to add headers to their default list
     * @param $header
     */
    public function addHeader($header) {
        // TODO: Implement addHeader() method.
    }

    /**
     * Implementing classes are responsible for sending their headers ahead of data output
     */
    public function sendHeaders() {
        foreach($this->getDefaultHeaders() as $header)
            header($header);
    }

    public function render(Response_HTTP $response) {

        /*
        echo "TWIG NOT, WANT NOT";
        return;
        */

        $response->setPage('siteRoot', Config::get('siteRoot'));

        $skin = $response->getPage('skin');


        // override the template if an error needs to be presented
        if($response->hasError()) {
            switch($response->getStatusCode()) {
                case Response_HTTP::HTTP_NOT_FOUND:
                    $response->overrideTemplate("templates/m/$skin/common/error");
                    break;
                default:
                    //$response->overrideTemplate('templates/core/common/error.html');
                    break;
            }
        }

        $dirs = array(
            Config::get('siteRoot') . "/templates/m/core/include/",
            Config::get('siteRoot') . "/templates/m/$skin/include/",
            Config::get('siteRoot') . "/templates/$skin/m/",
        );

        // if the template has been overridden, apply the default
        if($response->isTemplateOverridden()) {
            $template = Config::get('siteRoot') . '/' . $response->getTemplate();
            $dirs[] = dirname($template);
            $page = basename($template);
        }

        // use template override
        else {
            $tdir = Config::get('siteRoot') . '/app/' . str_replace('_', '/', $response->getCore('className'));
            $dirs[] = "$tdir/templates/$skin/";
            $dirs[] = "$tdir/templates/core/";
            $dirs[] = "$tdir/templates/";
            $page = $response->getCore('methodName');
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
            $response->setPage('include', $page);

            Debug::add("Page->include = $page");

            // render the output and execute
            $response->sendHeaders();
            echo $twig->render('main.html', $response->getData());
        }

            // could not load the templates. Fall back to something of a default nature...
        catch (Twig_Error_Loader $e) {
            // don't recommend a redirect here, but can't think of anything better
            // ...go home
            $response->sendHeaders();
            echo '<html><head><style type="text/css">body{background:#fee;font-family:monospace;}pre{background:#fff;margin:5px;padding:5px;border:solid 1px #F44;white-space:pre-wrap;}</style></head><body><h1>Twig Exception:</h1><pre>',
                nl2br($e),
                '</pre></body></html>';
            exit;
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
