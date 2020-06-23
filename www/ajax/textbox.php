
<?php
require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

$textboxMapper = new \Schoolbox\Resource\Textbox\Mapper();
$return        = array();
/** @var \Schoolbox\Comms\News\Audience\ArticleResolver $articleResolver */
$articleResolver = $kernel->getDependencyInjector()->get('commsNewsArticleResolver');


$allTextboxes = $textboxMapper->search($articleResolver, $_SESSION['user'], $_GET['query']);

foreach ($allTextboxes->getResult() as $currentTextbox) {
    $return[] = array(
        'id'   => $currentTextbox->getId(),
        'name' => "{$currentTextbox->getCategory()->getName()}: {$currentTextbox->getHeading()}",
    );
}

header('Content-type: application/json');
echo json_encode($return);
