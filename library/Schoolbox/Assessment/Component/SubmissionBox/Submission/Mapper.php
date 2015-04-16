<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission;

use Schoolbox\Assessment\Activity;
use Schoolbox\Assessment\ActivityCollection;
use Schoolbox\Assessment\IActivityMapper;
use Schoolbox\IMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Collection;

/**
 * Class Mapper
 *
 * This class acts as a composite for the concrete implementations,
 * for when you retrieve submission objects, but don't know what
 * type they are (including possibility of a mixture).
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox\Submission
 */
class Mapper extends \Schoolbox\Mapper implements IMapper, IActivityMapper
{

    /**
     * Indexed by fully qualified model class name
     *
     * @var AbstractMapper[]
     */
    protected $mappers;


    /**
     * Just jam dingletons in there
     */
    public function __construct()
    {

        $this->mapping();

    }

    public function mapping() {
        $i = InlineMapper::getInstance();
        $f = FileMapper::getInstance();

        $this->mappers = [
            $i->getModel() => $i,
            $f->getModel() => $f
        ];
    }


    /**
     * TODO split this method into an abstraction above Schoolbox\Mapper
     *
     * @param  array $options
     * @return int
     */
    public function countAll($options = array())
    {
        return $this->countByColumnList(array(), $options);
    }


    /**
     * TODO split this method into an abstraction above Schoolbox\Mapper
     *
     * @param string $columnName
     * @param string $columnValue
     * @param array  $options
     * @return int
     */
    public function countByColumn($columnName, $columnValue, $options = array())
    {
        return $this->countByColumnList(array($columnName => $columnValue), $options);
    }


    /**
     * @param  array $columnList
     * @param  array $options
     * @return int
     */
    public function countByColumnList($columnList, $options = array())
    {
        // TODO replace with something that can actually make use of COUNT(*) rather than all the overhead of hydration
        return $this->findByColumnList($columnList, $options)->count();
    }


    /**
     * TODO split this method into an abstraction above Schoolbox\Mapper
     *
     * @param array $options @see findByColumnList() $options param
     * @return Collection|ISubmission[]
     */
    public function findAll($options = array())
    {
        return $this->findByColumnList(array(), $options);
    }


    /**
     * TODO split this method into an abstraction above Schoolbox\Mapper
     *
     * @param  mixed $identifierValue
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     * @return ISubmission
     */
    public function findById($identifierValue)
    {
        return $this->findOneByColumn('id', $identifierValue);
    }


    /**
     * TODO split this method into an abstraction above Schoolbox\Mapper
     *
     * @param String $columnName
     * @param String $columnValue
     * @param array  $options @see findByColumnList() $options param
     * @return Collection|ISubmission[]
     */
    public function findByColumn($columnName, $columnValue, $options = array())
    {
        return $this->findByColumnList(array($columnName => $columnValue), $options);
    }


    /**
     * TODO split this method into an abstraction above Schoolbox\Mapper
     *
     * @param  String $columnName
     * @param  String $columnValue
     * @param  array  $order Fields to order by (@see findByColumnList() $options param)
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     * @return ISubmission
     */
    public function findOneByColumn($columnName, $columnValue, $order = array())
    {
        return $this->findOneByColumnList(array($columnName => $columnValue), $order);
    }


    /**
     * TODO split this method into an abstraction above Schoolbox\Mapper
     *
     * @param  array $columnList
     * @param  array $order Fields to order by (@see findByColumnList() $options param)
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     * @return ISubmission
     */
    public function findOneByColumnList($columnList, $order = array())
    {
        $result_collection = $this->findByColumnList(
            $columnList,
            array(
                'deleted' => true,
                'limit'   => 1,
                'order'   => $order,
            )
        );

        foreach ($result_collection as $model) {
            // $this->throwIfDeleted($model); // Submission doesn't have soft-deletes
            return $model;
        }

        throw new Exception('Instance of object not found');
    }


    /**
     * @param array $columnList
     * @param array $options
     * @throws Exception
     * @return Collection|ISubmission[]
     */
    public function findByColumnList($columnList, $options = array())
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }


    /**
     * @param $object
     * @return array
     */
    public function create($object)
    {
        $this->getRelevantMapper($object)->create($object);
    }


    /**
     * @param $object
     * @return array|bool
     */
    public function save($object)
    {
        $this->getRelevantMapper($object)->save($object);
    }


    /**
     * @param $object
     * @return bool
     */
    public function delete($object)
    {
        $this->getRelevantMapper($object)->delete($object);
    }


    /**
     * @param int  $userId
     * @param int  $boxId
     * @param int  $limit
     * @return Collection|Inline[]
     */
    public function getDrafts($userId, $boxId, $limit = null)
    {
        /** @var InlineMapper $inlineMapper */
        $inlineMapper = $this->mappers[Inline::class];

        return $inlineMapper->getDrafts($userId, $boxId, $limit);
    }


    /**
     * Gets the list of activities related to student submissions.
     *
     * An activity is returned for each submission that this user has made into
     * a submission box in the given categories.
     *
     * @param int   $userId
     * @param int   $limit
     * @param array $categories
     *
     * @return ActivityCollection|Activity[]
     */
    public function getActivities($userId, $limit, array $categories)
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }


    /**
     * @param int $userId
     * @param int $boxId
     * @return ActivityCollection|Activity[]
     */
    public function getHistory($userId, $boxId)
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }


    /**
     * Composite method; call the given method on all subordinate mappers and merge the result
     *
     * @param string $name
     * @param array $args
     * @throws Exception
     * @return Collection
     */
    public function __call($name, $args = array())
    {
        $collection = new Collection();

        // TODO switch out this implementation for intelligently hydrating on a per-record basis rather than iterating the mappers
        foreach ($this->mappers as $targetClass => $mapper) {

            $method = [ $mapper, $name ];

            if (!is_callable($method)) {
                $cName = get_class($mapper);
                throw new Exception("Could not call $cName::$name.");
            }

            $result = call_user_func_array($method, $args);

            if ($result instanceof Collection) {
                $collection->mergeUnique($result->filter(
                    function ($item) use ($targetClass) {
                        if ($item instanceof Activity) {
                            $item = $item->getSubmission();
                        }
                        return $item instanceof $targetClass;
                    }
                ), 'id');

            } else {
                $collection->add($result);

            }
        }

        return $collection;
    }


    /**
     * @param $object
     * @throws Exception
     * @return Mapper
     */
    protected function getRelevantMapper($object)
    {
        $class = get_class($object);

        if (isset($this->mappers[$class])) {
            return $this->mappers[$class];
        }

        throw new Exception("Unsupported model: $class");
    }

}