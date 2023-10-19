<?php

declare(strict_types=1);

namespace Supseven\InlinePageModule;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendLayout\ContentFetcher;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Versioning\VersionState;

/**
 * Hook to modify the query used to fetch records in the page view
 *
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
class InlineContentFetcher extends ContentFetcher
{
    private ?array $usedIds = null;

    protected function getResult($result): array
    {
        $output = [];

        if ($this->usedIds === null) {
            $this->usedIds = $this->getUsedIds();
        }

        while ($row = $result->fetchAssociative()) {
            BackendUtility::workspaceOL('tt_content', $row, -99, true);

            if ($row && !VersionState::cast($row['t3ver_state'] ?? 0)->equals(VersionState::DELETE_PLACEHOLDER)) {
                if (!$this->usedIds || in_array($row['uid'], $this->usedIds) || in_array($row['l18n_parent'], $this->usedIds)) {
                    $output[] = $row;
                }
            }
        }

        return $output;
    }

    protected function getUsedIds(): array
    {
        $pageId = (int)($_GET['id'] ?? 0);
        $result = [];

        if ($pageId > 0) {
            $parentTable = $_GET['inline_table'] ?? '';
            $parentField = $_GET['inline_field'] ?? '';
            $contentField = $GLOBALS['TCA'][$parentTable]['columns'][$parentField]['config']['foreign_field'] ?? '';
            $parentUid = (int)($_GET['inline_uid'] ?? 0);

            if (!empty($parentTable) && !empty($contentField) && $parentUid > 0) {
                if ($this->usedIds === null) {
                    $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
                    $qb->getRestrictions()->removeByType(HiddenRestriction::class);
                    $qb->select('uid');
                    $qb->from('tt_content');
                    $qb->where(
                        $qb->expr()->eq($contentField, $qb->createNamedParameter($parentUid)),
                        $qb->expr()->eq('pid', $qb->createNamedParameter($pageId)),
                    );

                    $foreignMatches = $GLOBALS['TCA'][$parentTable]['columns'][$parentField]['config']['foreign_match_fields'] ?? [];

                    foreach ($foreignMatches as $field => $value) {
                        $qb->andWhere($qb->expr()->eq($field, $qb->createNamedParameter($value)));
                    }

                    $foreignTableField = $GLOBALS['TCA'][$parentTable]['columns'][$parentField]['config']['foreign_table_field'] ?? '';

                    if ($foreignTableField) {
                        $qb->andWhere($qb->expr()->eq($foreignTableField, $qb->createNamedParameter($parentTable)));
                    }

                    $result = array_map(
                        fn (array $row): int => (int)$row['uid'],
                        $qb->executeQuery()->fetchAllAssociative()
                    );

                    // If the parent has no elements yet, we restrict to an "impossible ID"
                    // to make sure no elements are shown
                    if (!$result) {
                        $result = [-99];
                    }
                }
            }
        }

        return $result;
    }
}
