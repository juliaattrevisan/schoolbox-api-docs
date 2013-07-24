<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$allPosts = \SocialStream\PostMapper::getInstance()->findAllForHomepage(10);

//$tree = new Schoolbox\Collection();

//array_reverse($allPosts);
//
//foreach($allPosts as $currentPost) {
//
//	/* Add parents
//	 */
//	if (!$currentPost->getParentId()) {
//		$tree->setOffset($currentPost->getPostId(), $currentPost);
//	}
//	/* Either a child or a grandchild
//	 */
//	else {
//		recursiveChildren($allPosts, $tree->getOffset($currentPost->getParentId()));
//	}
//}
//
//
//function recursiveChildren($allPosts, $node) {
//	foreach($allPosts as $currentPost) {
//		if ($currentPost->getParentId() == $node->getPostId()) {
//			$node->getChildren()->setOffset($currentPost->getPostId(), $currentPost);
//		}
//		else {
//			recursivChildren($allPosts,
//
//}


//function recursiveChildren($allPosts, $tree) {
//	foreach ($allPosts as $currentPost) {
//		$exists = $tree->getOffset($currentPost->getParentId());
//
//		if ($exists) {
//			echo "Move {$currentPost->getPostId()} into {$currentPost->getParentId()}";
////			$exists->setChildren(recursiveChildren($allPosts, $tree->getOffset($currentPost->getParentId())->getChildren()));
//		}
//		else {
//			$tree->setOffset($currentPost->getPostId(), $currentPost);
//		}
////		else if (!$tree->getOffset($currentPost->getPostId())) {
////			$tree->setOffset($currentPost->getPostId(), $currentPost);
////		}
////		else {
////		}
//	}
//
//	return $tree;
//}
//
//$output = recursiveChildren($allPosts, $tree);
//
//print_r($output);


//
//function buildTree($tree, $allPosts, $parentId = 0) {
//	foreach($allPosts as $currentPost) {
//		if ($currentPost->getParentId() == $parentId) {
//			$tree->add($currentPost);
//
//		}
//		else {
//			buildTree($tree, $allPosts, $currentPost->getPostId());
//		}
//
//
//	}
//
//	return $tree;
//}



//$tree = array();
//
///* Insert parents
// */
//foreach($allPosts as $currentPost) {
//	if (!$currentPost->getParentId()) {
//		$tree[$currentPost->getPostId()] = $currentPost;
//	}
//}
//
//print_r($tree);
///* Insert children
// */
//foreach($allPosts as $currentPost) {
//
//}
//
//
//function insert($parentId, $node, $tree) {
//	foreach($tree as $leaf) {
//		if ($leaf->getPostId() == $node->getParentId()) {
//			$leaf->getChildren()->add($node);
//
//			return;
//		}
//		else if ($leaf
//	}
//
//
//}


//function growTree($allPosts, $parent) {
//	foreach($allPosts as $currentPost) {
//		if (isset($parent[$currentPost->getParentId()])) {
//			if ($parent[$currentPost->getParentId()]->getPostId() == $currentPost->getParentId()) {
//
//				growTree($allPosts, $currentPost);
//				$result['children'][] = $currentPost;
//
//			}
//		}
//	}
//
//	return $result;
//}


//
//echo "<div style='width:50%;float:left;'>";
//new Alaress_Probe($allPosts);
//echo "</div>";
//
//$tree = new SocialStream_Post_String();
//$tree->setPostId(0);

//
//echo "<div style='width:50%;float:left;'>";
//new Alaress_Probe($tree);
//echo "</div>";

//function growTree($allPosts, $tree) {
//	foreach($allPosts as $currentPost) {
//		if (isset($tree['children'][$currentPost->getPostId()])) {
//			$tree[$currentPost->getPostId()];
//		}
//	}
//
//	return $tree;
//}
//
//$root = new SocialStream_Post_String();
//$root->setPostId(0);
//
//$tree = array();
//
///* Populate with parents
// */
//foreach($allPosts as $currentPost) {
//	if (!$currentPost->getParentId()) {
//		$tree[$currentPost->getPostId()] = $currentPost;
//	}
//}
//
//growTree($allPost, $tree);
//new Alaress_Probe(growTree($allPosts, $tree));


//$root = new SocialStream_Post_String();
//$root->setPostId(0);
//
//growTree($allPosts, $root);
//
//new Alaress_Probe($root);
//
//function growTree($allPosts, $tree) {
//	foreach($allPosts as $currentPost) {
//		if ($tree->getPostId() == $currentPost->getParentId()) {
//			$tree->getChildren()->add(clone $currentPost);
//			growTree($allPosts, $currentPost);
//		}
//	}
//}


//function findNode($postId, $allLeaves) {
//	foreach ($allLeaves as $currentLeaf) {
//		if ($currentLeaf->getPostId() == $postId) {
//			return $currentLeaf;
//		}
//	}
//
//	return findNode($postId, $currentLeaf);
//}
//
//function insertAsChild($node, $parent) {
//	$parent->getChildren()->add($node);
//}
//
//$root = new SocialStream_Post_String();
//$root->setPostId(0);
//
//$x = findNode(4, $root);
//new Alaress_Probe($x);
//


//***********
//$root = new SocialStream_Post_String();
//$root->setPostId(0);
//
//$x = growTree($allPosts, $root);
//
//
//function growTree($allPosts, $tree) {
//	$result = clone $tree;
//
//	foreach($allPosts as $currentPost) {
//		if ($result->getPostId() == $currentPost->getParentId()) {
//			$x = growTree($allPosts, $currentPost);
//
//			$result->getChildren()->add($x);
//		}
//	}
//
//	return $result;
//}

//***********








function findNode($tree, $id) {
	foreach($tree as $leaf) {
		if ($leaf->getPostId() == $id) {
			return $leaf;
		}
	}
}


$structure = array();

foreach($allPosts as $currentPost) {
	$structure[] = $currentPost;
}

foreach($structure as $currentPost) {
	if ($currentPost->getParentId()) {
		$destination = findNode($structure, $currentPost->getParentId());
		$destination->getChildren()->add($currentPost);

		$remove[] = $currentPost->getPostId();
	}
}

for ($i = 0; $i < count($structure); $i++) {
	if (in_array($structure[$i]->getPostId(), $remove)) {
		$structure[$i] = null;
	}
}

$tree = array_values(array_filter($structure));
new Alaress_Probe($x);






















































exit();