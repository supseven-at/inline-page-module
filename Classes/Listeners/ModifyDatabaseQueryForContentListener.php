<?php

declare(strict_types=1);

namespace Supseven\InlinePageModule\Listeners;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\Event\ModifyDatabaseQueryForContentEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;

/**
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
#[AsEventListener(identifier: 'supseven/inline-page-module/inline-content-listener')]
class ModifyDatabaseQueryForContentListener
{
    protected const int ImpossibleUid = -99;

    protected ?array $allowedUids = null;

    public function __construct(
        #[Autowire(service: 'typo3.request')]
        protected readonly ServerRequestInterface $request,
        protected readonly ConnectionPool $connectionPool,
        protected readonly ExtensionConfiguration $extensionConfiguration,
    ) {
    }

    public function __invoke(ModifyDatabaseQueryForContentEvent $event): void
    {
        $params = $this->request->getQueryParams();
        $pageId = (int)($params['id'] ?? 0);
        $result = [];

        if ($pageId > 0) {
            $parentField = $params['inline_field'] ?? '';
            $parentTable = $params['inline_table'] ?? '';
            $contentField = $GLOBALS['TCA'][$parentTable]['columns'][$parentField]['config']['foreign_field'] ?? '';
            $parentUid = (int)($params['inline_uid'] ?? 0);

            if (!empty($parentTable) && !empty($contentField) && $parentUid > 0) {
                if ($this->allowedUids === null) {
                    $qb = $this->connectionPool->getQueryBuilderForTable('tt_content');
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

                    $this->allowedUids = array_map(
                        static fn (array $row): int => (int)$row['uid'],
                        $qb->executeQuery()->fetchAllAssociative()
                    );

                    // If the parent has no elements yet, we restrict to an "impossible ID"
                    // to make sure no elements are shown
                    if (!$this->allowedUids) {
                        $this->allowedUids = [self::ImpossibleUid];
                    }
                }

                $result = $this->allowedUids;
            } else {
                // If the content elements are not restricted by a specific parent element (above condition)
                // and the extension configuration is set to list no content elements at all,
                // we restrict to an "impossible ID", too. But only if the current page is a sysfolder and this
                // behaviour is enabled in the extension configuration
                $page = BackendUtility::getRecord('pages', $pageId);

                if ($page['doktype'] === PageRepository::DOKTYPE_SYSFOLDER) {
                    $listAllContentElements = (int)$this->extensionConfiguration->get('inline_page_module', 'listAllContentElements');

                    if ($listAllContentElements !== 1) {
                        $result = [self::ImpossibleUid];
                    }
                }
            }

            if ($result) {
                $event->getQueryBuilder()->where($event->getQueryBuilder()->expr()->in('uid', $result));
            }
        }
    }
}
