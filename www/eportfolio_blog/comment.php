<?php
$blogHandle = new Eportfolio_Blog_Item();
$commentHandle = new Eportfolio_Blog_Comment();

if ($this->arg[0] == 'new' && is_numeric($this->arg[1])) {
	$blog = $blogHandle->getByID($this->arg[1]);

    $eportfolioHandler = new Eportfolio_Container($blog['owner']);

    /* Check whether the current user can modify this person's eportfolio
     */
    if (!$eportfolioHandler->canEdit($blog['owner'])) {
		error(108);
    }

	if (!empty($blog) && !empty($_POST['body'])) {
		$commentHandle->create(array(
			'bid'  => $blog['id'],
			'body' => $_POST['body']
		));

		$this->backToTheFuture(1);
	}
    else {
		error(2);
    }
}
else if ($this->arg[0] == 'delete' && is_numeric($this->arg[1])) {
	$comment = $commentHandle->getByID($this->arg[1]);

    $blog = $blogHandle->getByID($comment['bid']);

    $eportfolioHandler = new Eportfolio_Container($blog['owner']);

    /* Check whether the current user can modify this person's eportfolio
     */
    if (!$eportfolioHandler->canEdit($blog['owner'])) {
        error(107);
    }

	$commentHandle->delete($this->arg[1]);

	$this->backToTheFuture(1);
}
?>