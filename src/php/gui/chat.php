<?php
namespace MonsTec\Produck;

use ProduckPlugin;

// prevent direct access
defined('ABSPATH') or die('Quidquid agis, prudenter agas et respice finem!');

class Chat {
    public function getHtml() {
        $cid = ProduckPlugin::getCustomerId();
        $duckyImage = ProduckPlugin::getImageURL('ducky.png');
        $produckLink = ProduckPlugin::getCustomerProduckLink();

        $view = '<div id="produck-chat-block-home" class="passive" title="Hilfe und Angebote im Chat">';
        $view .=   '<div class="produck-chat-frame">';
        $view .=    '<span class="produck-headline-text">';
        $view .=     'Chatten und Kaufen';
        $view .=    '</span>';
        $view .=    '<div id="produck-close-chat" href="#">';
        $view .=      '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/><path d="M0 0h24v24H0z" fill="none"/></svg>';
        $view .=    '</div>';
        $view .=   '</div>';
        $view .=   '<div id="produck-chat-link" class="produck-chat-link" title="Chatten und Kaufen" data-cid="'.$cid.'">';
        $view .=     '<img class="produck-ducky" src="'.$duckyImage.'" alt="helpful ducky"/>';
        $view .=   '</div>';
        $view .=   '<div id="produck-iframe-wrapper">';
        $view .=     '<iframe id="produck-iframe" frameborder="0" allowfullscreen="" src="'.$produckLink.'"></iframe>';
        $view .=   '</div>';
        $view .= '</div>';

        return $view;
    }
}
?>