<?php

/**
 * Plugin Name: Produck
 * Plugin URI: https://www.produck.de
 * Description: This plugin enables you to show posts such as article and chat conversations created on ProDuck.de and to integrate the ProDuck Live Chat on your site.
 * Version: 1.1.0
 * Author: MonsTec GmbH
 * Author URI: https://www.monstec.de
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */
/*
Copyright (C) 2024 MonsTec GmbH

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

// IMPORTANT! permalinks currently have to be set to something other than the default (first) option

// WordPress code security check
// prevent direct access
defined('ABSPATH') or die('Quidquid agis, prudenter agas et respice finem!');

// @if ENV!='production'
// development / debug mode switches
// has to go in 'wp-config.php' to work properly / or activate directly in wp-config
// define('WP_DEBUG', true);
// define('WP_DEBUG_LOG', true);
// define('WP_DEBUG_DISPLAY', false);
// @endif

require_once 'vpage/page.php';
require_once 'vpage/content.php';
require_once 'vpage/controller.php';
require_once 'vpage/templateloader.php';
require_once 'gui/quackpagecontent.php';
require_once 'gui/quacksoverviewpagecontent.php';
require_once 'gui/combinedpostspagecontent.php';
require_once 'gui/widget.php';
require_once 'gui/chat.php';
require_once 'api/produckconnector.php';
require_once 'api/produckapi.php';
require_once 'api/produckcache.php';
require_once 'pluginadministration.php';

use MonsTec\Produck\ProduckApi;
use MonsTec\Produck\ProduckCache;
use MonsTec\Produck\ProduckConnector;
use MonsTec\Produck\Page;
use MonsTec\Produck\Controller;
use MonsTec\Produck\TemplateLoader;
use MonsTec\Produck\QuackPageContent;
use MonsTec\Produck\OverviewPageContent;
use MonsTec\Produck\MergedOverviewPageContent;
use MonsTec\Produck\ProduckQuacksWidget;
use MonsTec\Produck\Chat;

/*** activation hook ***/
// only run when plugin is activated via administration
register_activation_hook(__FILE__, 'produck_activatePlugin');

function produck_activatePlugin()
{
    // This will add all the needed options but only if they don't exist yet.
    // That means deacivating the plugin and then activating it again will not reset the settings.
    add_option('produck_config', array(
        'customerId' => null,
        'quackToken' => null,
        'numberOfQuacksShown' => '6',
        'maxQuackUrlTitleLength' => '100',
        'openQuackInNewPage' => 1,
        'useThemeTemplate' => 0,
        'combineProduckPosts' => 'NoIntegration',
        'ignoreDuplicatePosts' => 1,
        'maxImplementationLoops' => '1',
        'ignorePostsWoPrimaryImage' => 1,
        'maxPostsToRecall' => '10',
        'poweredByLinkAllowed' => -1,
        'chatEnabled' => 0,
    ));

    $options = get_option('produck_config');
}
/*** end of activation hook ***/

if (is_admin()) {
    $produckAdministration = new ProduckPluginAdministration();
}

/**
 * Main class of plugin functionality.
 */
class ProduckPlugin
{
    const TEMPLATE_SUB_DIR = 'templates';
    // @if ENV='production'
    const PRODUCK_URL = 'https://produck.de/';
    const PRODUCK_CHAT_URL = 'https://produck.de/chat.html';
    // @endif
    // @if ENV!='production'
    const PRODUCK_URL = 'https://localhost/';
    const PRODUCK_CHAT_URL = 'https://localhost/chat.html';
    // @endif

    // replacements for pretty urls
    public static $urlReplaceFrom = array(' ', 'ä', 'ö', 'ü', 'ß');
    public static $urlReplaceTo = array('-', 'ae', 'oe', 'ue', 'ss');
    public static $urlReplaceRegexp = '/[^a-z0-9 -]/';

    private static $options;
    private static $pluginPath;
    private static $pluginUrl;
    private $connector;

    public function __construct()
    {
        $this->controller = new Controller(new TemplateLoader(ProduckPlugin::getPluginPath()));

        $produckApi = new ProduckApi(ProduckPlugin::getQuackToken(), ProduckPlugin::getPageNumber());
        $produckCache = new ProduckCache();
        $this->connector = new ProduckConnector($produckApi, $produckCache);
    }

