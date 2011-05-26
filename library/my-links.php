<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/model.php');

class MyLinks extends Model {

	public function MyLinks() {
		$this->_table = 'bookmarks';
		$this->_index = 'id';

		parent::Model();
	}

	/**
	 * Shares a link to the current user's ePortfolio.
	 * 
	 * @param <type> $linkID
	 */
	public function shareLink($linkID) {
		$query = "SELECT *
				  FROM bookmarks
				  WHERE id = '$linkID'
				  AND owner = '$_SESSION[id]'";

		$link = $this->db->getRow($query);

		if ($link) {
			require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/eportfolio-link.php');

			$ePortfolioLinkHandler = new ePortfolioLink();

			$exists = $ePortfolioLinkHandler->getAllByFields(array(
				'owner' => $_SESSION['id'],
				'url' => $link['url']
			));

			if (!$exists) {
				$newLink = array(
					'owner' => $_SESSION['id'],
					'url' => $link['url'],
					'submitted' => 'NOW()',
					'description' => $link['description']
				);

				$ePortfolioLinkHandler->create($newLink);

				return true;
			}
			else {
				return false;
			}
		}
		
		return false;
	}

    function linkExists($uid, $link) {
        $query = "SELECT id FROM bookmarks WHERE owner='$uid' AND url = '".mysql_real_escape_string($link)."'";
        return $this->db->getOne($query);
    }
}

?>
