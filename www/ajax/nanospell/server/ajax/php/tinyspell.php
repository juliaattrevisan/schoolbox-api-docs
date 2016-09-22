<?php
// Nanospell, rewritten to use PHP pspell for its backend.
$rawPostData = file_get_contents('php://input');
$post        = json_decode($rawPostData);

if (!$post->id) {
    die("JSON Data sent in unexpected format for Nanospell");
}
if ($post->method == 'spellcheck') {
    $structure = [
        'id'     => $post->id,
        'result' => []
    ];
    $pspell = pspell_new('en');
    foreach ($post->params->words as $word) {
        if (!pspell_check($pspell, $word)) {
            $structure['result'][$word] = pspell_suggest($pspell, $word);
        }
    }
    echo json_encode($structure);
}
if ($post->method == 'listdicts') {
    echo json_encode([
        'id'    => $post->id,
        'dicts' => ['en'],
    ]);
}
