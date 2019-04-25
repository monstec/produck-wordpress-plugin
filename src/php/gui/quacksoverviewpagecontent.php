<?php
namespace MonsTec\Produck;
use ProduckPlugin;
use DateTime;

// prevent direct access
defined('ABSPATH') or die('Quidquid agis, prudenter agas et respice finem!');

class OverviewPageContent implements DynamicPageContent {
    protected $connector;
    protected $content;
    protected $headContent;

    function __construct($produckConnectorObject) {
        $this->connector = $produckConnectorObject;
    }

    public function create(Array $requestParams) {
        // change title, description and keywords
        add_filter( 'pre_get_document_title', function() {
            return "Quacks &Uuml;bersicht";
        });

        add_action('wp_head', function() {
            $meta = '<meta name="description" content="In der Quacks Übersicht finden Sie spannende Fragen von fachkundigen Experten beantwortet."/>'.chr(0x0A);
            $meta .= '<meta name="keywords" content="Quack, Produck, FAQ, QAQ, Question, Answer"/>'.chr(0x0A);
            echo $meta;
        });

        $quackData = $this->connector->getQuacks();

        $contentBuilder = '<div id="quacks-main-div" class="quacks-main block">';
        $contentBuilder .=   '<section itemscope="" itemtype="http://schema.org/Question" id="quacks-container" debog="2">';
        $contentBuilder .=     '<h2 class="quacks-h2">In der Quacks &#220;bersicht finden Sie spannende Fragen von fachkundigen Experten beantwortet.</h2>';
        $contentBuilder .=     '<div id="quacklist-wrapper" class="quacks-flush-left">';
        $contentBuilder .=       '<div id="quack-overview-list">';

        if ($quackData != null) {

            if (ProduckPlugin::isPoweredByLinkAllowed() > 0) {
                $contentBuilder .= '<div id="quacks-share-brand">';
                $contentBuilder .=   '<div id="quacks-host-wrap-wrapper">';
                $contentBuilder .=     '<a class="quacks-host-ref" href="'.ProduckPlugin::getCustomerProduckLink().'" target="_blank">';
                $contentBuilder .=       '<span>Provided by ProDuck</span>';
                $contentBuilder .=       '<img src="'.ProduckPlugin::getImageURL('ducky.png').'" alt="helpful ducky"/>';
                $contentBuilder .=     '</a>';
                $contentBuilder .=   '</div>';
                $contentBuilder .= '</div>';
            }

            foreach($quackData as $quack) {
                if (!isset($quack['title']) || strlen($quack['title']) < 1
                        || !isset($quack['quackId']) || strlen($quack['quackId']) < 1) {
                    continue;
                }

                $quackId = $quack['quackId'];
                $title = $quack['title'];
                $quackDisplayTarget = ProduckPlugin::isOpenQuackInNewPage() ? "_blank" : "";

                $prettyUrlTitlePart = ProduckPlugin::transformTitleToUrlPart($title);
                $questionPath = '/quack/'.$quackId.'/'.$prettyUrlTitlePart;
                $quackLink = rtrim(home_url(), '/').$questionPath;

                $quackity = null;
                if (isset($quack['quackity'])) {
                    $quackity = round($quack['quackity'], 1);
                } else {
                    $quackity = (ProduckPlugin::getHash($question) % 1000) / 100.0;
                }

                $views = null;
                if (isset($quack['views'])) {
                    $views = $quack['views'];
                } else {
                    $views = ($this->getHash($question . $date) % 1000);
                }

                $time = null;
                if (isset($quack['timestamp'])) {
                    $time = new DateTime($quack['timestamp']);
                } else {
                    $time = new DateTime();
                }
                $date = $time->format('d.m.Y');

                $contentBuilder .= '<div class="quacks-dialogue-summary narrow">';
                $contentBuilder .=   '<div class="quacks-stats-wrapper">';
                $contentBuilder .=     '<div class="quacks-votes">';
                $contentBuilder .=       '<div class="quacks-mini-counts">';
                $contentBuilder .=         '<span title="'.$quackity.' rated quality">'.$quackity.'</span>';
                $contentBuilder .=       '</div>';
                $contentBuilder .=       '<div>&nbsp;quackity</div>';
                $contentBuilder .=     '</div>';
                $contentBuilder .=     '<div class="quacks-views">';
                $contentBuilder .=       '<div class="quacks-mini-counts">';
                $contentBuilder .=         '<span title="'.$views.' views">'.$views.'</span>';
                $contentBuilder .=       '</div>';
                $contentBuilder .=       '<div>&nbsp;views</div>';
                $contentBuilder .=       '<div class="quacks-share"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"/><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/></svg></div>';
                $contentBuilder .=     '</div>';
                $contentBuilder .=   '</div>';
                $contentBuilder .=   '<div class="quacks-summary-text">';
                $contentBuilder .=     '<h3 class="quacks-text-line">';
                $contentBuilder .=       '<a class="quacks-question-hyperlink" href="'.$quackLink.'" target="'.$quackDisplayTarget.'">'.$title.'</a>';
                $contentBuilder .=     '</h3>';
                $contentBuilder .=     '<div class="quacks-tags">';

                foreach($quack['tags'] as $tag) {
                    $contentBuilder .= '<div class="quacks-chip">';
                    $contentBuilder .=   '<a href="'.$quackLink.'" title="show questions tagged '.$tag.'">'.$tag.'</a>';
                    $contentBuilder .= '</div>';
                }

                $contentBuilder .=    '</div>';
                $contentBuilder .=    '<div class="quacks-question-date">';
                $contentBuilder .=        '<span class="quacks-published" title="vom '.$date.'">vom '.$date.'</span>';
                $contentBuilder .=      '</a>';
                $contentBuilder .=    '</div>';
                $contentBuilder .=  '</div>';
                $contentBuilder .= '</div>';
            }

        } else {
            $contentBuilder .= '<p>';
            $contentBuilder .=   'Chatten und Kaufen auf <a href="'.ProduckPlugin::getCustomerProduckLink().'" target="_blank">ProDuck.de</a>!';
            $contentBuilder .= '</p>';
        }

        $contentBuilder .=       '</div>';
        $contentBuilder .=     '</div>';
        $contentBuilder .=   '</section>';
        $contentBuilder .= '</div>';

        $contentBuilder .= '<div id="quacks-share-modal">';
        $contentBuilder .=     '<div id="quacks-modal-content">';
        $contentBuilder .=         '<h2>Quack Teilen</h2>';
        $contentBuilder .=         '<div id="quacks-url-box">';
        $contentBuilder .=             '<input class="quacks-share-url" value="" />';
        $contentBuilder .=             '<span class="quacks-content-copy">';
        $contentBuilder .=              '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" d="M0 0h24v24H0z"/><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm-1 4l6 6v10c0 1.1-.9 2-2 2H7.99C6.89 23 6 22.1 6 21l.01-14c0-1.1.89-2 1.99-2h7zm-1 7h5.5L14 6.5V12z"/></svg>';
        $contentBuilder .=             '</span>';
        $contentBuilder .=         '</div>';
        $contentBuilder .=         '<div id="quacks-share-btn-wrapper">';
        $contentBuilder .=             '<div class="quacks-share-shariff"></div>';
        $contentBuilder .=         '</div>';
        $contentBuilder .=     '</div>';
        $contentBuilder .=     '<div class="quacks-modal-footer">';
        $contentBuilder .=         '<a id="quacks-close-share-modal" href="#!" class="quacks-modal-close waves-effect waves-teal-light btn-flat">Schlie&#xDF;en</a>';
        $contentBuilder .=     '</div>';
        $contentBuilder .= '</div>';

        $this->content = $contentBuilder;
    }

    public function echoHeadContent() {
        echo $this->headContent;
    }

    public function getPostTitle() {
        return "Quacks &#220;bersicht";
    }

    public function getPostContent() {
        return $this->content;
    }
}
?>