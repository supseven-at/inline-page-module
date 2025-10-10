<?php

declare(strict_types=1);

namespace Supseven\InlinePageModule\Listeners;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TYPO3\CMS\Backend\Template\Components\Buttons\Action\ShortcutButton;
use TYPO3\CMS\Backend\Template\Components\ModifyButtonBarEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Remove change shortcut button if in inline view
 *
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
#[AsEventListener(identifier: 'supseven/inline-page-module/modify-buttons')]
class ModifyButtonsListener
{
    public function __construct(
        #[Autowire(service: 'typo3.request')]
        protected readonly ServerRequestInterface $request,
    ) {
    }

    public function __invoke(ModifyButtonBarEvent $event): void
    {
        $params = $this->request->getQueryParams();
        $table = $params['inline_table'] ?? '';
        $uid = $params['inline_uid'] ?? 0;

        if ($uid && $table) {
            $event->setButtons($this->filterRecursive($event->getButtons()));
        }
    }

    private function filterRecursive(array $src): array
    {
        $new = [];

        foreach ($src as $i => $v) {
            if (is_array($v)) {
                $new[$i] = $this->filterRecursive($v);
            } elseif (!$v instanceof ShortcutButton) {
                $new[$i] = $v;
            }
        }

        return $new;
    }
}
