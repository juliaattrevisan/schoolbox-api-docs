<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Facade_Homepage_API extends Generic_API {

    private $custom = array('outputGradesList', 'SubjectGrades');

    public function Display($categoryID) {
        try {
            $response = new Response_HTTP();
            list($user, $cat, $page) = $this->getHomepage($categoryID);

            $components = $page->get()->get('components');

            list($catTitle, $articles) = $this->renderComponents($user, $cat, $components);
            $response->setPage('title', $catTitle);
            $response->setResponse('articles', $articles);

            return $response;
        }
        catch(ErrorException $e) {
            die($e);
        }
    }

    // module methods
    public function News($cID)    { return $this->getModule($cID, 'outputNews');        }
    public function DueWork($cID) { return $this->getModule($cID, 'outputDueWorkList'); }
    public function Grades($cID)  { return $this->getModule($cID, 'outputGradesList');  }
    public function Links($cID)   { return $this->getModule($cID, 'outputLinkList');    }
    public function Forums($cID)  { return $this->getModule($cID, 'outputForumList');   }
    public function Folders($cID) { return $this->getModule($cID, 'outputFolderList');  }

    /**
     * This could be far more efficient, but at the moment...
     * @param $categoryID
     * @param $method
     * @return Response_HTTP
     */
    private function getModule($categoryID, $method) {
        $response = new Response_HTTP();
        list($user, $cat, $page) = $this->getHomepage($categoryID);

        foreach($page->get('components') as $comp) {
            if($comp->get('method') == $method) {
                list($catTitle, $articles) = $this->renderComponents($user, $cat, array($comp));
            }
        }

        if(isset($catTitle)) {
            $response->setPage('title', $catTitle);
            $response->setResponse('articles', $articles);
        }
        // i strongly dislike special cases, but grades needs to be overridden since it doesn't exist in desktop yet
        elseif(in_array($method, $this->custom)) {
            list($catTitle, $articles) = $this->renderComponents($user, $cat, $method);
            $response->setPage('title', $catTitle);
            $response->setResponse('articles', $articles);
        }
        else {
            $response->setPage('title', '');
        }

        return $response;
    }


    private function getHomepage($catID) {
        $s = new Authenticator();
        $user = $s->getSessionUser();

        $cMap = new GizaCategory_DataMapper();
        $cat = $cMap->read($catID);
        $page = $cat->get('homepage');

        return array($user, $cat, $page);
    }

    private function renderComponents($user, $cat, $components) {
        try {
            $articles = array();

            if(is_array($components) || $components instanceof Collection) {
                // plz render mine components
                foreach($components as $component) {
                    foreach($this->componentRefactoryMap($component->get('method')) as $m) {
                        if(in_array($m, $this->custom)) {
                            $className = "Facade_Component_$m";
                            if(class_exists($className)) {
                                $instance = new $className();

                                if(is_a($instance, 'Facade_Component_Generic')) {
                                    $articles[$m] = $instance->render($user, $cat->get('id'));
                                }
                                else {
                                    $articles[$m] = array();
                                }
                            }
                        }
                        else {
                            $articles[$m] = $this->renderComponent($component, $cat->get('id'), $user);
                        }
                    }
                }
            }
            // bypassing the whole subroutine = duplicate code -_-
            elseif(in_array($components, $this->custom)) {

                $className = "Facade_Component_$components";

                if(class_exists($className)) {
                    $instance = new $className();

                    if(is_a($instance, 'Facade_Component_Generic')) {
                        $articles[$components] = $instance->render($user, $cat->get('id'));
                    }

                    else {
                        $articles[$components] = array();
                    }
                }
            }
            else {
                return array('Error', array('Bad component definitions.'));
            }

            // if the category is still wrapped in a collection, extract it
            if($cat instanceof Collection) {
                $cat = $cat->get();
            }
            $catTitle = $cat->get('name') . ($cat->__isset('code') ? " ({$cat->get('code')})" : '');

            return array($catTitle, $articles);
        }
        catch(ErrorException $e) {
            die($e);
        }
    }

    private function renderComponent($component, $catID, GizaUser $user) {

        try {
            // TODO component definitions should be refactored in database to point to new modules
            foreach($this->componentRefactoryMap($component->get('method')) as $m) {
                $className = "Facade_Component_$m";

                if(class_exists($className)) {
                    $instance = new $className();
                    if(is_a($instance, 'Facade_Component_Generic')) {
                        return $instance->render($user, $catID);
                    }
                    else {
                        return array();
                    }
                }
            }
        }
        catch(ErrorException $e) {
            die($e);
        }

        return array();
    }

    /**
     * @param $oldFunctionCallName
     * @return array
     */
    private function componentRefactoryMap($oldFunctionCallName) {
        $map = array(
            'outputNews'        => array('HomepageNews'),
            'outputDueWorkList' => array('SubjectDueWork', 'SubjectGrades'),  // duplicate one
            'outputGradesList'  => array('SubjectGrades'), // will only be called from getModule
            'outputLinkList'    => array('HomepageLinks'),
            'outputForumList'   => array('CategoryForums'),
            'outputFolderList'  => array('CategoryFolders'),
        );
        return isset($map[$oldFunctionCallName]) ? $map[$oldFunctionCallName] : array('UNKN0WN');
    }

}
