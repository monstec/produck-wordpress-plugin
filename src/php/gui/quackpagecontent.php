<?php

namespace MonsTec\Produck;

use ProduckPlugin;
use DateTime;

// prevent direct access
defined('ABSPATH') or die('Quidquid agis, prudenter agas et respice finem!');

class QuackPageContent implements DynamicPageContent
{
    protected $connector;
    protected $title;
    protected $content;
    protected $headContent;

    function __construct($produckConnectorObject)
    {
        $this->connector = $produckConnectorObject;
    }

    public function create(array $requestParams)
    {
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
        $quackTitle = $quackData['title'];
        $prettyUrlTitlePart = ProduckPlugin::transformTitleToUrlPart($quackTitle);
        $questionPath = '/quack/' . $quackId . '/' . $prettyUrlTitlePart;

        $quackLink = rtrim(home_url(), '/') . $questionPath;


        if ($quackData == null) {
            status_header(404);
            return ProduckPlugin::getNotFoundContent();
        }

        $tags = '';
        if (isset($quackData['tags'])) {
            $tags = implode(',', $quackData['tags']);
        }

        $views = null;
        if (isset($quackData['views'])) {
            $views = $quackData['views'];
        } else {
            $views = ($this->getHash($quackTitle . $date) % 1000);
        }

        $quackity = null;
        if (isset($quackData['quackity'])) {
            $quackity = round($quackData['quackity'], 1);
        } else {
            $quackity = (ProduckPlugin::getHash($quackTitle) % 1000) / 100.0;
        }

        // add head-content
        // Note: Wordpress don't seems to support adding arguments to the add_action function for use
        // in the callable. So the solution is to store the head content in an instance variable for
        // later access.
        // The reason why no locally defined anonymous functions are used here is namespacing. There us
        // no need for complicately prefixing the methods if functions belonging to a (prefixed) class are used.

        // change title the html-head
        $this->title = htmlspecialchars($quackTitle, ENT_QUOTES, 'UTF-8');
        add_filter('pre_get_document_title', array($this, 'changePageTitle'));

        // add further head tags
        $headBuilder = '<meta name="description" content="Lies mehr zu ' . $quackTitle . '"/>' . chr(0x0A);
        $headBuilder .= '<meta name="keywords" content="' . $tags . '"/>' . chr(0x0A);
        $headBuilder .= '<link rel="canonical" href="' . $quackLink . '">' . chr(0x0A);
        $headBuilder .= '<link rel="shortlink" href="' . $quackLink . '">' . chr(0x0A);
        $headBuilder .= '<meta property="og:title" content="' . $quackTitle . '"/>' . chr(0x0A);
        if (!empty($quackData['tags'])) :
            foreach ($quackData['tags'] as $tag) :
                $headBuilder .= '<meta property="og:article:tag" content="'.$tag.'">';
            endforeach;
        endif;
        //$headBuilder .= '<meta property="og:description" content="' . $quackData['summary'] . '"/>' . chr(0x0A);
        $headBuilder .= '<meta property="og:url" content="' . $quackLink . '"/>' . chr(0x0A);
        $headBuilder .= '<meta property="og:type" content="website"/>' . chr(0x0A);
        $headBuilder .= '<meta name="twitter:title" itemprop="title name" content="' . $quackTitle . '"/>' . chr(0x0A);
        //$headBuilder .= '<meta name="twitter:description" itemprop="description" content="' . $quackData['summary'] . '"/>' . chr(0x0A);
        //$headBuilder .= '<meta name="twitter:card" content="summary"/>'.chr(0x0A);
        $headBuilder .= '<meta name="mobile-web-app-capable" content="yes"/>';

        $this->headContent = $headBuilder;
        add_action('wp_head', array($this, 'echoHeadContent'));


        // If there is a third path parameter or the title parameter does not semantically match the actual
        // title of the quack redirect to the correct URL
        if (isset($requestParams['2']) || $titleParam == null || $titleParam != $prettyUrlTitlePart) {
            wp_redirect($questionPath, 301);
            exit;
        }

        if (isset($quackData['chatId'])) {
            error_log("The reference type is CHAT.");

            // check if quack contains required properties
            if (!isset($quackData['title']) || !isset($quackData['messages']) || !isset($quackData['messages'][0])) {
                return null;
            }

            // check if the id of the asking user is set which is needed to arrange speech bubbles
            if (!isset($quackData['askingId'])) {
                // if it is not set fall back to the userId of the first message
                if (isset($quackData['messages'][0]['userId'])) {
                    $quackData['askingId'] = $quackData['messages'][0]['userId'];
                } else {
                    return null;
                }
            }

            $this->content = $this->renderChat($quackData, $quackId, $quackLink, $quackTitle, $tags, $views, $quackity);
        } elseif (isset($quackData['ownerId']) || isset($quackData['authorId'])) {
            error_log("The reference type is ARTICLE.");
            $this->content = $this->renderArticle($quackData, $quackId, $quackLink, $quackTitle, $tags, $views, $quackity);
        } else {
            error_log("The reference type is neither ARTICLE nor CHAT.");
            return null;
        }
    }

