<?php

declare(strict_types=1);

namespace Supseven\InlinePageModule\Listeners;

use TYPO3\CMS\Backend\Controller\Event\ModifyNewContentElementWizardItemsEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Hook to manipulate the wizard items
 *
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
class NewContentElementWizardModifier
{
    public function __invoke(ModifyNewContentElementWizardItemsEvent $event): void
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

            $wizardItems = $event->getWizardItems();

            foreach ($wizardItems as &$wizard) {
                foreach ($defVals as $field => $value) {
                    $wizard['tt_content_defValues'][$field] = (string)$value;
                }
            }

            $event->setWizardItems($wizardItems);
        }
    }
}
