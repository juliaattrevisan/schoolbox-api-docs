<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Folder;

use Schoolbox\Filter\AbstractRunTimeOptions;
use Schoolbox\User;

/**
 * For now, folder lists are generated at run-time
 */
class RunTimeOptions extends AbstractRunTimeOptions
{

    /**
     * @var User $viewee
     */
    private $viewee;


    /**
     * @param User          $viewee
     * @param string[]|null $folderIds
     */
    public function __construct(User $viewee, array $folderIds = null)
    {
        parent::__construct($folderIds);
        $this->viewee = $viewee;
    }


    /* Getters
     */


    /**
     * @return User $viewee
     */
    public function getViewee()
    {
        return $this->viewee;
    }

}
