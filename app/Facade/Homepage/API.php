<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\User;

class Facade_Homepage_API extends Generic_API {

    private $custom = array('Component_Homepage_SubjectGradesController', 'SubjectGrades');

    public function Display($categoryID) {
        $response = new Response_HTTP();
        try {
	        /**
	         * @var User $user
	         * @var Category $cat
	         * @var Collection $page
	         */
	        list($user, $cat, $page) = $this->getHomepage($categoryID);

            list($catTitle, $articles) = $this->renderComponents($user, $cat, $page->toArray());
            $response->setPage('title', $catTitle);
            $response->setResponse('articles', $articles);
            $this->logVisit($user->getId(), $categoryID);
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
            $this->logVisit($user->get('uid'), $categoryID);
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

	    /** @var $cMap CategoryMapper */
	    $cMap = CategoryMapper::getInstance();

        $cat = $cMap->findById($catID);

	    $homeFactory = new Component_Homepage_Factory(new \User(), new \SitePage(false));
        $page = $homeFactory->buildComponentList($catID);

        return array($user, $cat, $page);
    }

	/**
	 * @param User $user
	 * @param Category $cat
	 * @param $components
	 * @return array
	 */
	private function renderComponents($user, $cat, $components) {
        try {
            $articles = array();

            if(is_array($components) || $components instanceof GizaCollection) {
                // plz render mine components
                foreach($components as $component) {
                    foreach($this->componentRefactoryMap(get_class($component)) as $className) {
//                        echo "`$className` ";
                        if(in_array($className, $this->custom)) {
                            if(class_exists($className)) {
                                $instance = new $className();

                                if($instance instanceof Facade_Component_Generic) {
                                    $articles[$className] = $instance->render($user, $cat->getId());
                                }
                                else {
                                    $articles[$className] = array();
                                }
                            }
                        }
                        else {
                            $articles[$className] = $this->renderComponent($component, $cat->getId(), $user);
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
            if($cat instanceof Collection) {
                $cat = $cat->current();
            }
	        $codes = $cat->getCodes();
            $catTitle = $cat->getName() . ($codes->count() ? " ({$codes->current()->getCode()})" : '');

            return array($catTitle, $articles);
        }
        catch(ErrorException $e) {
            die($e);
        }
    }

    private function renderComponent($component, $catID, User $user) {

        try {
            // TODO component definitions should be refactored in database to point to new modules
            foreach($this->componentRefactoryMap(get_class($component)) as $className) {
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

    /**
     * Log visit to this homepage.
     *
     * @param int $user_id
     * @param int $category_id
     *
     * @return void
     */
    protected function logVisit($user_id, $category_id) {
        $mapper = new Schoolbox\Resource\CategoryLogMapper();
        $mapper->incrementHitcount($user_id, $category_id);
    }

}
