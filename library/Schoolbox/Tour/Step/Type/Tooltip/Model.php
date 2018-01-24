<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Tour\Step\Type\Tooltip;

use Schoolbox\Tour\Step\Type\AbstractModel;
use Schoolbox\Tour\Step;

class Model extends AbstractModel
{
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
            'css_class' => $this->getCssClass(),
            'body'      => $this->getBody(),
            'type'      => Step\Mapper::TYPE_TOOLTIP,
            'selector'  => $this->getJavascriptSelector()
        ];
    }

}