    // prepares a proper initiation of scripts just on pages, that are based on produck templates to avoid being load on independent pages, too.
    private static function is_specific_template()
    {
        // Check if we're in the main query and not in the admin
        if (!is_admin() && is_main_query()) {
            // Get the global post object
            global $post, $isProduckVirtualPage;

            // Check if $post is an object and if it has the correct page template
            if (
                is_object($post) && isset($post->ID) &&
                (
                    get_post_meta($post->ID, '_wp_page_template', true) === 'quackdetail.php' ||
                    get_post_meta($post->ID, '_wp_page_template', true) === 'quacksoverview.php' ||
                    get_post_meta($post->ID, '_wp_page_template', true) === 'combinedpage.php'
                )
            ) {
                return true;
            }

            // Check for virtual pages
            if ($isProduckVirtualPage) {
                return true;
            }
        }
        return false;
    }

    public function init()
    {

        // add hooks for custom dynamic pages
        // a possible alternative could be using "custom posttypes"
        add_action('init', array($this->controller, 'init'));        

        // Note! The following filter does not work on admin pages!
        add_filter('do_parse_request', array($this->controller, 'dispatch'), PHP_INT_MAX, 2);

        add_action('loop_end', function (\WP_Query $query) {
            if (isset($query->virtual_page) && !empty($query->virtual_page)) {
                $query->virtual_page = NULL;
            }
        });

        add_filter('the_permalink', function ($plink) {
            global $post, $wp_query;
            if (
                $wp_query->is_page
                && isset($wp_query->virtual_page)
                && $wp_query->virtual_page instanceof Page
                && isset($post->is_virtual)
                && $post->is_virtual
            ) {
                $plink = home_url($wp_query->virtual_page->getUrl());
            }

            return $plink;
        });

        add_filter('post_link', function ($post_link, $post) {
            // Check if this is a produck virtual post type
            if (
                is_front_page() &&
                isset($post->post_type)
                && isset($post->is_produck_virtual) && $post->is_produck_virtual
                && isset($post->guid)
            ) {
                // Override the permalink with the external link (stored in 'guid')
                return $post->guid;
            }

            return $post_link;
        }, 10, 2);

        global $overviewPageContentInstance;
        $overviewPageContentInstance = null;

        // add script and style dependencies, but just on pages, not single post sites (to avoid redundancies).
        // for an overview of already defined script handles (like 'jquery') see:
        // https://developer.wordpress.org/reference/functions/wp_enqueue_script/
        // Note! Calling third party CDNs for reasons other than font inclusions is forbidden (WP-Policy);
        // all non-service related JavaScript and CSS must be included locally
        add_action('wp_enqueue_scripts', function () {

            wp_enqueue_script('jquery');
            wp_enqueue_style('produck-quack-style', ProduckPlugin::getPluginUrl() . '/css/quacks.min.css');

            if (ProduckPlugin::is_specific_template()) {
                //produck styles, with produck styles first
                wp_enqueue_style('produck-link-style', ProduckPlugin::getPluginUrl() . '/css/linkify.min.css');
                wp_enqueue_style('material-style', ProduckPlugin::getPluginUrl() . '/css/material-style.min.css');
                wp_enqueue_style('shariff-style', ProduckPlugin::getPluginUrl() . '/css/shariff.min.css');

                //produck scripts
                wp_enqueue_script('materialize-lib', ProduckPlugin::getPluginUrl() . '/js/materialize.min.js', array('jquery'), null, true);
                wp_enqueue_script('cookie-lib', ProduckPlugin::getPluginUrl() . '/js/js.cookie.js');
                wp_enqueue_script('shariff-lib', ProduckPlugin::getPluginUrl() . '/js/shariff.min.js');
                wp_enqueue_script('prdkDeals', 'https://www.produck.de/assets/jsn/deals.json');
                wp_enqueue_script('produck-lib', ProduckPlugin::getPluginUrl() . '/js/produck.min.js', array('jquery', 'materialize-lib'), null, true);


                //for pagination, we need quacksData here
                global $overviewPageContentInstance;

                if ($overviewPageContentInstance instanceof OverviewPageContent) {

                    $quacksData = $overviewPageContentInstance->getQuacksData();

                    if (isset($quacksData['totalPages']) && isset($quacksData['pageNumber'])) {

                        // Localize the script with the data
                        wp_localize_script('produck-lib', 'quacksDataObj', array(
                            'totalPages' => $quacksData['totalPages'],
                            'pageNumber' => $quacksData['pageNumber']
                        ));
                    } else {
                        // Handle cases where quacksData does not have the expected structure
                        wp_localize_script('produck-lib', 'quacksDataObj', array(
                            'totalPages' => 0,
                            'pageNumber' => 0
                        ));
                    }
                }
            }

            wp_enqueue_style('produck-chat-style', ProduckPlugin::getPluginUrl() . '/css/produckchat.min.css');
        });

        // add dynamic pages for the produck plugin
        add_action('produck_virtual_pages', function ($controller) {
            global $overviewPageContentInstance;

            $overviewPageContentInstance = new OverviewPageContent($this->connector);
            $controller->addPage(new Page('quacks'))
                ->setContent($overviewPageContentInstance)
                ->setTemplate('quacksoverview.php');

            $controller->addPage(new Page('category/produck-guestpost/'))
                ->setContent($overviewPageContentInstance)
                ->setTemplate('quacksoverview.php');

            $controller->addPage(new Page('quack'))
                ->setContent(new QuackPageContent($this->connector))
                ->setTemplate('quackdetail.php');

            $controller->addPage(new Page('combined-post-overview'))
                ->setContent(new MergedOverviewPageContent($this->connector))
                ->setTemplate('combinedpage.php');
        });


        // Register the widget
        add_action('widgets_init', array($this, 'registerQuacksWidget'));

        if (ProduckPlugin::isChatEnabled()) {
            add_action('wp_footer', function () {
                $chat = new Chat();
                echo $chat->getHtml();
            });
        }

        add_filter('the_content', function ($content) {
            if (ProduckPlugin::is_specific_template()) {
                // wpautop wraps content in <p>-tags but also adds unwanted <p>-tags and <br> in the code, so it is removed here
                remove_filter('the_content', 'wpautop');
            }
            return $content;
        }, 9); //higher priority to run before other filters

        //Optional: Insert this short code in a php file of your choice in the form "[combined_posts]", to get an overview of the merged articles at this place
        add_shortcode('combined_posts', function () {

            if (is_front_page()) {
                $mergedContent = new MergedOverviewPageContent($this->connector);
                $mergedContentOutput = $mergedContent->getPostContent();

                return $mergedContentOutput;
            }

            return '<p>Keine Artikel verfügbar oder Funktion nicht gefunden.</p>';
        });

        add_action('wp_footer', function () {

            if (ProduckPlugin::is_specific_template()) {
?>
                <script type="text/javascript">
                    let quackPage = null;
                    document.addEventListener('DOMContentLoaded', function() {
                        if (typeof produckLib !== 'undefined' && typeof produckLib.InitQuackPage !== 'undefined') {
                            quackPage = new produckLib.InitQuackPage();
                            quackPage.pageInitialize();

                            const totalPages = quacksDataObj.totalPages;
                            const pageNumber = parseInt(quacksDataObj.pageNumber, 10) + 1; //starts with 0

                            if (totalPages > 1) {
                                quackPage.initOverviewPagination(totalPages, pageNumber);
                            }
                        } else {
                            console.log("Quack Page could not be initialized");
                        }
                    });

                    window.addEventListener('load', function() {
                        if (quackPage) {
                            quackPage.initMaterialize();
                        }
                    });
                </script>
<?php
            }
        });

        function setIntegrationModus($connector)
        {
            $modeOfIntegration = ProduckPlugin::getModusOfPostIntegration();

            // Create a single instance of the MergedOverviewPageContent class
            $mergedOverviewPageContent = new MergedOverviewPageContent($connector);

            if ($modeOfIntegration == 'integrateInMainQuery' || $modeOfIntegration == 'integrateInAllQueries') {

                // Pre get posts handler
                add_action('pre_get_posts', [$mergedOverviewPageContent, 'pre_get_posts_handler'], 1);

                // The posts handler
                add_filter('the_posts', function ($posts, $query) use ($mergedOverviewPageContent, $modeOfIntegration) {
                    return $mergedOverviewPageContent->the_posts_handler($posts, $query, $modeOfIntegration, ProduckPlugin::getDuplicatePostsModus(), ProduckPlugin::getMaxLoopsPostsToImplement());
                }, 10, 2);

                // Handling post metadata
                add_filter('get_post_metadata', function ($value, $post_id, $meta_key, $single) use ($mergedOverviewPageContent) {
                    return $mergedOverviewPageContent->getPostMetadata($value, $post_id, $meta_key, $single); // Return the result
                }, 10, 4);

                // Handling post thumbnail
                add_filter('post_thumbnail_html', function ($html, $post_id, $post_thumbnail_id, $size, $attr) use ($mergedOverviewPageContent) {
                    return $mergedOverviewPageContent->setPostThumbnail($html, $post_id, $post_thumbnail_id, $size, $attr); // Return the result
                }, 10, 5);

                // Handling attachment image
                add_filter('wp_get_attachment_image_src', function ($image, $attachment_id, $size, $icon) use ($mergedOverviewPageContent) {
                    return $mergedOverviewPageContent->getAttachementImage($image, $attachment_id, $size, $icon); // Return the result
                }, 10, 4);

                // Handling author name
                add_filter('the_author', function ($author) use ($mergedOverviewPageContent) {
                    return $mergedOverviewPageContent->setAuthorName($author); // Return the result
                });

                // Handling author display name
                add_filter('get_the_author_display_name', function ($display_name, $user_id) use ($mergedOverviewPageContent) {
                    return $mergedOverviewPageContent->setAuthorDisplayName($display_name, $user_id); // Return the result
                }, 10, 2);

                // Handling author link
                add_filter('author_link', function ($link, $author_id) use ($mergedOverviewPageContent) {
                    return $mergedOverviewPageContent->setAuthorLink($link, $author_id); // Return the result
                }, 10, 2);

                // Handling avatar image
                add_filter('get_avatar', function ($avatar, $id_or_email, $size, $default, $alt, $args) use ($mergedOverviewPageContent) {
                    return $mergedOverviewPageContent->setAvatarImg($avatar, $id_or_email, $size, $default, $alt, $args); // Return the result
                }, 10, 6);

                add_filter('category_link', function ($url, $category_id) {
                    $produck_category = get_term_by('slug', 'produck-guestpost', 'category');
                
                    // Check if this is the ProDuck category and redirect
                    if ($produck_category && $category_id === $produck_category->term_id) {
                        return site_url('/category/produck-guestpost/');
                    }
                
                    return $url;
                }, 10, 2);
                
            } else if ($modeOfIntegration == 'integratePerRedirect') {
                // Redirect action
                add_action('template_redirect', [$mergedOverviewPageContent, 'createRedirectToMergedOverview']);
            }
        }

        setIntegrationModus($this->connector);
    }

