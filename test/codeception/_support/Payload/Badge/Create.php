<?php

namespace Payload\Badge;
use Payload\Pastoral\FormTrait;
use Payload\PostTrait;

class Create extends \Payload\Request
{

    use PostTrait;
    use FormTrait;



    public function __construct(string $badgeavatarselector,string $category,string $body,string $name)
    {

        $allowasgoal= true;
        $tags = array();
        $this->setReferer("/learning/badge/create");
        $this->setFormParam('allow-as-goal', $allowasgoal);
        $this->setFormParam('badge-avatar-selector', $badgeavatarselector);
        $this->setFormParam('body', $body);
        $this->setFormParam('category', $category);
        $this->setFormParam('curriculumNodes', $tags);
        $this->setFormParam('icon', '');
        $this->setFormParam('image','');
        $this->setFormParam('name', $name);
        $this->setFormParam('tags', $tags);
        $this->setFormParam('yearLevelIds', $tags);

        $this->setHeader('content-type','application/json');
    }

    protected function getUrl(): string
    {
        return "/learning/badge";
    }
}
