<?php
// Nanospell, rewritten to use PHP pspell for its backend.
$rawPostData = file_get_contents('php://input');
$post        = json_decode($rawPostData);

// TODO figure out how dictionaries should be installed
$dicts = [
    pspell_new('en'),
];

if (!$post->id) {
    die("JSON Data sent in unexpected format for Nanospell");
}
if ($post->method == 'spellcheck') {
    $structure = [
        'id'     => $post->id,
        'result' => []
    ];
    foreach ($post->params->words as $word) {
        $corrections = [];
        foreach ($dicts as $dict) {
            if (!pspell_check($dict, $word)) {
                $corrections = array_merge($corrections, pspell_suggest($dict, $word));
            }
        }
        if (!empty($corrections)) {
            $structure['result'][$word] = $corrections;
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
