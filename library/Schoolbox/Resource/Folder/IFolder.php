<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Folder;


use Alaress\Color\Color;

interface IFolder {

    // Any child of a node with this ID will be considered a root node
    // @see https://www.jstree.com/docs/json/
    // TODO consider changing this to -1 and perform -1 => '#' mapping for JS-tree implementation only
    public const ROOT_PARENT_ID = '#';
    public const ROOT_ID = 0;

    /**
     * For now, we need to support string too account for '#'
     *
     * @see self::ROOT_PARENT_ID
     * @return int|string
     */
    public function getId();


    /**
     * @return string
     */
    public function getName();


    /**
     * @return self
     */
    public function getParent();


    /**
     * A folder node type. This will probably map to a CSS class name.
     *
     * @return string
     */
    public function getNodeType(): string;


    /**
     * @return null|Color
     */
    public function getColour(): ?Color;


    /**
     * @return string
     */
    public function getCoverImage();
}