    public function registerQuacksWidget()
    {
        register_widget('ProduckQuacksWidget');
    }

    public static function initOptions()
    {
        ProduckPlugin::$options = get_option('produck_config');
    }

    public static function getPluginPath()
    {
        if (!ProduckPlugin::$pluginPath) {
            ProduckPlugin::$pluginPath = plugin_dir_path(__FILE__);
        }

        return ProduckPlugin::$pluginPath;
    }

    public static function getPluginUrl()
    {
        if (!ProduckPlugin::$pluginUrl) {
            ProduckPlugin::$pluginUrl = plugins_url('produck');
        }

        return ProduckPlugin::$pluginUrl;
    }

    public static function getCustomerId()
    {
        if (isset(ProduckPlugin::$options['customerId'])) {
            return ProduckPlugin::$options['customerId'];
        } else {
            return null;
        }
    }

    public static function getQuackToken()
    {
        if (isset(ProduckPlugin::$options['quackToken'])) {
            return ProduckPlugin::$options['quackToken'];
        } else {
            return null;
        }
    }

    public static function getPageNumber()
    {
        // Get the current URL path
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $pathParts = explode('/', trim($path, '/'));

        // Find the index of 'quacks' and get the next part as the page number
        $pageNumber = 1;
        foreach ($pathParts as $key => $part) {
            if ($part === 'quacks' && isset($pathParts[$key + 1]) && is_numeric($pathParts[$key + 1])) {
                $pageNumber = (int)$pathParts[$key + 1];
                break;
            }
        }

        return $pageNumber;
    }

