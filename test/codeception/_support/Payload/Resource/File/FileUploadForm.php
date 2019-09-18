<?php


namespace Payload\Resource\File;


class FileUploadForm extends Upload
{

    public function __construct($folder_id, string $fileName, string $description, string $fileHashInStorage)
    {
        $this->unsetHeader('Content-Type');
        $this->setFormParam('fileID', '');
        $this->setFormParam('component', '');
        $this->setFormParam('attachedFile', '');
        $this->setFormParam('Submit', 'Post File');
        $this->setFormParam('attachment', [$fileHashInStorage]);
        $this->setCategory($folder_id);
        $this->setDescription($fileName);
        $this->setComment($description);
        $this->setVisibility(1);
    }

    /**
     * Returns the Schoolbox URL (without query params) to send this request to
     *
     * @return string
     */
    protected function getUrl(): string
    {
        return '/cms/fileForm.php';
    }
}
