<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage;
$returnHandler = new SubmissionReturn;

if( is_numeric($_POST['uid']) && is_numeric($_POST['box_id']) && $_POST['markUpdate'] == 'true') {

	/* Ajax from mark sheet
	 */
    if ($_POST['mark']) {
        $returnHandler->securityCheck($_POST['box_id'], $_POST['uid']);

		/* If there isn't a piece of returned work, create it and give it the
		 * updated mark filled in on the markSheet
		 *
		 * Otherwise, just use the first record and update its value. The first
		 * record is the most recent return.
		 */
        $result = $returnHandler->getByUser($_POST['uid'], $_POST['box_id'], 1);

		/* Set up common values for the return
		 */
        $return = array(
			'box_id'    => $_POST['box_id'],
			'owner'     => $_POST['uid'],
			'mark'      => $_POST['mark'],
		);

        if (is_array($result)) {
			$return['id'] = $result[0]['id'];

			$returnHandler->update($return);
		}
        else {
            $returnHandler->create($return);
		}
    }
}
else if ( (

	  ( isset($_POST['comment']) && $_POST['comment'] ) ||
	  ( isset($_POST['mark']) && $_POST['mark'] ) ||
	  ( isset($_FILES['file']['name']) && $_FILES['file']['name'] )

	 ) &&

	 ( isset($_POST['uid']) && $_POST['uid'] ) &&
	 ( isset($_POST['box_id']) && $_POST['box_id'] )
  )
{

        $returnHandler->securityCheck($_POST['box_id'], $_POST['uid']);


        if($_FILES['file']['name']) {
            $allowedFileHandler = new AllowedFile;
            $allowedFileHandler->handleError($allowedFileHandler->fileSecurityCheck($_FILES['file']));
        }

        $return = array('box_id'    => $_POST['box_id'],
                        'owner'     => $_POST['uid'],
                        'comment'   => $_POST['comment'],
                        'mark'      => $_POST['mark'],
                        );

        $returnHandler->create($return, $_FILES['file']);

        $page->backToTheFuture(2);
}
/* Exceeded PHP max file size */
elseif ( $_SERVER['REQUEST_METHOD'] == 'POST' && $_SERVER['CONTENT_LENGTH'] > 0 && empty($_POST) && empty($_FILES) ) {
    error(10);
}
else {
    error(2);
}

?>