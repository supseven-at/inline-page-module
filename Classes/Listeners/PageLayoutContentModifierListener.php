<?php

declare(strict_types=1);

namespace Supseven\InlinePageModule\Listeners;

use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Core\Domain\ConsumableString;

/**
 * Modify the variables of the
 *
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
class PageLayoutContentModifierListener
{
    public function __invoke(ModifyPageLayoutContentEvent $event): void
    {
        $nonceAttr = '';
        $nonceAttribute = $event->getRequest()->getAttribute('nonce');

        if ($nonceAttribute instanceof ConsumableString) {
            $nonceAttr = ' nonce="' . htmlspecialchars($nonceAttribute->consume()) . '"';
        }

        $event->addHeaderContent('
        <script' . $nonceAttr . '>
            window.addEventListener("DOMContentLoaded", () => {
                document.querySelectorAll(".t3-page-column-header .t3-page-column-header-icons").forEach(el => {
                    el.parentNode.removeChild(el);
                });
            });
        </script>
        ');
    }
}
