<?php

declare(strict_types=1);

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\Controller\PageLayoutController::class] = [
    'className' => \Supseven\InlinePageModule\InlinePageLayoutController::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\View\BackendLayoutView::class] = [
    'className' => \Supseven\InlinePageModule\InlineBackendLayoutView::class,
];
