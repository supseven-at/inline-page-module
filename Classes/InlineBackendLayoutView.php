<?php

declare(strict_types=1);

namespace Supseven\InlinePageModule;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TYPO3\CMS\Backend\View\BackendLayout\BackendLayout;
use TYPO3\CMS\Backend\View\BackendLayout\DataProviderCollection;
use TYPO3\CMS\Backend\View\BackendLayoutView;
use TYPO3\CMS\Core\Page\PageLayoutResolver;
use TYPO3\CMS\Core\TypoScript\TypoScriptStringFactory;

/**
 * Extends the BackendLayoutView to provide custom
 * backend layouts for inline page module views
 *
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
class InlineBackendLayoutView extends BackendLayoutView
{
    /**
     * Create this object and initialize data providers.
     */
    public function __construct(
        protected readonly DataProviderCollection $dataProviderCollection,
        protected readonly TypoScriptStringFactory $typoScriptStringFactory,
        protected readonly PageLayoutResolver $pageLayoutResolver,
        #[Autowire(service: 'typo3.request')]
        protected readonly ServerRequestInterface $request,
    ) {
        parent::__construct($this->dataProviderCollection, $this->typoScriptStringFactory, $this->pageLayoutResolver);
    }

    /**
     * Try backend layout overrides for page inline module
     *
     * Fall back to core logic if not available for setup or current view
     *
     * @param int $pageId
     * @return BackendLayout|null
     */
    public function getBackendLayoutForPage(int $pageId): ?BackendLayout
    {
        $params = $this->request->getQueryParams();
        $table = $params['inline_table'] ?? '';
        $field = $params['inline_field'] ?? '';

        // Only try if parameters and configuration settings exist
        if ($table && $field) {
            $layoutKey = $GLOBALS['TCA'][$table]['columns'][$field]['config']['customControls']['page_module_view']['backend_layout'] ?? '';

            if ($layoutKey) {
                // If a configuration exists, find a "pagets__" layout or fall back to default
                $backendLayout =
                    $this->dataProviderCollection->getBackendLayout('pagets__' . $layoutKey, $pageId) ??
                    $this->dataProviderCollection->getBackendLayout($layoutKey, $pageId);

                if ($backendLayout) {
                    return $backendLayout;
                }
            }
        }

        // No override found, use core logic
        return parent::getBackendLayoutForPage($pageId);
    }
}
