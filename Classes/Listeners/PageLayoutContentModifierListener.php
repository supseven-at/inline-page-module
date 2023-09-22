<?php

declare(strict_types=1);

namespace Supseven\InlinePageModule\Listeners;

use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;

/**
 * Modify the variables of the
 *
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
class PageLayoutContentModifierListener
{
    public function __invoke(ModifyPageLayoutContentEvent $event): void
    {
        $event->addHeaderContent('
        <script>
            window.addEventListener("DOMContentLoaded", () => {
                document.querySelectorAll(".t3-page-column-header .t3-page-column-header-icons").forEach(el => {
                    el.parentNode.removeChild(el);
                });
            });
        </script>
        ');
    }
}
