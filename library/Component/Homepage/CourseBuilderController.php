<?php
/**
 * Course builder component for heads of curriculum.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Resource\Folder\Model as CategoryModel;
use Schoolbox\Resource\Folder\Mapper as CategoryMapper;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Schoolbox\Collection;

class Component_Homepage_CourseBuilderController extends Component_Homepage_ControllerAbstract
{
    /**
     * @var Group
     */
    protected $group_handler;

    /**
     * @var CategoryModel
     */
    protected $category;

    /**
     * @var Task[]
     */
    protected $task_list;

    /**
     * The list of instances of this unit.
     *
     * @var UnitInstance[]|Collection
     */
    protected $unitList;

    /**
     * The list of categories this unit is on. Derived from $this->unitList.
     *
     * @var CategoryModel[]|Collection
     */
    protected $categoryWithUnitList;

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $this->group_handler = new Group();
        $categoryId          = $this->getContext()->getId();
        if ($categoryId > 0) {
            $this->category = CategoryMapper::getInstance()->findById($categoryId);
        }
        $this->task_list = TaskMapper::getInstance()->findByCategory($this->category);

        // What pages have this course?
        $this->unitList             = UnitInstanceMapper::getInstance()->findByUnitCategoryId($categoryId);
        $this->categoryWithUnitList = CategoryMapper::getInstance()->findByColumn(
            'id',
            $this->unitList->map(
                function ($unit) {
                    /** @var UnitInstance $unit */
                    return $unit->getComponent()->getIdentifier();
                }
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'courseBuilder')) {
            return false;
        }

        // Permission denied if user doesn't have write access to category
        if ($this->permission < WRITE_ACCESS) {
            return false;
        }

        $this->parseConfig();

        $this->setBlock($templateHandle, 'task', 'task_block');
        $this->setBlock($templateHandle, 'category_breadcrumb', 'category_breadcrumb_block');
        $this->setBlock('task', 'edit_task', 'edit_task_block');
        $this->setBlock('task', 'task_link', 'task_link_block');
        $this->setBlock('task', 'task_no_link', 'task_no_link_block');
        $this->setBlock('task', 'edit_task_form', 'edit_task_form_block');

        $this->setVar(
            array(
                'SESSION_NAME'   => session_name(),
                'SESSION_ID'     => session_id(),
                'COMPONENT_NAME' => $this->getConfig('componentname'),
                'UNIT_LIST'      => json_encode(
                    $this->unitList->map(
                        function ($unit) {
                            /** @var UnitInstance $unit */
                            return $unit->getComponent()->getIdentifier();
                        }
                    )->getAll()
                ),
            )
        );

        // Start spamming out tasks into the template
        $this->setVar('category_breadcrumb_block', '');
        if ($this->category instanceof CategoryModel) {
            foreach (CategoryMapper::getInstance()->findParents($this->category) as $parent) {
                /** @var CategoryModel $parent */
                $this->setVar('PARENT_CATEGORY_ID', $parent->getId());
                $this->setVar('PARENT_CATEGORY_NAME', $parent->getName());
                $this->parse('category_breadcrumb_block', 'category_breadcrumb', true);
            }
        }
        $this->setVar('task_block', '');

        $i = 0;
        foreach ($this->task_list as $task) {
            $this->setVar(
                array(
                    'TASK_ID'              => Scrub::text($task->getId()),
                    'TASK_TITLE'           => Scrub::text($task->getTitle()),
                    'TASK_TYPE'            => '',
                    'TASK_TYPE_LABEL'      => Scrub::text($task->getLabel()),
                    'TASK_CLASS'           => Scrub::text($task->getIconClass()),
                    'TASK_PREVIEW'         => $task->getType() == Task::TYPE_TASK ? 'data-plugin="preview"' : '',
                    'TASK_LINK'            => Scrub::text($task->getLink()),
                    'TASK_SELF_ASSESS'     => ($task->getRequiresConfirmation() ? '' : 'checked'),
                    'TASK_MODIFY_URL'      => $task->getType() == Task::TYPE_TASK ? '#' : '/task/modify/'.$task->getId(),
                    'TASK_REVEAL_ATTR'     => '',
                    'task_link_block'      => '',
                    'task_no_link_block'   => '',
                    'edit_task_form_block' => '',
                )
            );

            $task_type = $task->getType();
            if ($task_type === Task::TYPE_TASK) {
                $this->setVar(
                    'TASK_REVEAL_ATTR',
                    'data-reveal-id="editTask-' . Scrub::text($task->getId()) . '"'
                );
                $zone = $this->newZone('az-task-' . $task->getId());

                if (strpos($task->getLink(), '/storage/fetch.php') === 0) {
                    $zone->attachFiles(array(substr($task->getLink(), -40)));
                    $this->setVar(
                        array(
                            'TASK_ATTACH_DISPLAY' => 'display: block;',
                            'TASK_URL_DISPLAY'    => 'display: none;'
                        )
                    );

                } else {
                    $this->setVar(
                        array(
                            'TASK_ATTACH_DISPLAY' => 'display: none;',
                            'TASK_URL_DISPLAY'    => 'display: block;'
                        )
                    );
                }

                $zone->renderIntoSitePage($this->sitepage, 'ATTACH_ZONE');

                $this->parse('edit_task_form_block', 'edit_task_form');
                $this->setVar('TASK_TYPE', 'task');
                if (strlen($task->getLink()) > 0) {
                    $this->parse('task_link_block', 'task_link');
                } else {
                    $this->parse('task_no_link_block', 'task_no_link');
                }
            } elseif ($task_type === Task::TYPE_DUE_WORK) {
                $this->setVar('TASK_TYPE', 'due_work');
                $this->parse('task_no_link_block', 'task_no_link');
            } elseif ($task_type === Task::TYPE_ONLINE_ASSESS) {
                $this->setVar('TASK_TYPE', 'assessment');
                $this->parse('task_no_link_block', 'task_no_link');
            }
            $this->parse('edit_task_block', 'edit_task');
            $this->parse('task_block', 'task', true);
        }

        $this->newZone('az-new-task', 'ATTACH_ZONE');

        return true;
    }


    /**
     * @param string $id
     * @param string $target
     * @return \Schoolbox\Resource\Attachment\Zone
     */
    protected function newZone($id, $target = '')
    {
        $zone = new \Schoolbox\Resource\Attachment\Zone($id);
        $zone->setMulti(false)
            ->setReadOnly(false)
            ->setMimeFilter(null)
            ->setAttachmentsLabel('')
            ->setAttachPrompt('Attach file or folder')
            ->setModalTitle('Select File or Folder')
            ->setThumbnailSize(32);

        if ($target) {
            $zone->renderIntoSitePage($this->sitepage, $target);
        }

        return $zone;
    }
}
