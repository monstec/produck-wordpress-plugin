<?php

namespace MonsTec\Produck;

use ProduckPlugin;

// prevent direct access
defined('ABSPATH') or die('Quidquid agis, prudenter agas et respice finem!');

class OverviewPageContent implements DynamicPageContent
{
    protected $connector;
    protected $content;
    protected $headContent;
    protected $quacksDataObj;

    function __construct($produckConnectorObject)
    {
        $this->connector = $produckConnectorObject;
    }

    public function create(array $requestParams)
    {
        // change title, description and keywords
        add_filter('pre_get_document_title', function () {
            $externalPosts = ProduckPlugin::getTranslations(false, 'text', 'external_posts');
            if (isset($externalPosts)) {
                return $externalPosts;
            } else {
                return 'External Posts';
            }
        });

        add_action('wp_head', function () {
            $meta = '<meta name="description" data-i18n="[content]text.current_posts_by_external_authors" />' . chr(0x0A);
            $meta .= '<meta name="keywords" content="Blog Platform, Content Hub, Articles, Q&A, FAQ, Chat, Knowledge Base, Content Sharing, Content Management, Online Community, Blogging, Information Hub, Blog Network, User-Generated Content, Produck"/>' . chr(0x0A);
            echo $meta;
        });

        $this->quacksDataObj = $this->connector->getQuacksAndUsers();
        $usersData = !empty($this->quacksDataObj['users']) ? $this->quacksDataObj['users'] : null;
        
        $contentBuilder = '<div id="quackListMainContainer" class="main block">';
        $contentBuilder .= '<section id="quacks-overview-container" debog="2">';
        $contentBuilder .= '<h2 data-i18n="text.post_overview">Post Overview</h2>';
        $contentBuilder .= '<div id="quacklist-wrapper" class="flush-left">';
        
        if (!empty($this->quacksDataObj['quacks'])) {            

            if (ProduckPlugin::isPoweredByLinkAllowed() > 0) {
                $contentBuilder .= '<div id="quacks-share-brand">';
                $contentBuilder .=   '<div id="quacks-host-wrap-wrapper">';
                $contentBuilder .=     '<a class="quacks-host-ref prdk-link-darkco" href="https://www.produck.de" target="_blank">';
                $contentBuilder .=       '<span>Provided by ProDuck</span>';
                $contentBuilder .=       '<img src="' . ProduckPlugin::getImageURL('ducky_xs.png') . '" alt="ProDuck Brand Logo"/>';
                $contentBuilder .=     '</a>';
                $contentBuilder .=   '</div>';
                $contentBuilder .= '</div>';
            }

            foreach ($this->quacksDataObj['quacks'] as $quack) {
                if (
                    !isset($quack['title']) || strlen($quack['title']) < 1
                    || !isset($quack['id']) || strlen($quack['id']) < 1
                ) {
                    continue;
                }

                $quackId = $quack['id'];
                $userId = $quack['userId'];
                $user = $usersData[$userId];
                $quackity = isset($quack['quackity']) ? $quack['quackity'] : 0.0;
                $formattedQuackity = number_format($quackity, 1, '.', '');
                $title = $quack['title'];
                $summary = isset($quack['summary']) ? $quack['summary'] : '';
                $summaryText = strlen($summary) > 155 ? substr($summary, 0, 155) . '...' : $summary;
                $publicationTime = date('d.m.Y', strtotime($quack['publicationTime']));
                $tagsHomeLink = 'https://produck.de/quacks'; // Update with your actual path
                $quackDisplayTarget = ProduckPlugin::isOpenQuackInNewPage() ? "_blank" : "";
                $prettyUrlTitlePart = ProduckPlugin::transformTitleToUrlPart($title);
                $quackPath = '/quack/' . $quackId . '/' . $prettyUrlTitlePart;
                $quackLink = rtrim(home_url(), '/') . $quackPath;   

                $contentBuilder .= '<div class="dialogue-summary broad">';
                $contentBuilder .= '<div class="quack-category-block bottom">';
                $contentBuilder .= '<span class="chip active">' . $quack['referenceType'] . '</span>';
                $contentBuilder .= '</div>';
                $contentBuilder .= '<div id="stats-wrapper">';

                // Publication date
                $contentBuilder .= '<div class="quack-date">';
                $contentBuilder .= '<a class="published">';
                $contentBuilder .= '<span data-i18n="[title]text.published_on;[prepend]text.date_on">' . $publicationTime . '</span>';
                $contentBuilder .= '</a>';
                $contentBuilder .= '</div>';

                if ($quackity > 0.0) {
                    $contentBuilder .= '<div class="votes">';
                    $contentBuilder .= '<div class="mini-counts" title="' . $formattedQuackity . ' Rating">';
                    $contentBuilder .= '<span>' . $formattedQuackity . '</span>';
                    $contentBuilder .= '</div>';
                    $contentBuilder .= '<div class="flex-box"><i class="material-icons">star_border</i></div>';
                    $contentBuilder .= '</div>';
                } else {
                    $contentBuilder .= '<div class="votes"></div>';
                }

                //Teilen
                $contentBuilder .= '<div class="share-brand">';
                $contentBuilder .= '<div class="share">';
                $contentBuilder .= '<span class"prdk-link-darkco" data-i18n="text.share;[title]text.share">Share</span>';
                $contentBuilder .= '<i class="material-icons" onclick="return;">share</i>';
                $contentBuilder .= '</div>';
                $contentBuilder .= '</div>';
                $contentBuilder .= '</div>';
                $contentBuilder .= '<div class="summary-text">';
                $contentBuilder .= '<h3><a class="prdk-link-darkco " href="' . $quackLink . '" target="' . $quackDisplayTarget . '">' . $title . '</a></h3>';

                if ($summary !== '') {
                    $contentBuilder .= '<div class="info-text">';
                    $contentBuilder .= '<span>' . $summaryText . '</span>';
                    $contentBuilder .= '</div>';
                }

                // Author block
                $contentBuilder .= '<div class="card-author-block short-card">';
                if (isset($user['portraitImg']) && $user['portraitImg'] != null) {
                    $contentBuilder .= '<a class="portrait-image-wrapper" href="https://produck.de/profile/' . $userId . '">';
                    $contentBuilder .= '<img src="' . $user['portraitImg'] . '" loading="lazy" class="image" alt="Author\'s Portrait" />';
                    $contentBuilder .= '</a>';
                } else {
                    $contentBuilder .= '<a class="portrait-image-wrapper" href="https://produck.de/profile/' . $userId . '">';
                    $contentBuilder .= '<img src="https://produck.de/assets/img/icons/ducky_portrait_placeholder_transparent_xs_borderless.png" loading="lazy" class="image-placeholder" alt="Author\'s Portrait Placeholder" />';
                    $contentBuilder .= '</a>';
                }
                $contentBuilder .= '<a class="author-name prdk-link-darkco" href="https://produck.de/profile/' . $userId . '">';
                $contentBuilder .= '<span data-i18n="[prepend]text.written_by">' . (!empty($user['fullName']) ? $user['fullName'] : 'Unknown Author') . '</span>';
                $contentBuilder .= '</a>';
                $contentBuilder .= '</div>';

                // Tags
                $contentBuilder .= '<div class="tags">';
                if (isset($quack['tags']) && is_array($quack['tags'])) {
                    foreach ($quack['tags'] as $tag) {
                        $tagUrl = $tagsHomeLink . '/1/' . $tag;
                        $contentBuilder .= '<div class="chip">';
                        $contentBuilder .= '<a rel="noopener nofollow ugc" target="_blank" title="show topics tagged ' . $tag . '" href="' . $tagUrl . '">' . $tag . '</a>';
                        $contentBuilder .= '</div>';
                    }
                }
                $contentBuilder .= '</div>';
                $contentBuilder .= '</div>';
                $contentBuilder .= '</div>';
            }
        } else {

            $contentBuilder .= '<p class="w100 center-txt">';
            $contentBuilder .=   'Unfortunately, the search returned no results.';
            $contentBuilder .= '</p>';            
            $contentBuilder .= '<p class="w100 center-txt">';
            $contentBuilder .=   'Provided by <a href="' . ProduckPlugin::getCustomerProduckLink() . '" target="_blank">ProDuck</a> - Free Content Hosting Service';
            $contentBuilder .= '</p>';
        }

        $contentBuilder .=       '</div>';
        $contentBuilder .=     '</div>';
        $contentBuilder .=   '</section>';
        $contentBuilder .= '</div>';

        $contentBuilder .= '<div id="quacks-share-modal">';
        $contentBuilder .=     '<div id="quacks-modal-content">';
        $contentBuilder .=         '<h2 data-i18n="text.share_page"></h2>';
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
        $contentBuilder .=         '<a id="quacks-close-share-modal" href="#!" class="quacks-modal-close waves-effect waves-teal-light btn-flat" data-i18n="text.close">Close</a>';
        $contentBuilder .=     '</div>';
        $contentBuilder .= '</div>';

        $this->content = $contentBuilder;
    }

    public function echoHeadContent()
    {
        echo $this->headContent;
    }

    public function getPostTitle()
    {
        $externalPosts = ProduckPlugin::getTranslations(false, 'text', 'external_posts');
        if (isset($externalPosts)) {
            return $externalPosts;
        } else {
            return 'External Posts';
        }
    }

    public function getPostContent()
    {
        return $this->content;
    }

    public function getQuacksData()
    {
        return $this->quacksDataObj;
    }
}
