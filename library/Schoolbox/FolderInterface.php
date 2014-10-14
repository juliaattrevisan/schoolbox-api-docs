<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox;


interface FolderInterface {

    /**
     * @return int
     */
    public function getId();


    /**
     * @return string
     */
    public function getName();


    /**
     * @return FolderInterface
     */
    public function getParent();

    /**
     * A folder type. This will probably map to a class name.
     *
     * @return string
     */
    public function getFolderType();
}
