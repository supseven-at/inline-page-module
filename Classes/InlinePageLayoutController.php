<?php

declare(strict_types=1);

namespace Supseven\InlinePageModule;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Context\PageContext;
use TYPO3\CMS\Backend\Controller\PageLayoutController;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Service\PageLinkMessageProvider;
use TYPO3\CMS\Backend\Template\Components\Buttons\LanguageSelectorBuilder;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendLayout\BackendLayout;
use TYPO3\CMS\Backend\View\BackendLayout\DataProviderCollection;
use TYPO3\CMS\Backend\View\BackendLayoutView;
use TYPO3\CMS\Backend\View\Drawing\BackendLayoutRenderer;
use TYPO3\CMS\Backend\View\Drawing\DrawingConfiguration;
use TYPO3\CMS\Backend\View\PageLayoutContext;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

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

    protected ServerRequestInterface $request;

    protected string $inlineTable = '';
    protected string $inlineField = '';
    protected int $inlineUid = 0;

    public function __construct(
        protected readonly ComponentFactory $componentFactory,
        protected readonly IconFactory $iconFactory,
        protected readonly PageRenderer $pageRenderer,
        protected readonly UriBuilder $uriBuilder,
        protected readonly PageRepository $pageRepository,
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly EventDispatcherInterface $eventDispatcher,
        protected readonly ModuleProvider $moduleProvider,
        protected readonly BackendLayoutRenderer $backendLayoutRenderer,
        protected readonly BackendLayoutView $backendLayoutView,
        protected readonly TcaSchemaFactory $tcaSchemaFactory,
        protected readonly ConnectionPool $connectionPool,
        protected readonly LanguageSelectorBuilder $languageSelectorBuilder,
        protected readonly PageLinkMessageProvider $pageLinkMessageProvider,
        protected readonly DataProviderCollection $dataProviderCollection,
    ) {
    }

    protected function createPageLayoutContext(ServerRequestInterface $request): PageLayoutContext
    {
        $this->request = $request;
        $ctx = parent::createPageLayoutContext($request);

        if (!$this->isInlineView()) {
            return $ctx;
        }

        $title = $this->getRecordTitle();

        $this->pageContext = new PageContext(
            $this->pageContext->pageId,
            array_replace($this->pageContext->pageRecord, compact('title')),
            $this->pageContext->site,
            $this->pageContext->rootLine,
            $this->pageContext->pageTsConfig,
            $this->pageContext->selectedLanguageIds,
            $this->pageContext->languageInformation,
            $this->pageContext->pagePermissions,
        );

        $layoutKey = $GLOBALS['TCA'][$this->inlineTable]['columns'][$this->inlineField]['config']['customControls']['page_module_view']['backend_layout'] ?? '';
        $backendLayout = $ctx->getBackendLayout();

        if ($layoutKey) {
            // If a configuration exists, find a "pagets__" layout or fall back to default
            $pageId = $this->pageContext->pageId;
            $backendLayout =
                $this->dataProviderCollection->getBackendLayout('pagets__' . $layoutKey, $pageId) ??
                $this->dataProviderCollection->getBackendLayout($layoutKey, $pageId) ??
                $backendLayout;
        }

        return new class (
            $this->pageContext,
            $backendLayout,
            $ctx->getDrawingConfiguration(),
            $request,
            $title,
        ) extends PageLayoutContext {
            public function __construct(
                PageContext $pageContext,
                BackendLayout $backendLayout,
                DrawingConfiguration $drawingConfiguration,
                ServerRequestInterface $request,
                protected string $title,
            ) {
                parent::__construct(
                    $pageContext,
                    $backendLayout,
                    $drawingConfiguration,
                    $request,
                );
            }

            public function getLocalizedPageTitle(): string
            {
                return $this->title;
            }
        };
    }

    protected function addButtonsToButtonBar(ModuleTemplate $view, ServerRequestInterface $request): void
    {
        if (!$this->isInlineView()) {
            parent::addButtonsToButtonBar($view, $request);

            return;
        }

        $view->getDocHeaderComponent()->disableAutomaticReloadButton();
        $view->getDocHeaderComponent()->disableAutomaticShortcutButton();

        // Language selector
        $this->createLanguageSelector($view);
    }

    /**
     * Ensure isPageEditable is false in inline view
     *
     * Otherwise, the "Edit page" button would open the SysFolder the parent record is in
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
        $messages = parent::generateMessagesForCurrentPage($request);

        if ($this->isInlineView()) {
            $messages = array_filter(
                $messages,
                static fn (array $msg) => ($msg['state'] !== ContextualFeedbackSeverity::INFO),
            );

            array_unshift($messages, $this->generateInlineHint());
        }

        return $messages;
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
        return (int)($this->pageContext->pageRecord['doktype'] ?? 0) === PageRepository::DOKTYPE_SYSFOLDER && !empty($this->getRecord());
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
            $params = $this->request->getQueryParams();
            $table = $params['inline_table'] ?? '';
            $field = $params['inline_field'] ?? '';
            $uid = $params['inline_uid'] ?? 0;

            if ($uid && $table && $field) {
                $record = BackendUtility::getRecord($table, $uid);

                if ($record) {
                    $this->record = $record;
                    $this->inlineTable = $table;
                    $this->inlineField = $field;
                    $this->inlineUid = (int)$uid;
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
        $params = [
            'edit' => [
                $this->inlineTable => [
                    $this->inlineUid => 'edit',
                ],
            ],
        ];

        $url = (string)$this->uriBuilder->buildUriFromRoute('record_edit', $params);
        $title = $this->getRecordTitle();
        $type = $this->getLanguageService()->sL($GLOBALS['TCA'][$this->inlineTable]['ctrl']['title']);
        $label = $this->getLanguageService()->sL('LLL:EXT:inline_page_module/Resources/Private/Language/locallang_be.xlf:btn.back');

        $message = '<a href="'
            . htmlspecialchars($url)
            . '" class="btn btn-notice">'
            . sprintf($label, htmlspecialchars($type), htmlspecialchars($title))
            . '</a>';

        return [
            'message' => $message,
            'state'   => ContextualFeedbackSeverity::NOTICE,
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

        return BackendUtility::getRecordTitle($this->inlineTable, $record);
    }
}
