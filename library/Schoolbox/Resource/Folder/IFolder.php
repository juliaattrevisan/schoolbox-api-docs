<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Folder;


interface IFolder {

    /**
     * @return int
     */
    public function getId();


    /**
     * @return string
     */
    public function getName();


    /**
     * @return self
     */
    public function getParent();

    /**
     * A folder type. This will probably map to a class name.
     *
     * @return string
     */
    public function getFolderType();
}