    private function renderArticle($quackData, $quackId, $quackLink, $quackTitle, $tags, $views, $quackity)
    {
        ob_start();
?>
        <div id="quackSingleContainer" class="main" itemprop="mainEntity" itemscope itemtype="https://schema.org/Article" data-quack-id="<?php echo htmlspecialchars($quackData['id']); ?>" data-article-id="<?php echo htmlspecialchars($quackData['id']); ?>">
            <section id="quack-info-bar">
                <div id="quack-category-block">
                    <div class="chip"><span>Externer Artikel</span></div>
                </div>
                <div id="stats-wrapper">
                    <div class="votes" title="Bewertungen" itemscope itemtype="https://schema.org/CreativeWorkSeries">
                        <meta itemprop="headline name" content="<?php echo htmlspecialchars($quackData['title']); ?>" />
                        <?php if ($quackData['rating'] > 0.0) : ?>
                            <div class="mini-counts" itemprop="aggregateRating" itemscope itemtype="https://schema.org/AggregateRating">
                                <span id="aggregatedRatingLabel" itemprop="ratingValue"><?php echo htmlspecialchars(number_format($quackData['rating'], 1)); ?></span>&nbsp;(<span id="ratingCountLabel" itemprop="reviewCount"><?php echo htmlspecialchars($quackData['ratingCount']); ?></span>)<div class="flex-box"><i class="material-icons">star_border</i></div>
                            </div>
                        <?php else : ?>
                            <div class="mini-counts">
                                <span class="hide">0.0</span><span id="aggregatedRatingLabel">&dash;&#46;&dash;</span>&nbsp;(<span id="ratingCountLabel">0</span>)<div class="flex-box"><i class="material-icons">star_border</i></div>
                            </div>
                        <?php endif; ?>
                        <meta itemprop="bestRating" content="5">
                        <meta itemprop="worstRating" content="1">
                    </div>
                    <div class="stats-elem">&verbar;</div>
                    <div class="quack-date">
                        <?php if (isset($quackData['lastModifiedTime'])) : ?>
                            <?php
                            $modifiedTime = new DateTime($quackData['lastModifiedTime']);
                            $formattedModifiedTime = $modifiedTime->format('d.m.Y');
                            ?>
                            <span class="published" datetime="<?php echo htmlspecialchars($quackData['lastModifiedTime']); ?>" title="Zuletzt aktualisiert am <?php echo htmlspecialchars($formattedModifiedTime); ?>"><?php echo htmlspecialchars($formattedModifiedTime); ?></span><i title="Zuletzt aktualisiert am <?php echo htmlspecialchars($formattedModifiedTime); ?>" class="material-icons">autorenew</i>
                        <?php else : ?>
                            <?php
                            $publicationTime = new DateTime($quackData['publicationTime']);
                            $formattedPublicationTime = $publicationTime->format('d.m.Y');
                            ?>
                            <span class="published" datetime="<?php echo htmlspecialchars($quackData['publicationTime']); ?>" title="veröffentlicht am <?php echo htmlspecialchars($formattedPublicationTime); ?>"><?php echo htmlspecialchars($formattedPublicationTime); ?></span><i title="veröffentlicht am <?php echo htmlspecialchars($formattedPublicationTime); ?>" class="material-icons">public</i>
                        <?php endif; ?>
                    </div>
                    <div class="share-brand">
                        <div class="share" title="Seite teilen">
                            <i class="material-icons">share</i>
                        </div>
                    </div>
                </div>
            </section>
            <section id="quack-container">
                <?php if (!empty($quackData['summary'])) : ?>
                    <div itemprop="abstract" class="page-summary-block">
                        <div id="articleSummary" class="page-summary-text-block">
                            <p id="articleSummaryLabel" itemprop="text"><?php echo htmlspecialchars($quackData['summary']); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                <div id="quack-content-body">
                    <div id="quack-content-wrapper" data-quack-data="<?php echo htmlspecialchars($quackData['id']); ?>">
                        <?php foreach ($quackData['blocks'] as $block) : ?>
                            <div class="quack-article-block" itemscope>
                                <div class="js-block-bubble center-block" id="articleBlock<?php echo htmlspecialchars($block['id']); ?>" data-block-id="<?php echo htmlspecialchars($block['id']); ?>" data-user-id="<?php echo htmlspecialchars($quackData['userId']); ?>" data-author-id="<?php echo htmlspecialchars($quackData['authorId']); ?>">
                                    <div class="quack-outer-block">
                                        <div id="blockTextLabel<?php echo htmlspecialchars($block['id']); ?>" itemprop="text" class="quack-inner-block">
                                            <?php echo $block['text']; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="vertical-spacer<?php echo ($block === end($quackData['blocks'])) ? ' small' : ' medium'; ?>"></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!empty($quackData['tags'])) : ?>
                            <div class="quack-tag-block" data-tags="<?php echo htmlspecialchars(json_encode($quackData['tags'])); ?>">
                                <div class="tags">
                                    <?php foreach ($quackData['tags'] as $tag) : ?>
                                        <div class="chip">
                                            <span><?php echo htmlspecialchars($tag); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="interaction-bar share-brand bottom">
                            <div class="report" title="Beitrag melden">
                                <a class="report-link" href="mailto:report-quack@monstec.de?subject=Quack%20melden">Melden</a>
                            </div>
                            <div class="share" title="Seite teilen">Teilen <i class="material-icons">share</i></div>
                        </div>
                        <?php if (!empty($quackData['nickname'])) : ?>
                            <div class="quack-author-block">
                                <div id="author-details-block" class="info-card" data-author-id="<?php echo htmlspecialchars($quackData['authorId']); ?>">
                                    <div class="card-wrapper <?php echo !empty($quackData['longDescr']) ? 'long-version' : 'short-version'; ?>">
                                        <div class="card-image-block">
                                            <?php 
                                                $imagePath = null;
                                                if (strpos($quackData['portraitImg'], '/assets') === 0) {
                                                    $imagePath = 'https://produck.de' . $quackData['portraitImg'];
                                                } else {
                                                    $imagePath = $quackData['portraitImg'];
                                                }
                                            
