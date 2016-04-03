<?php
/**
 * Course builder component for heads of curriculum.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\TaskBuilder;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
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
     * @var FolderModel
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
     * @var FolderModel[]|Collection
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
            $this->category = FolderMapper::getInstance()->findById($categoryId);
        }
        $this->task_list = TaskMapper::getInstance()->findByCategory($this->category);

        // What pages have this course?
        $this->unitList             = UnitInstanceMapper::getInstance()->findByUnitCategoryId($categoryId);
        $this->categoryWithUnitList = FolderMapper::getInstance()->findByColumn(
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

        $returnUrl = urlencode(base64_encode($_GET["page"])) == '' ? $_POST["return_url"] : urlencode(base64_encode($_GET["page"]));

        $this->setVar(
            array(
                'SESSION_NAME'   => session_name(),
                'SESSION_ID'     => session_id(),
                'RETURN_URL'     => $returnUrl,
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
        if ($this->category instanceof FolderModel) {
            foreach (FolderMapper::getInstance()->findParents($this->category) as $parent) {
                /** @var FolderModel $parent */
                $this->setVar('PARENT_CATEGORY_ID', $parent->getId());
                $this->setVar('PARENT_CATEGORY_NAME', $parent->getName());
                $this->parse('category_breadcrumb_block', 'category_breadcrumb', true);
            }
        }

        // the 'unit' is just the page we're on
        $unitId = $this->getContext()->getId();

        $this->setVar('task_block', '');

        foreach ($this->task_list as $task) {

            // mash sitepage vars into a twig... what could possibly go wrong...
            $this->setVar('TASK_ITEM', $this->sitepage->getTwig()->render('@course/task/writable.twig', [
                'unit'        => [ 'id' => $unitId ],
                'returnUrl'   => [ $unitId => $returnUrl ],
                'task'        => $task,
                'taskReveals' => [ $task->getId() => new TaskBuilder($this->getInstance(), $task, $returnUrl) ],
                'reveals'     => [ $unitId => new TaskBuilder($this->getInstance()) ]
            ]));

            $this->parse('task_block', 'task', true);
        }

        $this->setVar('CREATE_MODAL', $this->sitepage->getTwig()->render('@course/task/reveal-modal.twig', [
            'reveal' => new TaskBuilder($this->getInstance(), null, $returnUrl)
        ]));

        return true;
    }

}
