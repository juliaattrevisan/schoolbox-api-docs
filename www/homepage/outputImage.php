<?php
//outputs an custom image and link to the homepage
$this->setBlock($componentHandle, 'previewimage', 'preview_block');
$this->setVar('IMAGE_TITLE', 'Feature Image');

// Make sure there a config option
if(empty($config['image'])) {
    $config['image'] = 'Preview Image';
    $config['pixel'] = 200;
}
$continue = true;

//get image id
$query = "SELECT id, description, comment FROM file WHERE category = '".$categoryID."' AND deleted = '0' AND description LIKE ".$this->db->smartQuote($config['image']);
$image = $this->db->getRow($query);
if(!is_array($image)) {
    //try to find another image if we cant find the configured one
    $query = "SELECT id, description, comment FROM file WHERE category = {$categoryID} AND deleted = '0' AND INSTR(mime_type, 'image/')";
    $image = $this->db->getRow($query);

    if(!is_array($image)) {
        $this->setVar('preview_block', '');
        $continue = false;
    }
}

if ($continue) {

    $this->setVar('IMAGE_TITLE', $image['description']);
    if($config['pixel']>600){$config['pixel']=600;}
    if($config['pixel']<20){$config['pixel']=20;}

    $imageurl = "/cms/image.php?id={$image['id']}&height={$config['pixel']}&width={$config['pixel']}";


    $this->setVar("PREVIEW_IMAGE_URL", $imageurl);
    $this->setVar("PREVIEW_IMAGE_DESC", scrubTEXT($image['comment']));
    if (Url::isUrl($config['url'])) {
        $this->setVar("CONTENT_URL_START", "<a href=\"/redir.php?url=".base64_encode($config['url'])."\" target=\"_blank\">");
        $this->setVar("CONTENT_URL_END", "</a>");
    } else {
        $this->setVar("CONTENT_URL_START", "");
        $this->setVar("CONTENT_URL_END", "");
    }
    $this->parse("preview_block", "previewimage");

}

//setup block for submission button
$this->setBlock($componentHandle, 'submit_image', 'submit_image_block');
if ($permission < WRITE_ACCESS)
	$this->setVar('submit_image_block', '');
else
	$this->parse('submit_image_block', 'submit_image');

?>