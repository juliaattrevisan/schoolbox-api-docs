<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Tour\Step\Type\Tooltip;

use Schoolbox\Tour\Step\Type\AbstractModel;

class Model extends AbstractModel
{
    const TYPE = \Schoolbox\Tour\Step\Model::TYPE_TOOLTIP;

    /**
     * @var string|null
     */
    protected $javascriptSelector;

    /**
     * @return string|null
     */
    public function getJavascriptSelector(): ?string
    {
        return $this->javascriptSelector;
    }

    /**
     * @param string|null $javascriptSelector
     *
     * @return self
     */
    public function setJavascriptSelector(?string $javascriptSelector): self
    {
        $this->javascriptSelector = $javascriptSelector;
        return $this;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @link  http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return [
            'author'    => $this->getAuthor(),
            'title'     => $this->getTitle(),
            'css_class' => $this->getCssClass(),
            'body'      => $this->getBody(),
            'type'      => $this::TYPE,
            'selector'  => $this->getJavascriptSelector()
        ];
    }

}