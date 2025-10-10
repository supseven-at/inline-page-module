<?php

declare(strict_types=1);

namespace Supseven\InlinePageModule;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * Helper for dependencies that are "value services"
 *
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
class DependencyFactory
{
    public function __construct(
        protected readonly LanguageServiceFactory $languageServiceFactory,
    ) {
    }

    public function getRequest(): ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'];
    }

    public function getLanguageService(): LanguageService
    {
        if (!empty($GLOBALS['LANG'])) {
            return $GLOBALS['LANG'];
        }

        return $this->languageServiceFactory->createFromUserPreferences($GLOBALS['BE_USER']);
    }
}
