<?php
namespace MonsTec\Produck;

use ProduckPlugin;
use DateTime;

// prevent direct access
defined('ABSPATH') or die('Quidquid agis, prudenter agas et respice finem!');

class QuackPageContent implements DynamicPageContent {
    protected $connector;
    protected $title;
    protected $content;
    protected $headContent;

    function __construct($produckConnectorObject) {
        $this->connector = $produckConnectorObject;
    }

    public function create(Array $requestParams) {
        // get the Quack ID Param value
        $quackId = null;
        $titleParam = null;
        if (isset($requestParams)) {
            $quackId = (isset($requestParams['quackId'])) ? $requestParams['quackId'] : null;
            $quackId = (isset($requestParams['0'])) ? $requestParams['0'] : $quackId;
            $titleParam = (isset($requestParams['title'])) ? $requestParams['title'] : null;
            $titleParam = (isset($requestParams['1'])) ? $requestParams['1'] : $quackId;
        }

        //if somethings not right, show a not found page
        if ($quackId == null || !is_numeric($quackId)) {
            status_header(404);
            return ProduckPlugin::getNotFoundContent();
        }

        $quackData = $this->connector->getQuack($quackId);

        if ($quackData == null) {
            status_header(404);
            return ProduckPlugin::getNotFoundContent();
        }

        $askingQuacker = $quackData['askingId'];
        $question = $quackData['title'];

        $prettyUrlTitlePart = ProduckPlugin::transformTitleToUrlPart($question);
        $questionPath = '/quack/'.$quackId.'/'.$prettyUrlTitlePart;
        $questionLink = rtrim(home_url(), '/').$questionPath;

        // If there is a third path parameter or the title parameter does not semantically match the actual
        // title of the quack redirect to the correct URL
        if (isset($requestParams['2']) || $titleParam == null || $titleParam != $prettyUrlTitlePart) {
            wp_redirect($questionPath, 301);
        }

        $tags = '';
        if (isset($quackData['tags'])) {
            $tags = implode(',', $quackData['tags']);
        }

        $quackity = null;
        if (isset($quackData['quackity'])) {
            $quackity = round($quackData['quackity'], 1);
        } else {
            $quackity = (ProduckPlugin::getHash($question) % 1000) / 100.0;
        }

        $time = null;
        if (isset($quackData['timestamp'])) {
            $time = new DateTime($quackData['timestamp']);
        } else {
            $time = new DateTime();
        }
        $date = $time->format('d.m.Y');

        $views = null;
        if (isset($quackData['views'])) {
            $views = $quackData['views'];
        } else {
            $views = ($this->getHash($question . $date) % 1000);
        }

        // add head-content
        // Note: Wordpress don't seems to support adding arguments to the add_action function for use
        // in the callable. So the solution is to store the head content in an instance variable for
        // later access.
        // The reason why no locally defined anonymous functions are used here is namespacing. There us
        // no need for complicately prefixing the methods if functions belonging to a (prefixed) class are used.

        // change title the html-head
        $this->title = filter_var($question, FILTER_SANITIZE_STRING);;
        add_filter( 'pre_get_document_title', array($this, 'changePageTitle'));

        // add further head tags
        $headDescription = 'Lies mehr zu '.$question;
        $headBuilder = '<meta name="description" content="Lies mehr zu '.$headDescription.'"/>'.chr(0x0A);
        $headBuilder .= '<meta name="keywords" content="'.$tags.'"/>'.chr(0x0A);
        $headBuilder .= '<link rel="shortlink" href="'.$questionLink.'">'.chr(0x0A);
        $headBuilder .= '<meta property="og:title" content="'.$question.'"/>'.chr(0x0A);
        $headBuilder .= '<meta property="og:description" content="'.$headDescription.'"/>'.chr(0x0A);
        $headBuilder .= '<meta property="og:url" content="'.$questionLink.'"/>'.chr(0x0A);
        $headBuilder .= '<meta property="og:type" content="website"/>'.chr(0x0A);
        $headBuilder .= '<meta name="twitter:title" itemprop="title name" content="'.$question.'"/>'.chr(0x0A);
        $headBuilder .= '<meta name="twitter:description" itemprop="description" content="'.$headDescription.'"/>'.chr(0x0A);
        $headBuilder .= '<meta name="twitter:card" content="summary"/>'.chr(0x0A);
        $headBuilder .= '<meta name="mobile-web-app-capable" content="yes"/>';

        $this->headContent = $headBuilder;
        add_action('wp_head', array($this, 'echoHeadContent'));

        // construct main/body content
        $contentBuilder = '<div id="quack-single-chat-container" class="quacks-main">';
        $contentBuilder .= '<section id="quack-container" itemprop="mainEntity" itemscope itemtype="http://schema.org/Question">';
        $contentBuilder .=     '<div id="quacklist-wrapper" class="quacks-flush-left">';
        $contentBuilder .=       '<div id="quack-overview-list" quack-data="'.$quackId.'">';
        $contentBuilder .=         '<div id="quacks-stats-wrapper">';
        $contentBuilder .=           '<div itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating" class="quacks-votes">';
        $contentBuilder .=             '<div class="quacks-mini-counts"><meta itemprop="worstRating" content="1"/><span itemprop="ratingValue" title="'.round($quackity, 1).' rated quality">'.round($quackity, 1).'</span><meta itemprop="bestRating" content="10"/></div>';
        $contentBuilder .=             '<div>&nbsp;quackity</div>';
        $contentBuilder .=           '</div>';
        $contentBuilder .=           '<div itemprop="interactionStatistic" itemscope itemtype="http://schema.org/InteractionCounter" class="quacks-views">';
        $contentBuilder .=             '<div itemprop="interactionType" href="http://schema.org/WatchAction" class="quacks-mini-counts"><span itemprop="userInteractionCount" title="'.$views.' views">'.$views.'</span></div>';
        $contentBuilder .=             '<div>&nbsp;views</div>';
        $contentBuilder .=           '</div>';
        $contentBuilder .=           '<div class="quacks-question-date"><a href="https://produck.de" class="quacks-published" target="_blank"><span itemprop="dateCreated" datetime="'.$date.'" title="beantwortet am '.$date.'">vom '.$date.'</span></a></div>';
        $contentBuilder .=         '</div>';

        $answerCount = 0;
        foreach($quackData['messages'] as $message) {
            if ($askingQuacker == $message['userId']) {
                $messageSenderIdentifyingClass = 'quacks-left-duck';
                $author = 'Ducky';
                $itemPropAnswer = '';
            } else {
                $messageSenderIdentifyingClass = 'quacks-right-duck';
                $author = 'Experte';
                $itemPropAnswer = 'itemprop="acceptedAnswer" itemscope itemtype="http://schema.org/Answer"';
                $answerCount++;
            }

            $contentBuilder .= '<div class="quacks-dialogue-summary narrow '.$messageSenderIdentifyingClass.'" '.$itemPropAnswer.'>';
            $contentBuilder .=   '<div itemprop="author" itemscope itemtype="http://schema.org/Person" class="quacks-author"><span itemprop="name" class="quacks-author-name">'.$author.'</span></div>';
            $contentBuilder .=   '<div class="quacks-summary-text"><div class="quacks-text-line" itemprop="text"><span class="quacks-question-hyperlink">'.$message['text'].'</span></div></div>';
            $contentBuilder .= '</div>';
        }

        $contentBuilder .= '<meta itemprop="answerCount" content="'.$answerCount.'"/>';
        $contentBuilder .= '<div id="quacks-share-brand">';
        $contentBuilder .=   '<div class="quacks-share">';
        $contentBuilder .=       '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"/><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"></svg>';
        $contentBuilder .=   '</div>';

        if (ProduckPlugin::isPoweredByLinkAllowed() > 0) {
            $contentBuilder .= '<div id="quacks-host-wrap-wrapper">';
            $contentBuilder .=   '<a class="quacks-host-ref" href="'.ProduckPlugin::getCustomerProduckLink().'" target="_blank">';
            $contentBuilder .=     '<span>Provided by ProDuck</span>';
            $contentBuilder .=     '<img src="'.ProduckPlugin::getImageURL('ducky.png').'" alt="helpful ducky"/>';
            $contentBuilder .=   '</a>';
            $contentBuilder .= '</div>';
        }

        $contentBuilder .=         '</div>';
        $contentBuilder .=       '</div>';
        $contentBuilder .=     '</div>';
        $contentBuilder .=   '</section>';
        $contentBuilder .=   '<div class="quacks-more-quacks-ref">';
        $contentBuilder .=     '<a href="'.ProduckPlugin::getQuackOverviewUrl().'">Mehr Quacks</a>';
        $contentBuilder .=   '</div>';
        $contentBuilder .= '</div>';
        $contentBuilder .= '<div id="quacks-share-modal">';
        $contentBuilder .=   '<div id="quacks-modal-content">';
        $contentBuilder .=         '<h2>Quack Teilen</h2>';
        $contentBuilder .=         '<div id="quacks-url-box">';
        $contentBuilder .=           '<input class="quacks-share-url" value="" />';
        $contentBuilder .=           '<span class="quacks-content-copy">';
        $contentBuilder .=             '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" d="M0 0h24v24H0z"/><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm-1 4l6 6v10c0 1.1-.9 2-2 2H7.99C6.89 23 6 22.1 6 21l.01-14c0-1.1.89-2 1.99-2h7zm-1 7h5.5L14 6.5V12z"/></svg>';
        $contentBuilder .=           '</span>';
        $contentBuilder .=         '</div>';
        $contentBuilder .=     '<div id="quacks-share-btn-wrapper">';
        $contentBuilder .=     '<div class="quacks-share-shariff"></div>';
        $contentBuilder .=     '</div>';
        $contentBuilder .=   '</div>';
        $contentBuilder .=   '<div class="quacks-modal-footer">';
        $contentBuilder .=     '<a id="quacks-close-share-modal" href="#!" class="quacks-modal-close waves-effect waves-teal-light btn-flat">Schlie&#xDF;en</a>';
        $contentBuilder .=   '</div>';
        $contentBuilder .= '</div>';

        $this->content = $contentBuilder;
    }

    public function echoHeadContent() {
        echo $this->headContent;
    }

    public function changePageTitle() {
        return $this->title;
    }

    public function getPostTitle() {
        return $this->title;
    }

    public function getPostContent() {
        return $this->content;
    }
}
?>