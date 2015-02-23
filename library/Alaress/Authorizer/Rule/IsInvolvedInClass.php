<?php
/**
 * This authorization method checks whether the user is not only involved in a
 * class, but is a teacher for that class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use External_UserInterface;
use Schoolbox\Resource\Category;
use Schoolbox\User;

class IsInvolvedInClass extends All
{
    /**
     * A connection to the external user database.
     * Classes and their teachers are stored in this database.
     *
     * @var External_UserInterface
     */
    protected $externalDb;

    /**
     * A source for class codes.
     *
     * May be one of the following:
     * * A array or other Traversable of class codes
     * * A Category object (which contains class codes)
     * * A callable that returns one of the above
     *
     * @var array|callable|Category
     */
    protected $classCodeSource;

    /**
     * Constructor (obviously)
     *
     * @param External_UserInterface  $externalDb
     * @param array|callable|Category $classCodeSource
     */
    public function __construct(External_UserInterface $externalDb, $classCodeSource)
    {
        $this->externalDb      = $externalDb;
        $this->classCodeSource = $classCodeSource;
        $this
            ->addRule(new IsLoggedIn())
            ->addRule(new Callback(array($this, 'authorizeMethod')))
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function authorizeMethod(array $params = array(), User $user = null)
    {
        if ($user instanceof User) {
            // Get the class code source
            $classCodeSource = is_callable($this->classCodeSource)
                ? call_user_func($this->classCodeSource, $params, $user)
                : $this->classCodeSource;

            // And translate to codes (with a little magic if necessary)
            $classCodes = array();
            if ($classCodeSource instanceof Category) {
                foreach ($classCodeSource->getCodes() as $code) {
                    $classCodes[] = $code->getCode();
                }
            } else {
                $classCodes = $classCodeSource;
            }

            // Check each subject
            foreach ($this->externalDb->getSubjectList($user->getExternalId(), $user->getId()) as $subject) {
                foreach ($classCodes as $classCode) {
                    if (is_numeric($classCode) && $classCode == $subject['id']) {
                        return true;
                    }
                    if (strtoupper($classCode) == strtoupper($subject['code'])) {
                        return true;
                    }
                }
            }
        }
        throw new Unauthorized();
    }
}
