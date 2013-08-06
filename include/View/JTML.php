<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class View_JTML implements View_Interface {

    private $headers = array();

    public function getDefaultHeaders() {
        return array('Content-Type: application/json; charset=UTF-8');
    }

    public function addHeader($header) {
        $this->headers[] = $header;
    }

    public function sendHeaders() {
        if(empty($this->headers)) {
            $this->headers = $this->getDefaultHeaders();
        }

        foreach($this->headers as $header) {
            header($header);
        }
    }

    public function render(Response_HTTP $response, $returnOutput = false) {

        $skin = $response->getPage('skin');

        // override the template if an error needs to be presented
        if($response->hasError()) {
            switch($response->getStatusCode()) {
                case Response_HTTP::HTTP_NOT_FOUND:

                    $jtml = array(
                        'page' => array(
                            'title' => "ERROR",
                            'articles' => array(array(
                                'title' => 'Page not found',
                                'aside' => '',
                                'class' => 'giza-error wide',
                                'icon' => 'error',
                                'content' => "
<ul>
  <li>Sorry, the page you're looking for can't be found.</li>
</ul>
"
                            ))
                        ),
                    );

                    if($returnOutput) {
                        return $jtml;
                    }
                    else {
                        $this->sendHeaders();
                        die(json_encode($jtml));
                    }

                    break;
                case Response_HTTP::HTTP_UNAUTHORIZED:
                case Response_HTTP::HTTP_BAD_REQUEST:
                case Response_HTTP::HTTP_FORBIDDEN:
                case Response_HTTP::HTTP_SERVER_ERROR:
                default:
                    break;
            }
        }

        $tdir = Config::get('siteRoot') . '/library/Schoolbox/Mobile/' . str_replace('\\', '/', $response->getCore('className'));
        $dirs[] = "$tdir/../Component/templates/";
        $dirs[] = "$tdir/templates/$skin/";
        $dirs[] = "$tdir/templates/core/";
        $dirs[] = "$tdir/templates/";

        // iterate over the $dirs array and ignore directories that don't exist because Twig will get upset
        $templatePaths = array();
        for($i = 0; $i < count($dirs); $i++) {
            if(is_dir($dirs[$i])) {
                $templatePaths[] = realpath($dirs[$i]) . '/';
            }
        }

        Debug::add('Template paths = ' . print_r($templatePaths, true));

        try {
            // instantiate Twig with template path
            $loader = new Twig_Loader_Filesystem($templatePaths);

            $twig = new Twig_Environment($loader);
            $twig->addExtension(new Twig_Extension_Debug());

            $pageTitle = $response->getPage('title');
            $twigData = $response->getResponse();

            $articleList = array();

            if($twigData) {

                // multi-article page
                if(isset($twigData['articles'])) {

                    // use array_keys as templates
                    if(is_assoc($twigData['articles'])) {
                        foreach($twigData['articles'] as $templateFile => $article) {

                            // don't try to parse data that's not in array form
                            if(!is_array($article)) {
                                continue;
                            }

                            $templateFile = ucfirst($templateFile);
                            $articleList[] = $this->renderTwigArticle($twig, $article, $templateFile);
                        }
                    }
                    // use core['methodName'] as template
                    else {
                        $templateFile = $response->getCore('methodName');
                        foreach($twigData['articles'] as $article) {
                            if(is_array($article)) {
                                $articleList[] = $this->renderTwigArticle($twig, $article, $templateFile);
                            }
                        }
                    }

                }
                // single-article page
                else {
                    $templateFile = $response->getCore('methodName');
                    // this case shouldn't happen, so we just try out luck, really...
                    $singleArticle = array(
                        'title' => ($pageTitle) ? $pageTitle : $templateFile,
                        'aside' => '',
                        'class' => '',
                        'icon' => '',
                        'content' => $this->renderTwigArticle($twig, $twigData, $templateFile)
                    );
                    $articleList[] = $singleArticle;
                }

            }

            // render the output and execute

            if($pageTitle || $pageTitle === '') {
                $jsonOutput = array(
                    'page' => array(
                        'title'    => $pageTitle,
                        'articles' => $articleList
                    ),
                );
            }
            // display a helpful message to the user, and give the option of sending an equally helpful error report
            else {
                $err[] = array(
                    'title' => 'Sorry, Schoolbox Mobile encountered an error.',
                    'aside' => '',
                    'class' => 'giza-error wide',
                    'icon' => 'error',
                    'content' => '
                    <ul><li>
                        <a href="#" id="sendReport">Tap here to send an error report</a>
                    </li><li>
                        <a href="#" id="wipeCache">Tap here to go back to the dashboard</a>
                    </li></ul>
                    '
                );

                $err[] = array(
                    'title' => 'URI',
                    'aside' => '',
                    'class' => 'giza-error technical',
                    'icon' => 'error',
                    'content' => sprintf("<ul><li>%s</li></ul>", $_SERVER['REQUEST_URI'])
                );

                if(count($response->getError())) {
                    foreach($response->getError() as $k => $v) {
                        // $v = sprintf("<pre style=%s>%s</pre>", '"font-size:0.6em; padding: 1em;"', $v);
                        $err[] = array(
                            'title' => $k,
                            'aside' => '',
                            'class' => 'giza-error technical',
                            'icon' => 'error',
                            'content' => $v
                        );
                    }
                }
                $jsonOutput = array(
                    'page' => array(
                        'title' => "ERROR", 'articles' => $err
                    ),
                );
            }
/*
            THIS IS FOR DEBUGGING REQUESTS IF REQUIRED, WILL DUMP THE WHOLE REQUEST AND RESPONSE TO FILE
            $fp = fopen('/tmp/lasttrace.txt', 'w');
            $trace = debug_backtrace();
            fwrite($fp, serialize($trace));
            fclose($fp);
*/
            if($returnOutput) {
                return $jsonOutput;
            }
            else {
                $this->sendHeaders();
                die(json_encode($jsonOutput));
            }

        }
        // could not load the templates
        catch(Twig_Error_Loader $e) {
            die($e);
        } catch(ErrorException $e) {
            die($e);
        }

    }

    private function renderTwigArticle(Twig_Environment $twig, array $data, $templateFile) {
        try {
            // is this a well-formatted article?
            if(isset($data['content'])) {

                // replace the 'content' array with its parsed content
                $data['content'] = preg_replace('/\s\s+/', ' ', str_replace('\n', '', $twig->render("$templateFile.html", $data)));
                return $data;
            }
            // not in article format? return a parsing of the data and hope for the best!
            else {
                return preg_replace('/\s\s+/', ' ', str_replace('\n', '', $twig->render("$templateFile.html", $data)));
            }
        } catch(Twig_Error_Loader $e) {
            // aaaaand ignore it.
            return '';
        }
    }

    public function getName() {
        return get_called_class();
    }

    /**
     * Implementing classes may choose to setup page-specific variables, which is done here
     */
    public function generatePage() {
        // TODO: Implement generatePage() method.
    }

}
