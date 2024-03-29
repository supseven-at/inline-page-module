<?php

declare(strict_types=1);

namespace Supseven\InlinePageModule;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\PageLayoutController;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\Buttons\ButtonInterface;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\DropDownItemInterface;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\DropDownRadio;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\ViewHelpers\Be\InfoboxViewHelper;

/**
 * Overload the PageLayoutController to adjust the view for inline needs
 *
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
class InlinePageLayoutController extends PageLayoutController
{
    /**
     * "Cache" of the parent record the tt_content
     * records are referenced by.
     *
     * @var array|null
     */
    protected ?array $record = null;

    /**
     * Build custom action menu
     *
     * Same function as parent, but adds the "inline_X" parameters to the URLs
     * so we stay in the inline view when switching the action
     * @param ModuleTemplate $view
     * @param array $tsConfig
     */
    protected function makeActionMenu(ModuleTemplate $view, array $tsConfig): void
    {
        $defaultParams = [];

        if ($this->isInlineView()) {
            $defaultParams = [
                'inline_table' => $_GET['inline_table'],
                'inline_field' => $_GET['inline_field'],
                'inline_uid'   => $_GET['inline_uid'],
            ];
        }

        $languageService = $this->getLanguageService();
        $actions = [
            1 => $languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.view.layout'),
        ];

        // Find if there are ANY languages at all (and if not, do not show the language option from function menu).
        // The second check is for an edge case: Only two languages in the site and the default is not allowed.
        if (count($this->availableLanguages) > 1 || (int)array_key_first($this->availableLanguages) > 0) {
            $actions[2] = $languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.view.language_comparison');
        }
        // Page / user TSconfig blinding of menu-items
        $blindActions = $tsConfig['mod.']['web_layout.']['menu.']['functions.'] ?? [];
        foreach ($blindActions as $key => $value) {
            if (!$value && array_key_exists($key, $actions)) {
                unset($actions[$key]);
            }
        }

        $actionMenu = $view->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $actionMenu->setIdentifier('actionMenu');
        $actionMenu->setLabel('');
        $defaultKey = null;
        $foundDefaultKey = false;
        foreach ($actions as $key => $action) {
            $params = $defaultParams + ['id' => $this->id, 'function' => $key];
            $menuItem = $actionMenu
                ->makeMenuItem()
                ->setTitle($action)
                ->setHref((string)$this->uriBuilder->buildUriFromRoute('web_layout', $params));

            if (!$foundDefaultKey) {
                $defaultKey = $key;
                $foundDefaultKey = true;
            }

            if ((int)$this->moduleData->get('function') === $key) {
                $menuItem->setActive(true);
                $defaultKey = null;
            }
            $actionMenu->addMenuItem($menuItem);
        }

        if (isset($defaultKey)) {
            $this->moduleData->set('function', $defaultKey);
        }
        $view->getDocHeaderComponent()->getMenuRegistry()->addMenu($actionMenu);
    }

    protected function makeLanguageSwitchButton(ButtonBar $buttonbar): ?ButtonInterface
    {
        $languageDropDownButton = parent::makeLanguageSwitchButton($buttonbar);

        if ($languageDropDownButton && $this->isInlineView()) {
            $languageService = $this->getLanguageService();
            $defaultParams = [
                'inline_table' => $_GET['inline_table'],
                'inline_field' => $_GET['inline_field'],
                'inline_uid'   => $_GET['inline_uid'],
            ];

            $languageDropDownButton = $buttonbar->makeDropDownButton()
                ->setLabel($languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.language'))
                ->setShowLabelText(true);

            foreach ($this->MOD_MENU['language'] as $key => $language) {
                $siteLanguage = $this->availableLanguages[$key] ?? null;

                if (!$siteLanguage instanceof SiteLanguage) {
                    // Skip invalid language keys, e.g. "-1" for "all languages"
                    continue;
                }
                /** @var DropDownItemInterface $languageItem */
                $languageItem = GeneralUtility::makeInstance(DropDownRadio::class)
                    ->setActive($this->currentSelectedLanguage === $siteLanguage->getLanguageId())
                    ->setIcon($this->iconFactory->getIcon($siteLanguage->getFlagIdentifier()))
                    ->setHref((string)$this->uriBuilder->buildUriFromRoute('web_layout', [
                        'id'       => $this->id,
                        'function' => (int)$this->moduleData->get('function'),
                        'language' => $siteLanguage->getLanguageId(),
                    ] + $defaultParams))
                    ->setLabel($siteLanguage->getTitle());
                $languageDropDownButton->addItem($languageItem);
            }

            if ((int)$this->moduleData->get('function') !== 1) {
                /** @var DropDownItemInterface $allLanguagesItem */
                $allLanguagesItem = GeneralUtility::makeInstance(DropDownRadio::class)
                    ->setActive($this->currentSelectedLanguage === -1)
                    ->setIcon($this->iconFactory->getIcon('flags-multiple'))
                    ->setHref((string)$this->uriBuilder->buildUriFromRoute('web_layout', [
                        'id'       => $this->id,
                        'function' => (int)$this->moduleData->get('function'),
                        'language' => -1,
                    ] + $defaultParams))
                    ->setLabel($languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:multipleLanguages'));
                $languageDropDownButton->addItem($allLanguagesItem);
            }
        }

        return $languageDropDownButton;
    }

    /**
     * Ensure isPageEditable is false in inline view
     *
     * Otherwise the "Edit page" button would open the SysFolder the parent record is in
     *
     * @param int $languageId
     * @return bool
     */
    protected function isPageEditable(int $languageId): bool
    {
        return parent::isPageEditable($languageId) && !$this->isInlineView();
    }

    protected function generateMessagesForCurrentPage(ServerRequestInterface $request): array
    {
        if ($this->isInlineView()) {
            return $this->generateInlineHint();
        }

        return parent::generateMessagesForCurrentPage($request);
    }

    /**
     * Use the title of the parent record as page title
     *
     * @param int $currentSelectedLanguage
     * @param array $pageInfo
     * @return string
     */
    protected function getLocalizedPageTitle(int $currentSelectedLanguage, array $pageInfo): string
    {
        if ($this->isInlineView()) {
            return $this->getRecordTitle();
        }

        return parent::getLocalizedPageTitle($currentSelectedLanguage, $pageInfo);
    }

    /**
     * Ensure "quick-edit" functions
     *
     * @param int $languageId
     * @return bool
     */
    protected function isContentEditable(int $languageId): bool
    {
        return parent::isContentEditable($languageId) && !$this->isInlineView();
    }

    /**
     * Determine if the current URL is an inline view
     *
     * @return bool
     */
    protected function isInlineView(): bool
    {
        return (int)($this->pageinfo['doktype'] ?? 0) === PageRepository::DOKTYPE_SYSFOLDER && !empty($this->getRecord());
    }

    /**
     * Fetch the parent record of the displayed inline elements
     *
     * @return array
     */
    protected function getRecord(): array
    {
        if (!is_array($this->record)) {
            $this->record = [];
            $table = $_GET['inline_table'] ?? '';
            $uid = $_GET['inline_uid'] ?? 0;

            if ($uid && $table) {
                $record = BackendUtility::getRecord($table, $uid);

                if ($record) {
                    $this->record = $record;
                    $this->record['_table'] = $table;
                }
            }
        }

        return $this->record;
    }

    /**
     * @return array
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     */
    protected function generateInlineHint(): array
    {
        $record = $this->getRecord();
        $params = [
            'edit' => [
                $record['_table'] => [
                    $record['uid'] => 'edit',
                ],
            ],
        ];

        $url = (string)$this->uriBuilder->buildUriFromRoute('record_edit', $params);
        $title = $this->getRecordTitle();
        $type = $this->getLanguageService()->sL($GLOBALS['TCA'][$record['_table']]['ctrl']['title']);
        $label = $this->getLanguageService()->sL('LLL:EXT:inline_page_module/Resources/Private/Language/locallang_be.xlf:btn.back');

        $message = '<a href="'
            . htmlspecialchars($url)
            . '" class="btn btn-notice">'
            . sprintf($label, htmlspecialchars($type), htmlspecialchars($title))
            . '</a>';

        return [
            [
                'message' => $message,
                'state'   => InfoboxViewHelper::STATE_NOTICE,
            ],
        ];
    }

    /**
     * Get the title of the parent records of the inline elements
     *
     * @return string
     */
    private function getRecordTitle(): string
    {
        $record = $this->getRecord();

        return BackendUtility::getRecordTitle($record['_table'], $record);
    }
}
