<?php

$EM_CONF['sluggi'] = [
    'title' => 'sluggi',
    'description' => 'The little TYPO3 slug helper',
    'category' => 'backend',
    'author' => 'Wolfgang Klinger',
    'author_email' => 'wolfgang@wazum.com',
    'state' => 'stable',
    'clearCacheOnLoad' => true,
    'author_company' => 'wazum.com',
    'version' => '9.9.9-UDES',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-10.4.99',
            'redirects' => '*'
        ]
    ]
];
