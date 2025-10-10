<?php

declare(strict_types=1);

namespace Supseven\InlinePageModule\Listeners;

use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Domain\ConsumableString;

/**
 * Modify the variables of the
 *
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
#[AsEventListener(identifier: 'supseven/inline-page-module/page-layout-content-modifier')]
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
                document.querySelectorAll(".module-body > .typo3-messages").forEach(el => {
                    el.parentNode.removeChild(el);
                });
            });
        </script>
        ');
    }
}
