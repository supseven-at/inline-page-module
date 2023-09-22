<?php

declare(strict_types=1);

namespace Supseven\InlinePageModule\Listeners;

use TYPO3\CMS\Backend\Template\Components\Buttons\Action\ShortcutButton;
use TYPO3\CMS\Backend\Template\Components\ModifyButtonBarEvent;

/**
 * Remove change shortcut button if in inline view
 *
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
class ModifyButtonsListener
{
    public function __invoke(ModifyButtonBarEvent $event): void
    {
        $table = $_GET['inline_table'] ?? '';
        $uid = $_GET['inline_uid'] ?? 0;

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
