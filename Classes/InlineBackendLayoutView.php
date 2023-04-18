<?php

declare(strict_types=1);

namespace Supseven\InlinePageModule;

use TYPO3\CMS\Backend\View\BackendLayout\BackendLayout;
use TYPO3\CMS\Backend\View\BackendLayoutView;

/**
 * Extends the BackendLayoutView to provide custom
 * backend layouts for inline page module views
 *
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
class InlineBackendLayoutView extends BackendLayoutView
{
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
        $table = $_GET['inline_table'] ?? '';
        $field = $_GET['inline_field'] ?? '';

        // Only try if parameters and configuration settings exist
        if ($table && $field) {
            $layoutKey = $GLOBALS['TCA'][$table]['columns'][$field]['config']['customControls']['page_module_view']['backend_layout'] ?? '';

            if ($layoutKey) {
                // If a configuration exists, find a "pagets__" layout or fall back to default
                $backendLayout =
                    $this->getDataProviderCollection()->getBackendLayout('pagets__' . $layoutKey, $pageId) ??
                    $this->getDataProviderCollection()->getBackendLayout($layoutKey, $pageId);

                if ($backendLayout) {
                    return $backendLayout;
                }
            }
        }

        // No override found, use core logic
        return parent::getBackendLayoutForPage($pageId);
    }
}
