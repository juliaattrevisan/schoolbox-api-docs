<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload;

/**
 * Encapsulates a textbox CREATE request
 */
class TextBoxComponent extends Request
{
    use PostTrait;

    public function __construct(int $homepageId, int $componentInstanceId)
    {
        $this->setReferer("/homepage/$homepageId/textBoxCreate/$componentInstanceId");
        $this->setFormParam('originID', $homepageId);
        $this->setFormParam('categoryID', $homepageId);
        $this->setFormParam('textBoxID', '');
        $this->setFormParam('componentID', $componentInstanceId);
        $this->setHeading('');
        $this->setBody('');
    }

    public function setHeading(string $heading): self
    {
        return $this->setFormParam('textBoxHeading', $heading);
    }

    public function setBody(string $text): self
    {
        return $this->setFormParam('textBoxBody', $text);
    }

    /** @inheritDoc */
    protected function getUrl(): string
    {
        return '/textbox/textBoxForm.php';
    }
}
