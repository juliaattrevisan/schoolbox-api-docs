<?php
/**
 * Controller for curriculum learning areas.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Curriculum\LearningArea;

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
     * Creates a new learning area.
     */
    public function create()
    {
        /** @var Model $item */
        $item = $this->getMapper()->createModel();
        $post = $this->getRequest()->post;
        $code = 200;

        $item
            ->setName($post['name'])
            ->setSource($this->getSourceMapper()->findOneByColumn('class', ''));

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
