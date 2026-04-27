<?php

namespace Schoolbox\Nationality;

use Schoolbox\Mapper\Traits\Model as ModelTraits;
use Schoolbox\ValueLabelInterface;

class Model implements ValueLabelInterface
{
    use ModelTraits\Id;
    use ModelTraits\CreateCollection;

    private string $name;
    private string $code;

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @param string $code
     * @return $this
     */
    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    /**
     * Get nationality
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @inheritdoc
     * @return array
     */
    public function findValueLabelPair(): array
    {
        return [
            'value' => $this->getId(),
            'label' => $this->getName()
        ];
    }
}
