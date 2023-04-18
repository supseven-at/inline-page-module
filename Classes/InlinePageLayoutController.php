<?php

declare(strict_types=1);

namespace Supseven\InlinePageModule;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\PageLayoutController;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;

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
     *
     * @param array $actions
     */
    protected function makeActionMenu(array $actions): void
    {
        $actionMenu = $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $actionMenu->setIdentifier('actionMenu');
        $actionMenu->setLabel('');

        $defaultKey = null;
        $foundDefaultKey = false;
        $defaultParams = [];

        if ($this->isInlineView()) {
            $defaultParams = [
                'inline_table' => $_GET['inline_table'],
                'inline_field' => $_GET['inline_field'],
                'inline_uid'   => $_GET['inline_uid'],
            ];
        }

        foreach ($actions as $key => $action) {
            $params = $defaultParams;
            $params['id'] = $this->id;
            $params['SET'] = ['function' => $key];
            $menuItem = $actionMenu
                ->makeMenuItem()
                ->setTitle($action)
                ->setHref((string)$this->uriBuilder->buildUriFromRoute($this->moduleName, $params));

            if (!$foundDefaultKey) {
                $defaultKey = $key;
                $foundDefaultKey = true;
            }

            if ((int)$this->MOD_SETTINGS['function'] === $key) {
                $menuItem->setActive(true);
                $defaultKey = null;
            }

            $actionMenu->addMenuItem($menuItem);
        }

        if (isset($defaultKey)) {
            $this->MOD_SETTINGS['function'] = $defaultKey;
        }

        $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($actionMenu);
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

    /**
     * Add a back-button to the flash messages area
     *
     * @return string
     */
    protected function getHeaderFlashMessagesForCurrentPid(): string
    {
        if ($this->isInlineView()) {
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

            return '<a href="' . htmlspecialchars($url)
                . '" style="margin-bottom: 15px" class="btn btn-default btn-info">Go back to '
                . htmlspecialchars($type)
                . ' »'
                . htmlspecialchars($title)
                . '«'
                . '</a>';
        }

        return parent::getHeaderFlashMessagesForCurrentPid();
    }

    /**
     * Use the title of the parent record as page title
     *
     * @return string
     */
    protected function getLocalizedPageTitle(): string
    {
        if ($this->isInlineView()) {
            return $this->getRecordTitle();
        }

        return parent::getLocalizedPageTitle();
    }

    /**
     * Build menu config and hard-code some settings
     *
     * This ensures to disable some buttons that have an undesired effect
     * in the context of an inline view
     *
     * @param ServerRequestInterface $request
     */
    protected function menuConfig(ServerRequestInterface $request): void
    {
        parent::menuConfig($request);

        if ($this->isInlineView()) {
            $this->modTSconfig['properties']['disableSearchBox'] = true;
            $this->modTSconfig['properties']['disableAdvanced'] = true;
        }
    }

    /**
     * Build custom language menu
     *
     * This is the same function as in the parent with some additional
     * parameters to keep in the inline view when switching languages
     */
    protected function makeLanguageMenu(): void
    {
        if (!$this->isInlineView()) {
            parent::makeLanguageMenu();

            return;
        }

        if (count($this->MOD_MENU['language']) > 1) {
            $languageMenu = $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
            $languageMenu->setIdentifier('languageMenu');
            $defaultParams = [
                'id'  => $this->id,
                'SET' => [
                    'language' => 0,
                ],
                'inline_table' => $_GET['inline_table'],
                'inline_field' => $_GET['inline_field'],
                'inline_uid'   => $_GET['inline_uid'],
            ];

            foreach ($this->MOD_MENU['language'] as $key => $language) {
                $params = $defaultParams;
                $params['SET']['language'] = $key;

                $menuItem = $languageMenu
                    ->makeMenuItem()
                    ->setTitle($language)
                    ->setHref((string)$this->uriBuilder->buildUriFromRoute($this->moduleName, $params));

                if ((int)$this->current_sys_language === $key) {
                    $menuItem->setActive(true);
                }

                $languageMenu->addMenuItem($menuItem);
            }

            $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($languageMenu);
        }
    }

    /**
     * Call the parent::main function and add some additional content
     *
     * @param ServerRequestInterface $request
     */
    protected function main(ServerRequestInterface $request): void
    {
        parent::main($request);

        // Remove the "edit this column" buttons via JS
        // because the function is not compatible with an inline view
        $this->moduleTemplate->addJavaScriptCode('inline_page_module', '
        window.addEventListener("DOMContentLoaded", () => {
            document.querySelectorAll(".t3-page-column-header .t3-page-column-header-icons").forEach(el => {
                el.parentNode.removeChild(el);
            });
        });
        ');
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
     * Get the title of the parent records of the inline elements
     *
     * @return string
     */
    private function getRecordTitle(): string
    {
        $title = '';
        $record = $this->getRecord();
        $labelField = $GLOBALS['TCA'][$record['_table']]['ctrl']['label'] ?? '';

        if ($labelField && $record && !empty($record[$labelField])) {
            $title = (string)$record[$labelField];
        }

        return $title;
    }
}