    public static function getNumberOfQuacksShown()
    {
        $num = 6;
        if (isset(ProduckPlugin::$options['numberOfQuacksShown'])) {
            $num = ProduckPlugin::$options['numberOfQuacksShown'];
        }
        return $num;
    }

    public static function getCustomerProduckLink()
    {
        if (isset(ProduckPlugin::$options['customerId'])) {
            return self::PRODUCK_CHAT_URL . '?cid=' . ProduckPlugin::$options['customerId'];
        } else {
            return self::PRODUCK_URL;
        }
    }

    public static function loadTranslations($lang)
    {
        $filePath = plugin_dir_path(__FILE__) . "/locales/{$lang}.json";

        if (file_exists($filePath)) {
            $jsonContent = file_get_contents($filePath);
            return json_decode($jsonContent, true);
        } else {
            return [];
        }
    }

    //helps to translate words in php by using json locales files
    public static function getTranslations($returnAll, $subsection, $value)
    {
        // Determine language (default to 'en' if not set)
        $lang = isset($_COOKIE['i18next']) ? $_COOKIE['i18next'] : 'de';

        $translations = ProduckPlugin::loadTranslations($lang);

        if ($translations && $returnAll) {
            return $translations;
        } else if ($translations && isset($subsection) && isset($value)) {
            if (isset($translations['translation'][$subsection][$value])) {
                return $translations['translation'][$subsection][$value];
            } else {
                error_log("Translation not found for {$subsection}.{$value}.");
                return null;
            }
        } else {
            error_log("Error decoding the translation file. Translation file not found.");
            return null;
        }
    }

