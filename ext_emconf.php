<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Import WordPress into EXT:news',
    'description' => 'Basic importer for WordPress',
    'category' => 'fe',
    'author' => 'Georg Ringer',
    'author_email' => 'mail@ringer.it',
    'state' => 'stable',
    'clearCacheOnLoad' => true,
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-10.4.99',
        ]
    ],
];
