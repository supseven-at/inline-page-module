<?php

declare(strict_types=1);

namespace Supseven\InlinePageModule;

use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Configuration "manager" for the inline page module view
 *
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
class PageModuleSwitcher
{
    /**
     * Register the inline page module view for a table
     *
     * @param string $table Name of the table to register
     * @param string $backendLayout Name/Key of the backend layout to use
     * @param array $fields List of fields to limit the page module view to
     */
    public static function register(string $table, string $backendLayout = '', array $fields = []): void
    {
        foreach ($GLOBALS['TCA'][$table]['columns'] ?? [] as $field => $tca) {
            // Skip if not in fields, but only if fields are restricted manually
            if ($fields && !in_array($field, $fields, true)) {
                continue;
            }

            $config = $tca['config'] ?? [];

            if (($config['foreign_table'] ?? '') !== 'tt_content' || ($config['type'] ?? '') !== 'inline') {
                continue;
            }

            // Register custom control to show the button
            $GLOBALS['TCA'][$table]['columns'][$field]['config']['customControls']['page_module_view'] = [
                'userFunc'       => static::class . '->createButton',
                'backend_layout' => $backendLayout,
            ];

            // Ensure there is a TCA type definition for all "hidden" fields
            // Otherwise DataHandler will not fill them with defined defaults in new records
            $hiddenFields = [
                $GLOBALS['TCA'][$table]['columns'][$field]['config']['foreign_field'],
            ];

            $foreignMatches = $GLOBALS['TCA'][$table]['columns'][$field]['config']['foreign_match_fields'] ?? [];

            foreach (array_keys($foreignMatches) as $matchField) {
                $hiddenFields[] = $matchField;
            }
            $foreignTableField = $GLOBALS['TCA'][$table]['columns'][$field]['config']['foreign_table_field'] ?? '';

            if ($foreignTableField) {
                $hiddenFields[] = $foreignTableField;
            }

            foreach ($hiddenFields as $hiddenField) {
                if (empty($GLOBALS['TCA']['tt_content']['columns'][$hiddenField]['config'])) {
                    $GLOBALS['TCA']['tt_content']['columns'][$hiddenField]['config'] = [
                        'type' => 'input',
                    ];
                }
            }
        }
    }

    /**
     * Callback to render the button to switch into the inline page view
     *
     * Is automatically added in the "register" function
     *
     * @see self::register
     * @param array $parameters
     * @return string
     */
    public function createButton(array $parameters): string
    {
        $html = '';

        if (MathUtility::canBeInterpretedAsInteger($parameters['row']['uid'] ?? '')) {
            $label = $GLOBALS['LANG']->sL('LLL:EXT:inline_page_module/Resources/Private/Language/locallang_be.xlf:btn.openInPageModule');
            $icon = GeneralUtility::makeInstance(IconFactory::class)
                ->getIcon('apps-pagetree-page-content-from-page', Icon::SIZE_SMALL);

            $params = [
                'inline_table' => $parameters['table'],
                'inline_field' => $parameters['field'],
                'inline_uid'   => $parameters['row']['uid'],
                'id'           => $parameters['row']['pid'],
            ];

            $uri = (string)GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoute('web_layout', $params);
            $html = '<a class="btn btn-default" style="margin-top:15px" href="' . htmlspecialchars($uri) . '">' . $icon . ' ' . $label . '</a>';
        }

        return $html;
    }
}