    public static function getQuackOverviewUrl()
    {
        return home_url('quacks');
    }

    public static function getImageURL($imageName)
    {
        return ProduckPlugin::getPluginUrl() . '/img/' . $imageName;
    }

    public static function isOpenQuackInNewPage()
    {
        return isset(ProduckPlugin::$options['openQuackInNewPage'])
            && boolval(ProduckPlugin::$options['openQuackInNewPage']);
    }

    public static function isChatEnabled()
    {
        return isset(ProduckPlugin::$options['chatEnabled'])
            && boolval(ProduckPlugin::$options['chatEnabled']);
    }

    public static function useThemeTemplate()
    {
        return isset(ProduckPlugin::$options['useThemeTemplate'])
            && boolval(ProduckPlugin::$options['useThemeTemplate']);
    }

    public static function getModusOfPostIntegration()
    {
        return isset(ProduckPlugin::$options['combineProduckPosts'])
            ? ProduckPlugin::$options['combineProduckPosts']
            : 'NoIntegration';
    }

    public static function getDuplicatePostsModus()
    {
        return isset(ProduckPlugin::$options['ignoreDuplicatePosts'])
            && boolval(ProduckPlugin::$options['ignoreDuplicatePosts']);
    }

    public static function getPostsWoPrimaryImageModus()
    {
        return isset(ProduckPlugin::$options['ignorePostsWoPrimaryImage'])
            && boolval(ProduckPlugin::$options['ignorePostsWoPrimaryImage']);
    }

    public static function getMaxLoopsPostsToImplement()
    {
        $maxLoops = 1;
        if (isset(ProduckPlugin::$options['maxImplementationLoops'])) {
            $maxLoops = ProduckPlugin::$options['maxImplementationLoops'];
        }
        return $maxLoops;
    }

    public static function getMaxPostsToRecall()
    {
        $maxPosts = 10;
        if (isset(ProduckPlugin::$options['maxPostsToRecall'])) {
            $maxPosts = ProduckPlugin::$options['maxPostsToRecall'];
        }
        return $maxPosts;
    }

    /**
     * Will return an integer < 0 if the user did not decide yet, 0 if powered-by-links must not be shown
     * and an integer > 0 if powered-by-link are allowed.
     */
    public static function isPoweredByLinkAllowed()
    {
        if (isset(ProduckPlugin::$options['poweredByLinkAllowed'])) {
            return intval(ProduckPlugin::$options['poweredByLinkAllowed']);
        } else {
            return -1;
        }
    }

    public static function transformTitleToUrlPart($title)
    {
        $maxlength = 100;
        if (isset(ProduckPlugin::$options['maxQuackUrlTitleLength'])) {
            $maxlength = ProduckPlugin::$options['maxQuackUrlTitleLength'];
        }

        return substr(
            preg_replace(
                ProduckPlugin::$urlReplaceRegexp,
                '',
                str_replace(ProduckPlugin::$urlReplaceFrom, ProduckPlugin::$urlReplaceTo, strtolower($title))
            ),
            0,
            $maxlength
        );
    }

    public static function getHash($string)
    {
        return substr(base_convert(md5($string), 16, 10), -5);
    }

    /**
     * Determine if a given string starts with a given substring.
     *
     * @param  string  $haystack
     * @param  string|array  $needle
     * @return bool
     */
    public static function startsWith($haystack, $needle)
    {
        foreach ((array) $needle as $currentChar) {
            if ($currentChar != '' && strpos($haystack, $needle) === 0) return true;
        }

        return false;
    }

    public static function getNotFoundContent()
    {
        return '<div>Die Seite konnte nicht gefunden werden</div>';
    }
}

ProduckPlugin::initOptions(); // must be called before instantiation of the main class ProduckPlugin
$pluginInstance = new ProduckPlugin();
$pluginInstance->init();
