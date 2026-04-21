<?php

namespace Schoolbox\Nationality;

use Schoolbox\Mapper\Traits\Model as ModelTraits;
use Schoolbox\ValueLabelInterface;

class Model implements ValueLabelInterface
{
    use ModelTraits\Id;
    use ModelTraits\CreateCollection;

    private string $name;

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
