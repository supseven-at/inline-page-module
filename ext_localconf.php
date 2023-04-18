<?php

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][\TYPO3\CMS\Backend\View\PageLayoutView::class]['modifyQuery']['inline_page_module'] =
    \Supseven\InlinePageModule\PageQueryModifier::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms']['db_new_content_el']['wizardItemsHook']['inline_page_module']
    = \Supseven\InlinePageModule\NewContentElementWizardModifier::class;

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\Controller\PageLayoutController::class] = [
    'className' => \Supseven\InlinePageModule\InlinePageLayoutController::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\View\BackendLayoutView::class] = [
    'className' => \Supseven\InlinePageModule\InlineBackendLayoutView::class,
];
