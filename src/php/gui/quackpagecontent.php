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
        $quackTitle = htmlspecialchars($quackData['title']);
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

        // add head-content
        // Note: Wordpress don't seems to support adding arguments to the add_action function for use
        // in the callable. So the solution is to store the head content in an instance variable for
        // later access.
        // The reason why no locally defined anonymous functions are used here is namespacing. There us
        // no need for complicately prefixing the methods if functions belonging to a (prefixed) class are used.

        // change title of the html-head
        $this->title = htmlspecialchars($quackTitle, ENT_QUOTES, 'UTF-8');
        add_filter('pre_get_document_title', array($this, 'changePageTitle'));

        if (!empty($quackData['summary'])) {
            $headBuilder = '<meta name="description" content="' . htmlspecialchars($quackData['summary'], ENT_QUOTES, 'UTF-8') . '"/>' . chr(0x0A);
            $headBuilder .= '<meta property="og:description" content="' . htmlspecialchars($quackData['summary'], ENT_QUOTES, 'UTF-8') . '"/>' . chr(0x0A);
            $headBuilder .= '<meta name="twitter:description" itemprop="description" content="' . htmlspecialchars($quackData['summary'], ENT_QUOTES, 'UTF-8') . '"/>' . chr(0x0A);
        } else {
            $headBuilder = '<meta name="description" content="' . htmlspecialchars($quackTitle, ENT_QUOTES, 'UTF-8') . '"/>' . chr(0x0A);
        }

        // add further head tags
        $headBuilder .= '<meta name="keywords" content="' . $tags . '"/>' . chr(0x0A);
        $headBuilder .= '<link rel="canonical" href="' . $quackLink . '">' . chr(0x0A);
        $headBuilder .= '<link rel="shortlink" href="' . $quackLink . '">' . chr(0x0A);
        $headBuilder .= '<meta property="og:title" content="' . $quackTitle . '"/>' . chr(0x0A);
        if (!empty($quackData['tags'])) :
            foreach ($quackData['tags'] as $tag) :
                $headBuilder .= '<meta property="og:article:tag" content="' . $tag . '">';
            endforeach;
        endif;
        $headBuilder .= '<meta property="og:url" content="' . $quackLink . '"/>' . chr(0x0A);
        $headBuilder .= '<meta property="og:type" content="website"/>' . chr(0x0A);
        $headBuilder .= '<meta name="twitter:title" itemprop="name" content="' . $quackTitle . '"/>' . chr(0x0A);
        $headBuilder .= '<meta name="twitter:card" content="summary"/>' . chr(0x0A);
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
            if (!isset($quackTitle) || !isset($quackData['messages']) || !isset($quackData['messages'][0])) {
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

            $this->content = $this->renderChat($quackData, $quackId, $quackTitle, $quackLink);
        } elseif (isset($quackData['ownerId']) || isset($quackData['authorId'])) {
            error_log("The reference type is ARTICLE.");
            $this->content = $this->renderArticle($quackData, $quackTitle, $quackLink);
        } else {
            error_log("The reference type is neither ARTICLE nor CHAT.");
            return null;
        }
    }

    private function renderArticle($quackData, $quackTitle, $quackLink)
    {
        $quackDisplayTarget = ProduckPlugin::isOpenQuackInNewPage() ? "_blank" : "";
        $authorLnk = 'https://www.produck.de' . '/profile/' . htmlspecialchars($quackData['authorId']);
        $expertNickname = !empty($quackData['fullName']) ? htmlspecialchars($quackData['fullName']) : htmlspecialchars($quackData['nickname']);
        $primaryImage = !empty($quackData['primaryImage']) ? $quackData['primaryImage'] : ProduckPlugin::getImageURL('monstec_image_placeholder.jpg');
        $views = null;
        if ($quackData['views'] > 0) {
            $views = htmlspecialchars(number_format($quackData['views'], 0, '', '.'));
        }

        ob_start();
?>
        <div id="quackSingleMainContainer" class="main" itemprop="mainEntity" itemscope itemtype="https://schema.org/Article" data-quack-id="<?php echo htmlspecialchars($quackData['id']); ?>" data-article-id="<?php echo htmlspecialchars($quackData['id']); ?>">
            <meta itemprop="name" content="<?php echo $quackTitle; ?>" />
            <meta itemprop="headline" content="<?php echo $quackTitle ?>" />
            <meta itemprop="url" content="<?php echo $quackLink ?>" />
            <meta itemprop="image" content="<?php echo $primaryImage ?>" />
            <section id="quack-info-bar">
                <div id="quack-category-block">
                    <div class="chip"><span data-i18n="text.external_article">ProDuck Guest Article</span></div>
                </div>
                <div id="stats-wrapper">
                    <?php if ($quackData['rating'] > 0.0) : ?>
                        <div class="votes" data-i18n="[title]text.rating">
                            <div class="mini-counts">
                                <span id="aggregatedRatingLabel" ><?php echo htmlspecialchars(number_format($quackData['rating'], 1)); ?></span>&nbsp;(<span id="ratingCountLabel" ><?php echo htmlspecialchars($quackData['ratingCount']); ?></span>)<div class="flex-box"><i class="material-icons">star_border</i></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="stats-elem">&verbar;</div>
                    <?php if ($views) : ?>
                        <div class="views" data-i18n="[title]text.views" itemprop="interactionStatistic" itemscope itemtype="https://schema.org/InteractionCounter" >
                            <div itemprop="interactionType" href="https://schema.org/WatchAction" class="mini-counts">
                                <meta itemprop="name" content="Views" />
                                <span itemprop="userInteractionCount" name="views" content="<?php echo $views ?>" views="<?php echo $views ?> views"><?php echo $views ?></span>
                            </div>
                            <div class="flex-box"><i class="material-icons">visibility</i></div>
                        </div>
                    <?php endif; ?>
                    <div class="quack-date">
                        <?php if (isset($quackData['lastModifiedTime'])) : ?>
                            <?php
                            $modifiedTime = new DateTime($quackData['lastModifiedTime']);
                            $formattedModifiedTime = $modifiedTime->format('d.m.Y');
                            ?>
                            <span class="published" datetime="<?php echo htmlspecialchars($quackData['lastModifiedTime']); ?>" data-i18n="[title]text.last_updated_on"><?php echo htmlspecialchars($formattedModifiedTime); ?></span><i data-i18n="[title]text.last_updated_on" class="material-icons">autorenew</i>
                        <?php else : ?>
                            <?php
                            $publicationTime = new DateTime($quackData['publicationTime']);
                            $formattedPublicationTime = $publicationTime->format('d.m.Y');
                            ?>
                            <span class="published" datetime="<?php echo htmlspecialchars($quackData['publicationTime']); ?>" data-i18n="[title]text.published_on" ><?php echo htmlspecialchars($formattedPublicationTime); ?></span><i data-i18n="[title]text.published_on" class="material-icons">public</i>
                        <?php endif; ?>
                    </div>
                    <div class="share-brand">
                        <div class="share" data-i18n="[title]text.share_page"><i class="material-icons">share</i></div>
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
                            <div class="report" data-i18n="[title]text.notify">
                                <a class="report-link prdk-link-darkco" href="mailto:report-quack@monstec.de?subject=Quack%20melden" data-i18n="text.notify">Report</a>
                            </div>
                            <div class="share" data-i18n="[title]text.share_page"><span data-i18n="text.share" class="prdk-link-darkco">Share</span><i class="material-icons prdk-link-darkco">share</i></div>
                        </div>
                        <?php if (!empty($quackData['nickname'])) : ?>
                            <div class="quack-author-block" itemprop="author" itemscope itemtype="https://schema.org/Person">
                                <div id="author-details-block" class="info-card" data-author-id="<?php echo htmlspecialchars($quackData['authorId']); ?>">
                                    <div class="card-wrapper <?php echo !empty($quackData['longDescr']) ? 'long-version' : 'short-version'; ?>">
                                        <div class="card-image-block">
                                            <?php
                                            $imagePath = null;
                                            if (!empty($quackData['portraitImg'])) {
                                                if (strpos($quackData['portraitImg'], '/assets') === 0) {
                                                    $imagePath = 'https://produck.de' . $quackData['portraitImg'];
                                                } else {
                                                    $imagePath = $quackData['portraitImg'];
                                                }
                                            }
                                        
                                            if (!empty($imagePath)) :
                                            ?>
                                                <a class="image-wrapper" target="_blank" href="<?php echo htmlspecialchars($authorLnk); ?>">
                                                    <img src="<?php echo htmlspecialchars($imagePath); ?>" loading="lazy" class="image" alt="Author Portrait" itemprop="image">
                                                </a>
                                            <?php else : ?>
                                                <a class="image-wrapper" target="_blank" href="<?php echo htmlspecialchars($authorLnk); ?>">
                                                    <img src="<?php echo ProduckPlugin::getImageURL('ducky_xs.png'); ?>" loading="lazy" class="image-placeholder" alt="Author Portrait" itemprop="image">
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-text-block">
                                            <a class="author-name" target="_blank" href="<?php echo $authorLnk ?>">
                                                <span class="prdk-link-darkco" itemprop="name"><?php echo $expertNickname; ?></span>
                                            </a>
                                            <?php if (!empty($quackData['specDescr'])) : ?>
                                                <a class="author-expertise" target="_blank" href="<?php echo $authorLnk ?>">
                                                    <span data-i18n="quackpage.speciality"></span>:&nbsp;<span itemprop="jobTitle"><?php echo htmlspecialchars($quackData['specDescr']); ?></span>
                                                </a>
                                            <?php else : ?>
                                                <span class="author-expertise">Autor auf ProDuck.de</span>
                                            <?php endif; ?>
                                            <?php if (!empty($quackData['longDescr'])) : ?>
                                                <a class="author-description" target="_blank" href="<?php echo $authorLnk ?>">
                                                    <span><?php echo htmlspecialchars(mb_strimwidth($quackData['longDescr'], 0, 500, '...')); ?></span>
                                                </a>
                                            <?php endif; ?>
                                            <a class="profile-ref prdk-link" target="_blank" href="<?php echo $authorLnk ?>" data-i18n="quackpage.profile_ref" itemprop="url">Zum Profil</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="quacks-more-quacks-ref">
                           <a class="btn" href="<?php echo ProduckPlugin::getQuackOverviewUrl() ?>" target="<?php echo $quackDisplayTarget ?>" data-i18n="text.go_to_post_overview">Post Overview</a>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <div id="quacks-share-modal">
            <div id="quacks-modal-content">
                <h2 data-i18n="text.share_page"></h2>
                <div id="quacks-url-box">
                    <input class="quacks-share-url" value="" />
                    <span class="quacks-content-copy">
                     <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" d="M0 0h24v24H0z"/><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm-1 4l6 6v10c0 1.1-.9 2-2 2H7.99C6.89 23 6 22.1 6 21l.01-14c0-1.1.89-2 1.99-2h7zm-1 7h5.5L14 6.5V12z"/></svg>
                    </span>
                </div>
                <div id="quacks-share-btn-wrapper">
                    <div class="quacks-share-shariff"></div>
                </div>
            </div>
            <div class="quacks-modal-footer">
                <a id="quacks-close-share-modal" href="#!" class="quacks-modal-close waves-effect waves-teal-light btn-flat" data-i18n="text.close">Close</a>
            </div>
        </div> 

    <?php
        return ob_get_clean();
    }

    private function renderChat($quackData, $quackId, $quackTitle, $quackLink)
    {
        $quackDisplayTarget = ProduckPlugin::isOpenQuackInNewPage() ? "_blank" : "";
        $expertNickname = !empty($quackData['fullName']) ? htmlspecialchars($quackData['fullName']) : htmlspecialchars($quackData['nickname']);
        $primaryImage = !empty($quackData['primaryImage']) ? $quackData['primaryImage'] : ProduckPlugin::getImageURL('monstec_image_placeholder.jpg');

        $isodate = null;
        if (isset($quackData['timestamp'])) {
            $isodate = new DateTime($quackData['timestamp']);
        } else {
            $isodate = new DateTime();
        }
        $date = $isodate->format('d.m.Y');

        $expertLnk = 'https://www.produck.de' . '/profile/' . htmlspecialchars($quackData['expertId']);
        $views = null;
        if ($quackData['views'] > 0) {
            $views = htmlspecialchars(number_format($quackData['views'], 0, '', '.'));
        }

        // add head-content
        // Note: Wordpress don't seems to support adding arguments to the add_action function for use
        // in the callable. So the solution is to store the head content in an instance variable for
        // later access.
        // The reason why no locally defined anonymous functions are used here is namespacing. There us
        // no need for complicately prefixing the methods if functions belonging to a (prefixed) class are used.

        // change title in the html-head
        $this->title = htmlspecialchars($quackTitle, ENT_QUOTES, 'UTF-8');
        add_filter('pre_get_document_title', array($this, 'changePageTitle'));

        ob_start();

    ?>
        <div id="quackSingleMainContainer" class="main" itemprop="mainEntity" itemscope itemtype="https://schema.org/Article" data-quack-id="<?php echo htmlspecialchars($quackData['quackId']); ?>" data-chat-id="<?php echo htmlspecialchars($quackData['chatId']); ?>">
            <meta itemprop="name" content="<?php echo $quackTitle ?>" />
            <meta itemprop="headline" content="<?php echo $quackTitle ?>" />
            <meta itemprop="url" content="<?php echo $quackLink ?>" />
            <meta itemprop="image" content="<?php echo $primaryImage ?>" />
            <section id="quack-info-bar">
                <div id="quack-category-block">
                    <span class="chip active" data-i18n="text.external_chat">ProDuck Chat Transcript</span>
                </div>
                <div id="stats-wrapper">
                    <?php if ($quackData['quackity'] > 0.0) : ?>
                        <div class="votes" data-i18n="[title]text.rating">
                            <div class="mini-counts" ><span id="aggregatedRatingLabel" ><?php echo htmlspecialchars(number_format($quackData['quackity'], 1)); ?></span>&nbsp;(<span id="ratingCountLabel" ><?php echo htmlspecialchars($quackData['ratingCount']); ?></span>)<div class="flex-box"><i class="material-icons">star_border</i></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="stats-elem">&verbar;</div>
                    <?php if ($views) : ?>
                        <div class="views" data-i18n="[title]text.views" itemprop="interactionStatistic" itemscope itemtype="https://schema.org/InteractionCounter">
                            <div itemprop="interactionType" href="https://schema.org/WatchAction" class="mini-counts">
                                <meta itemprop="name" content="Views" />
                                <span itemprop="userInteractionCount" name="views" content="<?php echo $views ?>" views="<?php echo $views ?> views"><?php echo $views ?></span>
                            </div>
                            <div class="flex-box"><i class="material-icons">visibility</i></div>
                        </div>
                    <?php endif; ?>
                    <div class="quack-date" data-i18n="[title]text.answered_on">
                        <meta itemprop="dateCreated" content="<?php $isodate; ?>" />
                        <span class="published" datetime="<?php $isodate; ?>"><?php echo htmlspecialchars($date); ?></span>
                        <i class="material-icons">public</i>
                    </div>
                    <div class="share-brand">
                        <div class="share" data-i18n="[title]text.share_page"><i class="material-icons">share</i></div>
                    </div>
                </div>
            </section>
            <section id="quack-container">
                <div id="quack-content-body" class="flush-left">
                    <div id="quack-content-wrapper" quack-data="<?php $quackId; ?>">
                        <div class="answer">
                            <?php foreach ($quackData['messages'] as $msg) :
                                $isAnswer = ($quackData['expertId'] == $msg['userId']);
                            ?>
                                <?php if ($isAnswer) : ?>
                                    <div id="messageBlock<?php echo htmlspecialchars($msg['id']); ?>" data-user-id="<?php echo htmlspecialchars($quackData['expertId']); ?>" class="dialogue-summary narrow right-duck" itemscope itemprop="suggestedAnswer" itemtype="https://schema.org/Answer">
                                        <div id="messageActionButtons<?php echo htmlspecialchars($msg['id']); ?>" data-message-id="<?php echo htmlspecialchars($msg['id']); ?>" class="js-action-buttons action-buttons hide"></div>
                                        <div class="summary-text">
                                            <div id="messageTextLabel<?php echo htmlspecialchars($msg['id']); ?>" itemprop="text" class="quacks-question-hyperlink">
                                                <?php echo $msg['text']; ?>
                                            </div>
                                        </div>
                                        <div class="author">
                                            <?php if ($expertNickname != null) : ?>
                                                <a class="prdk-link" href="<?php echo $expertLnk ?>"><span class="author-name"><?php echo htmlspecialchars($expertNickname); ?></span></a>
                                            <?php else : ?>
                                                <a class="prdk-link" href="<?php echo $expertLnk ?>"><span class="author-name">Incognito</span></a>
                                            <?php endif; ?>
                                            <?php if ($expertNickname != null) : ?>
                                                <span class="author-divider">&#10072;</span>
                                                <span class="author-status" data-i18n="text.expert" >Experte</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php else : ?>
                                    <div id="messageBlock<?php echo htmlspecialchars($msg['id']); ?>" class="dialogue-summary narrow left-duck">
                                        <div id="messageActionButtons<?php echo htmlspecialchars($msg['id']); ?>" data-message-id="<?php echo htmlspecialchars($msg['id']); ?>" class="js-action-buttons action-buttons hide"></div>
                                        <div class="summary-text">
                                            <div id="messageTextLabel<?php echo htmlspecialchars($msg['id']); ?>" class="quacks-question-hyperlink">
                                                <?php echo $msg['text']; ?>
                                            </div>
                                        </div>
                                        <div class="author">
                                            <span class="author-name">Ducky</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php echo ($msg === end($quackData['messages'])) ? '<div class="vertical-spacer small"></div>' : ''; ?>
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
                            <div class="report" data-i18n="[title]text.notify"><a class="report-link prdk-link-darkco" href="mailto:report-quack@monstec.de?subject=Quack%20melden" data-i18n="text.notify">Report</a></div>
                            <div class="share" data-i18n="[title]text.share_page"><span data-i18n="text.share" class="prdk-link-darkco">Share</span><i class="material-icons prdk-link-darkco">share</i></div>
                        </div>
                        <?php if (!empty($quackData['nickname'])) : ?>
                            <div class="quack-author-block" itemprop="author" itemscope itemtype="https://schema.org/Person">
                                <div id="author-details-block" class="info-card" data-author-id="<?php echo htmlspecialchars($quackData['expertId']); ?>">
                                    <div class="card-wrapper <?php echo !empty($quackData['longDescr']) ? 'long-version' : 'short-version'; ?>">
                                        <div class="card-image-block">
                                            <?php
                                            $imagePath = null;
                                            if (!empty($quackData['portraitImg'])) {
                                                if (strpos($quackData['portraitImg'], '/assets') === 0) {
                                                    $imagePath = 'https://produck.de' . $quackData['portraitImg'];
                                                } else {
                                                    $imagePath = $quackData['portraitImg'];
                                                }
                                            }
                                        
                                            // Check if an image path exists or fall back to a placeholder
                                            if (!empty($imagePath)) :
                                            ?>
                                                <a class="image-wrapper" target="_blank" href="<?php echo htmlspecialchars($expertLnk); ?>">
                                                    <img src="<?php echo htmlspecialchars($imagePath); ?>" loading="lazy" class="image" alt="Autoren Portrait" itemprop="image">
                                                </a>
                                            <?php else : ?>
                                                <a class="image-wrapper" target="_blank" href="<?php echo htmlspecialchars($expertLnk); ?>">
                                                    <img src="<?php echo ProduckPlugin::getImageURL('ducky_xs.png'); ?>" loading="lazy" class="image-placeholder" alt="Autoren Portrait" itemprop="image">
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-text-block">
                                            <a class="author-name" target="_blank" href="<?php echo $expertLnk ?>">
                                                <span class="prdk-link-darkco" itemprop="name" ><?php echo $expertNickname; ?></span>
                                            </a>
                                            <?php if (!empty($quackData['specDescr'])) : ?>
                                                <a class="author-expertise" target="_blank" href="<?php echo $expertLnk ?>">
                                                    <span data-i18n="quackpage.speciality"></span>:&nbsp;<span itemprop="jobTitle"><?php echo htmlspecialchars($quackData['specDescr']); ?></span>
                                                </a>
                                            <?php else : ?>
                                                <span class="author-expertise">Autor auf ProDuck.de</span>
                                            <?php endif; ?>
                                            <?php if (!empty($quackData['longDescr'])) : ?>
                                                <a class="author-description" target="_blank" href="<?php echo $expertLnk ?>">
                                                    <span><?php echo htmlspecialchars(mb_strimwidth($quackData['longDescr'], 0, 500, '...')); ?></span>
                                                </a>
                                            <?php endif; ?>
                                            <a class="profile-ref prdk-link" target="_blank" href="<?php echo $expertLnk ?>" data-i18n="quackpage.profile_ref" itemprop="url">Zum Profil</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="quacks-more-quacks-ref">
                           <a class="btn" href="<?php echo ProduckPlugin::getQuackOverviewUrl() ?>" target="<?php echo $quackDisplayTarget ?>" data-i18n="text.go_to_post_overview">Post Overview</a>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <div id="quacks-share-modal">
            <div id="quacks-modal-content">
                <h2 data-i18n="text.share_page"></h2>
                <div id="quacks-url-box">
                    <input class="quacks-share-url" value="" />
                    <span class="quacks-content-copy">
                     <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" d="M0 0h24v24H0z"/><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm-1 4l6 6v10c0 1.1-.9 2-2 2H7.99C6.89 23 6 22.1 6 21l.01-14c0-1.1.89-2 1.99-2h7zm-1 7h5.5L14 6.5V12z"/></svg>
                    </span>
                </div>
                <div id="quacks-share-btn-wrapper">
                    <div class="quacks-share-shariff"></div>
                </div>
            </div>
            <div class="quacks-modal-footer">
                <a id="quacks-close-share-modal" href="#!" class="quacks-modal-close waves-effect waves-teal-light btn-flat" data-i18n="text.close">Close</a>
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