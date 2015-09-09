<?php
/**
 * Controller for curriculum learning levels.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Curriculum\LearningLevel;

use Alaress\Web\Controller\Base;
use Schoolbox\Curriculum\Source;

class Controller extends Base
{
    /**
     * @var Mapper
     */
    protected $mapper;

    /**
     * @var Source\Mapper
     */
    protected $sourceMapper;

    /**
     * Constructor
     *
     * @param Mapper $mapper
     */
    public function __construct(Mapper $mapper, Source\Mapper $sourceMapper)
    {
        $this->mapper       = $mapper;
        $this->sourceMapper = $sourceMapper;
    }

    /**
     * Creates a new learning level.
     */
    public function create()
    {
        /** @var Model $item */
        $item = $this->getMapper()->createModel();
        $post = $this->getRequest()->post;
        $code = 200;

        /** @var Source\Model $source */
        $source = $this->getSourceMapper()->findOneByColumn('class', '');
        $item
            ->setName($post['name'])
            ->setSource($source)
            ->setReference('')
            ->setDescription('')
            ->setOrder($this->getMapper()->countByColumn('curriculum_source_id', $source->getId()))
        ;

        // Save the item
        if (!$this->getMapper()->save($item)) {
            // Something went wrong, but I know not what
            $code = 500;
        }

        // Send something back
        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->status->setCode($code);
            $this->getResponse()->content->set([
                'format' => 'json',
                'data'   => ['item' => $item,],
            ]);
        } else {
            // Redirect back to the the last page?
            // Or elsewhere, should validation have failed
            $this->getResponse()->redirect->afterPost(
                $this->getSession()->getHistory()
            );
        }
    }

    /**
     * @return Mapper
     */
    protected function getMapper()
    {
        return $this->mapper;
    }

    /**
     * @return Source\Mapper
     */
    protected function getSourceMapper()
    {
        return $this->sourceMapper;
    }

}
