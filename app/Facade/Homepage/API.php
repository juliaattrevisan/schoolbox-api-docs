<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Facade_Homepage_API extends Generic_API {

    private $custom = array('Component_Homepage_SubjectGradesController', 'SubjectGrades');

    public function Display($categoryID) {
        $response = new Response_HTTP();
        try {
            list($user, $cat, $page) = $this->getHomepage($categoryID);

            $components = $page->get('components');
            list($catTitle, $articles) = $this->renderComponents($user, $cat, $components);
            $response->setPage('title', $catTitle);
            $response->setResponse('articles', $articles);

            return $response;
        }
        catch(ErrorException $e) {
            die($e);
        }
        catch(Exception_Schoolbox $e) {
            $response->setPage('title', 'Folder Not Found');
            $response->setResponse('articles', array('Error' => array(
                'title' => 'Folder Not Found',
                'icon' => '',
                'class' => 'wide no-title',
                'content' => array(
                    'reason' => 'Sorry, the homepage requested could not be found.'
                )
            )));
            return $response;
        }
    }

    // module methods
    public function News($cID)    { return $this->getModule($cID, 'News');        }
    public function DueWork($cID) { return $this->getModule($cID, 'DueWorkList'); }
    public function Grades($cID)  { return $this->getModule($cID, 'SubjectGrades');  }
    public function Links($cID)   { return $this->getModule($cID, 'LinkList');    }
    public function Folders($cID) { return $this->getModule($cID, 'FolderList');  }

    /**
     * This could be far more efficient, but at the moment...
     * @param $categoryID
     * @param $method
     * @return Response_HTTP
     */
    private function getModule($categoryID, $method) {
        $response = new Response_HTTP();
        try {
            list($user, $cat, $page) = $this->getHomepage($categoryID);
            $method = sprintf("Component_Homepage_%sController", $method);
            foreach($page->get('components') as $comp) {
                if($comp->get('controller') == $method) {
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
        }
        catch (Exception_Schoolbox $e) {
            //
            // Exception causes:
            // * Category was not found
            // * Category was found but isn't a homepage
            $response->setStatusCode(Response_HTTP::HTTP_NOT_FOUND);
            $response->setPage('title', 'Homepage Not Found');
            $response->setResponse('articles', array('Error' => array(
                'title' => 'Homepage Not Found',
                'icon' => '',
                'class' => 'wide no-title',
                'content' => array(
                    'reason' => 'Sorry, the homepage requested could not be found.'
                )
            )));
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

            if(is_array($components) || $components instanceof GizaCollection) {
                // plz render mine components
                foreach($components as $component) {
                    foreach($this->componentRefactoryMap($component->get('controller')) as $className) {
//                        echo "`$className` ";
                        if(in_array($className, $this->custom)) {
                            if(class_exists($className)) {
                                $instance = new $className();

                                if($instance instanceof Facade_Component_Generic) {
//                                    echo " ~$className~ ";
                                    $articles[$className] = $instance->render($user, $cat->get('id'));
                                }
                                else {
                                    $articles[$className] = array();
                                }
                            }
                        }
                        else {
                            $articles[$className] = $this->renderComponent($component, $cat->get('id'), $user);
                        }
                    }
                }
            }
            // bypassing the whole subroutine = duplicate code -_-
            elseif(in_array($components, $this->custom)) {

                preg_match('#Component_([a-z]*)_([a-z]*)Controller#i', $components, $matches);
                $className = 'Facade_Component_' . $matches[2];

                if(class_exists($className)) {
                    $instance = new $className();

                    if($instance instanceof Facade_Component_Generic) {
                        $articles[$matches[2]] = $instance->render($user, $cat->get('id'));
                    }

                    else {
                        $articles[$matches[2]] = array();
                    }
                }
            }
            else {
                return array('Error', array('Bad component definitions.'));
            }

            // if the category is still wrapped in a collection, extract it
            if($cat instanceof GizaCollection) {
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
            foreach($this->componentRefactoryMap($component->get('controller')) as $className) {
                $className = "Facade_Component_$className";
                if(class_exists($className)) {
                    $instance = new $className();
                    if($instance instanceof Facade_Component_Generic) {
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

        preg_match('#Component_([a-z]*)_([a-z]*)Controller#i', $oldFunctionCallName, $matches);
        $call = $matches[2];

        $map = array(
            'News'        => array('HomepageNews'),
            'DueWorkList' => array('SubjectDueWork'),
            'GradesList'  => array('SubjectGrades'), // will only be called from getModule
            'LinkList'    => array('HomepageLinks'),
            'FolderList'  => array('CategoryFolders'),
        );
        return isset($map[$call]) ? $map[$call] : array('UNKN0WN');
    }

}