                                                if (!empty($quackData['primaryImage'])) : 
                                                    ?>
                                                    <a class="image-wrapper" target="_blank" href="https://produck.de/profile/<?php echo htmlspecialchars($quackData['authorId']); ?>">
                                                        <img src="<?php echo htmlspecialchars($imagePath); ?>" loading="lazy" class="image" alt="Autoren Portrait">
                                                    </a> 
                                            <?php else : ?>
                                                <a class="image-wrapper" target="_blank" href="https://produck.de/profile/<?php echo htmlspecialchars($quackData['authorId']); ?>">
                                                    <img src="<?php ProduckPlugin::getImageURL('ducky.png') ?>" loading="lazy" class="image-placeholder" alt="Autoren Portrait">
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-text-block">
                                            <a class="author-name" target="_blank" href="https://produck.de/profile/<?php echo htmlspecialchars($quackData['authorId']); ?>">
                                                <span><?php echo !empty($quackData['fullName']) ? htmlspecialchars($quackData['fullName']) : htmlspecialchars($quackData['nickname']); ?></span>
                                            </a>
                                            <?php if (!empty($quackData['specDescr'])) : ?>
                                                <a class="author-expertise" target="_blank" href="https://produck.de/profile/<?php echo htmlspecialchars($quackData['authorId']); ?>">
                                                    <span data-i18n="quackpage.speciality"></span>&nbsp;<span><?php echo htmlspecialchars($quackData['specDescr']); ?></span>
                                                </a>
                                            <?php else : ?>
                                                <span class="author-expertise">Autor auf ProDuck.de</span>
                                            <?php endif; ?>
                                            <?php if (!empty($quackData['longDescr'])) : ?>
                                                <a class="author-description" target="_blank" href="https://produck.de/profile/<?php echo htmlspecialchars($quackData['authorId']); ?>">
                                                    <span><?php echo htmlspecialchars(mb_strimwidth($quackData['longDescr'], 0, 500, '...')); ?></span>
                                                </a>
                                            <?php endif; ?>
                                            <a class="profile-ref prdk-link" target="_blank" href="https://produck.de/profile/<?php echo htmlspecialchars($quackData['authorId']); ?>" data-i18n="quackpage.profile_ref">Zum Profil</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
        <div id="share-modal" class="modal">
            <div id="modal-content">
                <h2 data-i18n="text.share_link"></h2>
                <div id="url-box">
                    <input class="share-url" value="" /> <i class="material-icons content-copy" data-i18n="[title]text.copy">content_copy</i>
                </div>
                <div id="share-btn-wrapper">
                    <div class="share-shariff"></div>
                </div>
            </div>
            <div class="modal-footer">
                <a id="close-share-modal" href="#!" class="modal-close waves-effect waves-teal-light btn-flat" data-i18n="text.close"></a>
            </div>
        </div>
    <?php
        return ob_get_clean();
    }

    private function renderChat($quackData, $quackId, $quackLink, $quackTitle, $tags, $views, $quackity)
    {
        $askingQuacker = $quackData['askingId'];

        $isodate = null;
        if (isset($quackData['timestamp'])) {
            $isodate = new DateTime($quackData['timestamp']);
        } else {
            $isodate = new DateTime();
        }
        $date = $isodate->format('d.m.Y');


        // add head-content
        // Note: Wordpress don't seems to support adding arguments to the add_action function for use
        // in the callable. So the solution is to store the head content in an instance variable for
        // later access.
        // The reason why no locally defined anonymous functions are used here is namespacing. There us
        // no need for complicately prefixing the methods if functions belonging to a (prefixed) class are used.

        // change title the html-head
        $this->title = htmlspecialchars($quackTitle, ENT_QUOTES, 'UTF-8');
        add_filter('pre_get_document_title', array($this, 'changePageTitle'));

        // // construct main/body content
        // $contentBuilder = '<div id="quack-single-chat-container" class="quacks-main">';
        // $contentBuilder .= '<section id="quack-container" itemprop="mainEntity" itemscope itemtype="http://schema.org/Question">';
        // $contentBuilder .=     '<div id="quacklist-wrapper" class="quacks-flush-left">';
        // $contentBuilder .=       '<div id="quack-overview-list" quack-data="' . $quackId . '">';
        // $contentBuilder .=         '<div id="quacks-stats-wrapper">';
        // $contentBuilder .=           '<div itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating" class="quacks-votes">';
        // $contentBuilder .=             '<div class="quacks-mini-counts"><meta itemprop="worstRating" content="1"/><span itemprop="ratingValue" title="' . round($quackity, 1) . ' rated quality">' . round($quackity, 1) . '</span><meta itemprop="bestRating" content="10"/></div>';
        // $contentBuilder .=             '<div>&nbsp;Quackity</div>';
        // $contentBuilder .=           '</div>';
        // $contentBuilder .=           '<div itemprop="interactionStatistic" itemscope itemtype="http://schema.org/InteractionCounter" class="quacks-views">';
        // $contentBuilder .=             '<div itemprop="interactionType" href="http://schema.org/WatchAction" class="quacks-mini-counts"><span itemprop="userInteractionCount" title="' . $views . ' Views">' . $views . '</span></div>';
        // $contentBuilder .=             '<div>&nbsp;Views</div>';
        // $contentBuilder .=           '</div>';
        // $contentBuilder .=           '<div class="quacks-question-date"><a href="https://produck.de" class="quacks-published" target="_blank"><span itemprop="dateCreated" datetime="' . $date . '" title="beantwortet am ' . $date . '">vom ' . $date . '</span></a></div>';
        // $contentBuilder .=         '</div>';

        // $answerCount = 0;
        // foreach ($quackData['messages'] as $message) {
        //     if ($askingQuacker == $message['userId']) {
        //         $messageSenderIdentifyingClass = 'quacks-left-duck';
        //         $author = 'Ducky';
        //         $itemPropAnswer = '';
        //     } else {
        //         $messageSenderIdentifyingClass = 'quacks-right-duck';
        //         $author = 'Experte';
        //         $itemPropAnswer = 'itemprop="acceptedAnswer" itemscope itemtype="http://schema.org/Answer"';
        //         $answerCount++;
        //     }

        //     $contentBuilder .= '<div class="quacks-dialogue-summary narrow ' . $messageSenderIdentifyingClass . '" ' . $itemPropAnswer . '>';
        //     $contentBuilder .=   '<div itemprop="author" itemscope itemtype="http://schema.org/Person" class="quacks-author"><span itemprop="name" class="quacks-author-name">' . $author . '</span></div>';
        //     $contentBuilder .=   '<div class="quacks-summary-text"><div class="quacks-text-line" itemprop="text"><span class="quacks-question-hyperlink">' . $message['text'] . '</span></div></div>';
        //     $contentBuilder .= '</div>';
        // }

        // $contentBuilder .= '<meta itemprop="answerCount" content="' . $answerCount . '"/>';
        // $contentBuilder .= '<div id="quacks-share-brand">';
        // $contentBuilder .=   '<div class="quacks-share">';
        // $contentBuilder .=       '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"/><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"></svg>';
        // $contentBuilder .=   '</div>';

        // if (ProduckPlugin::isPoweredByLinkAllowed() > 0) {
        //     $contentBuilder .= '<div id="quacks-host-wrap-wrapper">';
        //     $contentBuilder .=   '<a class="quacks-host-ref" href="' . ProduckPlugin::getCustomerProduckLink() . '" target="_blank">';
        //     $contentBuilder .=     '<span>Provided by ProDuck</span>';
        //     $contentBuilder .=     '<img src="' . ProduckPlugin::getImageURL('ducky.png') . '" alt="helpful ducky"/>';
        //     $contentBuilder .=   '</a>';
        //     $contentBuilder .= '</div>';
        // }

        // $contentBuilder .=         '</div>';
        // $contentBuilder .=       '</div>';
        // $contentBuilder .=     '</div>';
        // $contentBuilder .=   '</section>';
        // $contentBuilder .=   '<div class="quacks-more-quacks-ref">';
        // $contentBuilder .=     '<a href="' . ProduckPlugin::getQuackOverviewUrl() . '">Mehr Q&As</a>';
        // $contentBuilder .=   '</div>';
        // $contentBuilder .= '</div>';
        // $contentBuilder .= '<div id="quacks-share-modal">';
        // $contentBuilder .=   '<div id="quacks-modal-content">';
        // $contentBuilder .=         '<h2>Q&A Teilen</h2>';
        // $contentBuilder .=         '<div id="quacks-url-box">';
        // $contentBuilder .=           '<input class="quacks-share-url" value="" />';
        // $contentBuilder .=           '<span class="quacks-content-copy">';
        // $contentBuilder .=             '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" d="M0 0h24v24H0z"/><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm-1 4l6 6v10c0 1.1-.9 2-2 2H7.99C6.89 23 6 22.1 6 21l.01-14c0-1.1.89-2 1.99-2h7zm-1 7h5.5L14 6.5V12z"/></svg>';
        // $contentBuilder .=           '</span>';
        // $contentBuilder .=         '</div>';
        // $contentBuilder .=     '<div id="quacks-share-btn-wrapper">';
        // $contentBuilder .=     '<div class="quacks-share-shariff"></div>';
        // $contentBuilder .=     '</div>';
        // $contentBuilder .=   '</div>';
        // $contentBuilder .=   '<div class="quacks-modal-footer">';
        // $contentBuilder .=     '<a id="quacks-close-share-modal" href="#!" class="quacks-modal-close waves-effect waves-teal-light btn-flat">Schlie&#xDF;en</a>';
        // $contentBuilder .=   '</div>';
        // $contentBuilder .= '</div>';

        ob_start();
    ?>

        <div id="quackSingleContainer" class="main" itemprop="mainEntity" itemscope itemtype="https://schema.org/Article" data-quack-id="<?php echo htmlspecialchars($quackData['quackId']); ?>" data-chat-id="<?php echo htmlspecialchars($quackData['chatId']); ?>">
            <section id="quack-info-bar">
                <div id="quack-category-block">
                    <a class="chip active">Externer Artikel</a>
                </div>
                <div id="stats-wrapper">
                    <div class="votes" title="Bewertungen" itemscope itemtype="https://schema.org/CreativeWorkSeries">
                        <meta itemprop="headline name" content="<?php echo htmlspecialchars($quackData['title']); ?>" />
                        <?php if ($quackData['quackity'] > 0.0) : ?>
                            <div class="mini-counts" itemprop="aggregateRating" itemscope itemtype="https://schema.org/AggregateRating">
                                <span id="aggregatedRatingLabel" itemprop="ratingValue"><?php echo htmlspecialchars(number_format($quackData['quackity'], 1)); ?></span>&nbsp;(<span id="ratingCountLabel" itemprop="reviewCount"><?php echo htmlspecialchars($quackData['ratingCount']); ?></span>)<div class="flex-box"><i class="fa-solid fa-star"></i></div>
                            </div>
                        <?php else : ?>
                            <div class="mini-counts">
                                <span class="hide">0.0</span><span id="aggregatedRatingLabel">&dash;&#46;&dash;</span>&nbsp;(<span id="ratingCountLabel">0</span>)<div class="flex-box"><i class="material-icons">star_border</i></div>
                            </div>
                        <?php endif; ?>
                        <meta itemprop="bestRating" content="5">
                        <meta itemprop="worstRating" content="1">
                    </div>
                    <div class="stats-elem">&verbar;</div>
                    <div class="quack-date">
                        <div class="question-date" title="beantwortet am <?php $date; ?>">
                            <meta itemprop="dateCreated" content="<?php $isodate; ?>" />
                            <span class="published" datetime="<?php $isodate; ?>">
                                <?php echo htmlspecialchars($date); ?>
                            </span>
                            <i class="material-icons">public</i>
                        </div>
                    </div>
                    <div class="share-brand">
                        <div class="share" title="Seite teilen">
                            <i class="material-icons">share</i>
                        </div>
                    </div>
                </div>
            </section>
            <section id="quack-container">
                <div id="quacklist-wrapper" class="flush-left">
                    <div id="quack-overview-list" th:attr="quack-data=${quack.id}">
                        <div class="answer">
                            <?php foreach ($quackData['messages'] as $msg) :
                                $isAnswer = ($quackData['expertId'] == $msg['userId']);
                            ?>
                                <?php if ($isAnswer) : ?>
                                    <div id="messageBlock<?php echo htmlspecialchars($msg['id']); ?>" data-user-id="<?php echo htmlspecialchars($quackData['expertId']); ?>" class="dialogue-summary narrow right-duck" itemscope itemprop="suggestedAnswer" itemtype="https://schema.org/Answer">
                                        <div id="messageActionButtons<?php echo htmlspecialchars($msg['id']); ?>" data-message-id="<?php echo htmlspecialchars($msg['id']); ?>" class="js-action-buttons action-buttons hide"></div>
                                        <div class="summary-text">
                                            <div id="messageTextLabel<?php echo htmlspecialchars($msg['id']); ?>" itemprop="text" class="question-hyperlink">
                                                <?php echo htmlspecialchars($msg['text']); ?>
                                            </div>
                                        </div>
                                        <div itemscope itemprop="author" itemtype="https://schema.org/Person" class="author">
                                            <?php if ($expertNickname != null) : ?>
                                                <a class="prdk-link" href="https://produck.de/profile/<?php echo htmlspecialchars($quackData['expertId']); ?>">
                                                    <span itemprop="name" class="author-name"><?php echo htmlspecialchars($expertNickname); ?></span>
                                                </a>
                                            <?php else : ?>
                                                <a class="prdk-link" href="/profile/<?php echo htmlspecialchars($quackData['expertId']); ?>">
                                                    <span itemprop="name" class="author-name">Incognito</span>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($expertNickname != null) : ?>
                                                <span class="author-divider">&#10072;</span>
                                                <span class="author-status">Experte</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php else : ?>
                                    <div id="messageBlock<?php echo htmlspecialchars($msg['id']); ?>" class="dialogue-summary narrow left-duck">
                                        <div id="messageActionButtons<?php echo htmlspecialchars($msg['id']); ?>" data-message-id="<?php echo htmlspecialchars($msg['id']); ?>" class="js-action-buttons action-buttons hide"></div>
                                        <div class="summary-text">
                                            <div id="messageTextLabel<?php echo htmlspecialchars($msg['id']); ?>" class="question-hyperlink">
                                                <?php echo htmlspecialchars($msg['text']); ?>
                                            </div>
                                        </div>
                                        <div class="author">
                                            <span class="author-name">Ducky</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <meta itemprop="answerCount" content="<?php echo count($quackData['messages']); ?>" />
                        <?php if (!empty($quackData['tags'])) : ?>
                            <div class="quack-tag-block" data-tags="<?php echo htmlspecialchars(json_encode($quackData['tags'])); ?>">
                                <div class="tags">
                                    <?php foreach ($quackData['tags'] as $tag) : ?>
                                        <div class="chip">
                                            <span rel="noopener nofollow"><?php echo htmlspecialchars($tag); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="interaction-bar share-brand bottom">
                            <div class="report" title="Beitrag melden">
                                <a class="report-link" href="mailto:report-quack@monstec.de?subject=Quack%20melden">Melden</a>
                            </div>
                            <div class="share" title="Seite teilen">Teilen <i class="material-icons">share</i></div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <div id="share-modal" class="modal">
            <div id="modal-content">
                <h2 data-i18n="text.share_link"></h2>
                <div id="url-box">
                    <input class="share-url" value="" /> <i class="material-icons content-copy" data-i18n="[title]text.copy">content_copy</i>
                </div>
                <div id="share-btn-wrapper">
                    <div class="share-shariff"></div>
                </div>
            </div>
            <div class="modal-footer">
                <a id="close-share-modal" href="#!" class="modal-close waves-effect waves-teal-light btn-flat" data-i18n="text.close"></a>
            </div>
        </div>
<?php
        return ob_get_clean();
    }

    public function echoHeadContent()
    {
        echo $this->headContent;
    }

    public function changePageTitle()
    {
        return $this->title;
    }

    public function getPostTitle()
    {
        return $this->title;
    }

    public function getPostContent()
    {
        return $this->content;
    }
}
?>