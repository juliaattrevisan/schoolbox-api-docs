<?php


namespace Payload;


class TextBoxComponent extends Base
{
    private $hompage_id;
    private $component_id;

    public function __construct()
    {
        Base::__construct();
        $this->hompage_id = '??';
        $this->component_id='??';
        $this->setPage('/textbox/textBoxForm.php');
        $this->setReferer('/homepage/'.$this->hompage_id.'/textBoxCreate/'.$this->component_id);
        $this->setFormParam('originID', $this->hompage_id);
        $this->setFormParam('categoryID', $this->hompage_id);
        $this->setFormParam('textBoxID', '');
        $this->setFormParam('componentID', $this->component_id);
        $this->setFormParam('textBoxHeading', 'someHeading');
        $this->setFormParam('textBoxBody', 'someText');
    }

    public function setHomepageId(int $homepage_id)
    {
        $this->hompage_id=$homepage_id;
        $this->setReferer('/homepage/'.$this->hompage_id.'/textBoxCreate/'.$this->component_id);
        $this->setFormParam('originID', $this->hompage_id);
        $this->setFormParam('categoryID', $this->hompage_id);
    }

    public function setComponentId(int $component_id)
    {
        $this->component_id=$component_id;
        $this->setReferer('/homepage/'.$this->hompage_id.'/textBoxCreate/'.$this->component_id);
        $this->setFormParam('componentID', $this->component_id);
    }

    public function setHeading(string $heading)
    {
        $this->setFormParam('textBoxHeading', $heading);
    }

    public function setBody(string $text)
    {
        $this->setFormParam('textBoxBody', $text);
    }

    public function deleteFormParams()
    {
        parent::deleteFormParams();
    }



    /**
     *         $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.';

    $page='/textbox/textBoxForm.php';
    $url = 'https://'.VAGRANT_DOMAIN.$page;

    $data =[
    'originID'=>$homepage_id,
    'categoryID'=>$homepage_id,
    'textBoxID'=>'',
    'componentID'=>$text_component->getId(),
    'textBoxHeading'=>$heading,
    'textBoxBody'=>$text
    ];
     */
}