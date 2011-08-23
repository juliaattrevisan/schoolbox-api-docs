<?php

/* Outputs an custom image and link to the homepage
 */
$this->setBlock($componentHandle, "previewimage", "preview_block");

/* Make sure there a config option
 */
if (empty($config['pixel'])) {
    $config['pixel'] = 200;
}

$continue = true;

/* Get image id
 */
$query = "SELECT id, description FROM myFile WHERE owner = '{$userID}' AND deleted = '0' AND description LIKE '{$config['image']}'";

$image = $this->db->getRow($query);

if (!is_array($image)) {

    /* Try to find another image if we can't find the configured one.
     */
    $query = "SELECT id, description FROM myFile WHERE owner = '{$userID}' AND deleted = '0' AND INSTR(mime_type, 'image/')";

    $image = $this->db->getRow($query);

    if (!is_array($image)) {
        $this->setVar('preview_block', '');
        $continue = false;
    }
}

if ($continue) {
    if ($config['pixel'] > 600){
        $config['pixel'] = 600;
    }

    if ($config['pixel'] < 20) {
        $config['pixel'] = 20;
    }

    $imageurl = "/cms/image.php?myFile=1&id={$image['id']}&height={$config['pixel']}&width={$config['pixel']}";

    $this->setVar("PREVIEW_IMAGE_URL", $imageurl);
    $this->setVar("PREVIEW_IMAGE_DESC", scrubTEXT($image['comment']));

    if (Url::isUrl($config['url'])) {
        $this->setVar("CONTENT_URL_START", "<a href=\"/redir.php?url=".base64_encode($config['url'])."\" target=\"_blank\">");
        $this->setVar("CONTENT_URL_END", "</a>");
    }
    else {
        $this->setVar("CONTENT_URL_START", "");
        $this->setVar("CONTENT_URL_END", "");
    }

    $this->parse("preview_block", "previewimage");
}
?>