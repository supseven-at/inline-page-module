<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Psr\Http\Message\ServerRequestInterface;
use Supseven\InlinePageModule\DependencyFactory;
use Supseven\InlinePageModule\InlineBackendLayoutView;
use Supseven\InlinePageModule\InlinePageLayoutController;
use Supseven\InlinePageModule\Listeners\ModifyButtonsListener;
use Supseven\InlinePageModule\Listeners\NewContentElementWizardModifier;
use Supseven\InlinePageModule\Listeners\PageLayoutContentModifierListener;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use TYPO3\CMS\Backend\Controller\PageLayoutController;
use TYPO3\CMS\Backend\View\BackendLayoutView;

return static function (ContainerConfigurator $container, ContainerBuilder $containerBuilder): void {
    $services = $container->services();
    $services->defaults()->public()->autowire()->autoconfigure();

    $services->set(ModifyButtonsListener::class);
    $services->set(NewContentElementWizardModifier::class);
    $services->set(PageLayoutContentModifierListener::class);
    $services->set(DependencyFactory::class);
    $services->set(InlineBackendLayoutView::class);
    $services->set(InlinePageLayoutController::class);

    $services->alias(BackendLayoutView::class, InlineBackendLayoutView::class);
    $services->alias(PageLayoutController::class, InlinePageLayoutController::class);

    $services->set('typo3.request', ServerRequestInterface::class)
        ->lazy()
        ->share(false)
        ->private()
        ->factory([service(DependencyFactory::class), 'getRequest']);
};
