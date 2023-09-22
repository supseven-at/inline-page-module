<?php

declare(strict_types=1);

namespace Supseven\InlinePageModule\Listeners;

use TYPO3\CMS\Backend\Search\Event\ModifyQueryForLiveSearchEvent;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Hook to modify the query used to fetch records in the page view
 *
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
class PageQueryModifier
{
    public function __invoke(ModifyQueryForLiveSearchEvent $event): void
    {
        $table = $event->getTableName();
        $pageId = (int)($_GET['id'] ?? 0);

        if ($table === 'tt_content' && $pageId > 0) {
            $parentTable = $_GET['inline_table'] ?? '';
            $parentField = $_GET['inline_field'] ?? '';
            $contentField = $GLOBALS['TCA'][$parentTable]['columns'][$parentField]['config']['foreign_field'] ?? '';
            $parentUid = (int)($_GET['inline_uid'] ?? 0);

            if (!empty($parentTable) && !empty($contentField) && $parentUid > 0) {
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

                $uids = array_map(
                    fn (array $row): int => (int)$row['uid'],
                    $qb->execute()->fetchAllAssociative()
                );

                // If the parent has no elements yet, we restrict to an "impossible ID"
                // to make sure no elements are shown
                if (!$uids) {
                    $uids = [-99];
                }

                $event->getQueryBuilder()->andWhere($event->getQueryBuilder()->expr()->orX(
                    $event->getQueryBuilder()->expr()->in('uid', $uids),
                    $event->getQueryBuilder()->expr()->in('l18n_parent', $uids)
                ));
            }
        }
    }
}
