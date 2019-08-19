<?php
/**
 * Contains a homepage template, made up really of only a name.
 * This is just a lookup table, new ones can be created in the admin system
 * Suspect that left and right column widths are no longer used
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */


namespace Schoolbox\Resource;


use Schoolbox\Mapper\Traits\Model\Id;

class HomepageTemplate {

    public const HOMEPAGE_NONE          = -1;
    public const HOMEPAGE_KEEP_EXISTING = 0;

    use Id;

    /**
     * Name of homepage template
     *
     * @var string
     */
    protected $name;

    /**
     * Width of left column for template
     *
     * @deprecated
     * @var float
     */
    protected $left_column_width;

    /**
     * Width of right column for template
     *
     * @deprecated
     * @var float
     */
    protected $right_column_width;

    /**
     * @deprecated
     * @param float $left_column_width
     * @return static
     */
    public function setLeftColumnWidth($left_column_width)
    {
        $this->left_column_width = $left_column_width;
        return $this;
    }

    /**
     * @deprecated
     * @return float
     */
    public function getLeftColumnWidth()
    {
        return $this->left_column_width;
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
     * @deprecated
     * @param float $right_column_width
     * @return static
     */
    public function setRightColumnWidth($right_column_width)
    {
        $this->right_column_width = $right_column_width;
        return $this;
    }

    /**
     * @deprecated
     * @return float
     */
    public function getRightColumnWidth()
    {
        return $this->right_column_width;
    }

}
