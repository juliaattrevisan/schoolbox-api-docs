<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\WorkType;

use Schoolbox;

class Model
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $externalResultType;

    /**
     * @var boolean
     */
    private $showOnGradesPage;

    /**
     * @return bool
     */
    public function getShowOnGradesPage(): bool
    {
        return $this->isShowOnGradesPage();
    }

    /**
     * @return bool
     */
    public function isShowOnGradesPage(): bool
    {
        return $this->showOnGradesPage;
    }


    /**
     * @param bool $showOnGradesPage
     */
    public function setShowOnGradesPage(bool $showOnGradesPage): void
    {
        $this->showOnGradesPage = $showOnGradesPage;
    }

    /**
     * @param int $id
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @param string $name
     * @return static
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }


    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $externalResultType
     * @return static
     */
    public function setExternalResultType($externalResultType)
    {
        $this->externalResultType = $externalResultType;
        return $this;
    }


    /**
     * @return string
     */
    public function getExternalResultType()
    {
        return $this->externalResultType;
    }
}
