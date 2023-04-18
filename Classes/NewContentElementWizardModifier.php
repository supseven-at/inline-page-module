<?php

declare(strict_types=1);

namespace Supseven\InlinePageModule;

use TYPO3\CMS\Backend\Wizard\NewContentElementWizardHookInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Hook to manipulate the wizard items
 *
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
class NewContentElementWizardModifier implements NewContentElementWizardHookInterface
{
    /**
     * Extend the URL of all wizard items to keep the "inline_"
     * parameters if we are in an inline view
     *
     * @param array $wizardItems
     * @param \TYPO3\CMS\Backend\Controller\ContentElement\NewContentElementController $parentObject
     */
    public function manipulateWizardItems(&$wizardItems, &$parentObject): void
    {
        $returnUrlParts = GeneralUtility::explodeUrl2Array($_GET['returnUrl'] ?? '');
        $parentTable = $returnUrlParts['inline_table'] ?? '';
        $parentField = $returnUrlParts['inline_field'] ?? '';
        $contentField = $GLOBALS['TCA'][$parentTable]['columns'][$parentField]['config']['foreign_field'] ?? '';
        $parentUid = (int)($returnUrlParts['inline_uid'] ?? 0);

        if ($parentUid > 0 && $parentTable && $contentField) {
            $defVals = [
                $contentField => $parentUid,
            ];

            $foreignMatches = $GLOBALS['TCA'][$parentTable]['columns'][$parentField]['config']['foreign_match_fields'] ?? [];

            foreach ($foreignMatches as $key => $value) {
                $defVals[$key] = $value;
            }
            $foreignTableField = $GLOBALS['TCA'][$parentTable]['columns'][$parentField]['config']['foreign_table_field'] ?? '';

            if ($foreignTableField) {
                $defVals[$foreignTableField] = $parentTable;
            }

            foreach ($wizardItems as &$wizard) {
                if (isset($wizard['params'])) {
                    foreach ($defVals as $field => $value) {
                        $wizard['tt_content_defValues'][$field] = (string)$value;
                        $wizard['params'] .= '&defVals[tt_content][' . $field . ']=' . rawurlencode((string)$value);
                    }
                }
            }
        }
    }
}
