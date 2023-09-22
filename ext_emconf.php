<?php

declare(strict_types=1);

$EM_CONF[$_EXTKEY] = [
    'title'            => 'Inline Page Module',
    'description'      => 'Edit inline content records in a page module view',
    'category'         => 'be',
    'author'           => 'Georg Großberger',
    'author_email'     => 'office@supseven.at',
    'author_company'   => 'supseven',
    'state'            => 'beta',
    'clearCacheOnLoad' => true,
    'version'          => '2.0.0',
    'constraints'      => [
        'depends' => [
            'typo3' => '12.4.6-12.4.999',
        ],
    ],
];
