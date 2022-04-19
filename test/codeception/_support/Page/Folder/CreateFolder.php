<?php

namespace Page\Folder;

use AcceptanceTester;

class CreateFolder
{

    public const URL = '/resource/folder/0/create';
    public const FOLDER_NAME = 'name';
    public const HOMEPAGE_TEMPLATE = 'template';
    public const CREATE_GROUP = 'label[for=groupcheck]';
    public const CREATE_FOLDER_BUTTON = 'input[type=submit]';

    private AcceptanceTester $tester;

    /**
     * Constructor
     *
     * @param AcceptanceTester $i
     */
    public function __construct(AcceptanceTester $i)
    {
        $this->tester = $i;
    }

    /**
     * wait Create Folder Title to be presented
     */
    public function waitCreateFolderTitleToBePresented(): void
    {
        $this->tester->waitForText('Create Folder');
    }

    /**
     * type a folder name
     */
    public function typeName($folderName): void
    {
        $this->tester->fillField(self::FOLDER_NAME, $folderName);
    }

    /**
     * set a homepage template
     */
    public function setHomepageTemplate($option): void
    {
        $this->tester->selectOption(self::HOMEPAGE_TEMPLATE, $option);
    }

    /**
     * set access permission
     */
    public function setAccessPermission($accessPermission): void
    {
        $this->tester->click(['css' => 'label[class=radio-label][for=' . $accessPermission . ']']);
    }

    /**
     * set create group on
     */
    public function clickCreateGroupToggle(): void
    {
        $this->tester->click(self::CREATE_GROUP);
    }

    /**
     * click Create Folder button
     */
    public function clickCreateFolderButton(): void
    {
        $this->tester->click(self::CREATE_FOLDER_BUTTON);
    }
}
