<?php
/**
 * Mapper for the unit instance model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Component_Instance;
use Schoolbox\Assessment\Course\Task\Cloner;
use Schoolbox\Collection;
use Schoolbox\Mapper;

class UnitInstanceMapper extends Mapper {

    protected function mapping() {
        $this->hasTable('course_unit_instance');
        $this->hasModel('\\Schoolbox\\Assessment\\Course\\UnitInstance');
        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true,
        ));
        // Unit category: the category that this unit was created from, not the
        // one that the component is on
        $this->hasMap(array(
            'column'     => 'unit_category_id',
            'property'   => 'unitCategory',
            'type'       => self::TYPE_MODEL,
            'join'       => 'unit_category',
        ));
        // Component: the component that this unit is displayed on
        $this->hasMap(array(
            'column'     => 'component_id',
            'property'   => 'component',
            'type'       => self::TYPE_MODEL,
            'join'       => 'component',
        ));
        $this->hasMap(array(
            'column'     => 'title',
            'property'   => 'title',
            'type'       => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'     => 'open_date',
            'property'   => 'openDate',
            'type'       => self::TYPE_DATETIME,
        ));
        $this->hasMap(array(
            'column'     => 'due_date',
            'property'   => 'dueDate',
            'type'       => self::TYPE_DATETIME,
        ));
        $this->hasMap(array(
            'column'     => 'created_at',
            'property'   => 'created',
            'type'       => self::TYPE_DATETIME,
            'created_timestamp' => true,
        ));
        $this->hasMap(array(
            'column'     => 'deleted_at',
            'property'   => 'deleted',
            'type'       => self::TYPE_DATETIME,
        ));

        $this->hasJoin('unit_category', array(
            'mapper'    => '\\Schoolbox\\Resource\\CategoryMapper',
            'this_key'  => 'unit_category_id',
            'other_key' => 'id',
        ));
        $this->hasJoin('component', array(
            'mapper'    => 'Component_InstanceMapper',
            'this_key'  => 'component_id',
            'other_key' => 'id',
        ));

        $this->hasSoftDeleteDateField('deleted_at');
        $this->registerCallback('postSave', array($this, 'postSave'));
    }

    /**
     * Retrieves unit instances for the given component.
     *
     * @param  Component_Instance        $component
     * @return UnitInstance[]|Collection
     */
    public function findForComponent(Component_Instance $component) {
        /** @var Collection|UnitInstance[] $unit_list */
        $unit_list = $this->findByColumn(
            'component_id',
            $component->getId(),
            array(
                'order' => array(
                    'open_date' => 'asc',
                    'due_date'  => 'asc',
                )
            )
        );

        // Hydrate the list of tasks within each unit, sorting by order.
        $task_list = Task\InstanceMapper::getInstance()->findByColumn(
            'course_unit_instance_id',
            $unit_list->getColumnValues('id'),
            array(
                'order' => array(
                    'order' => 'asc',
                )
            )
        );

        // Add only the relevant tasks to each unit.
        foreach ($unit_list as $unit) {
            $task_iterator = new \CallbackFilterIterator(
                $task_list->getIterator(),
                function($item) use($unit) {
                    /** @var Task\Instance $item */
                    return $item->getUnitInstance()->getId() == $unit->getId();
                }
            );
            foreach ($task_iterator as $task) {
                $unit->getTasks()->add($task);
            }
        }
        return $unit_list;
    }

    /**
     * Post-save callback:
     * * if a new item, save a new task instance for each task in this unit.
     * * if not, update existing task instances for this course.
     *
     * @param  UnitInstance $object
     * @param  bool         $is_new
     * @return void
     */
    protected function postSave(UnitInstance $object, $is_new = false) {
        // Create tasks for this item (if this item was only just saved)
        if ($is_new) {
            $task_list = TaskMapper::getInstance()->findByColumn(
                'category_id',
                $object->getUnitCategory()->getId()
            );
            $task_instance_collection = new Collection();
            foreach ($task_list as $task) {
                $task_instance = new Task\Instance();
                $task_instance
                    ->setUnitInstance($object)
                    ->setTask($task)
                ;
                $task_instance_collection->add($task_instance);
            }
            Task\InstanceMapper::getInstance()->save($task_instance_collection);
        }
        else {
            /** @var Task\Instance $task_instance_list */
            $task_instance_list = Task\InstanceMapper::getInstance()->findByColumn(
                'course_unit_instance_id',
                $object->getId()
            );
            foreach ($task_instance_list as $task_instance) {
                $cloner = new Cloner($task_instance);
                $cloner->updateLinkedItems();
            }
        }
    }
}
