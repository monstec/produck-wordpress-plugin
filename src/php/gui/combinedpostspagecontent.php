<?php

namespace MonsTec\Produck;

use ProduckPlugin;
use WP_Query;

// prevent direct access
defined('ABSPATH') or die('Quidquid agis, prudenter agas et respice finem!');

class MergedOverviewPageContent implements DynamicPageContent
{
    protected $connector;
    protected $content;
    protected $quacksDataObj;
    protected $internal_posts = array(); // Store internal articles
    protected $external_posts = array(); // Store external articles
    protected $combined_posts = array(); // Store combined articles
    protected $added_external_post_ids = array(); // Array to track already merged external post IDs
    protected $implementationLoopsRun = 0; // Number of implementation loops run

    function __construct($produckConnectorObject)
    {
        $this->connector = $produckConnectorObject;
    }

    private static function get_author_name($author_id = null, $user = null)
    {
        if ($author_id) {
            $full_name = get_the_author_meta('display_name', $author_id);
            return !empty($full_name) ? $full_name : "Unbekannter Autor";
        } elseif ($user) {
            if (isset($user['fullName'])) {
                return $user['fullName'];
            } elseif (isset($user['nickname']) && !empty($user['nickname'])) {
                return $user['nickname'];
            } else {
                return "Unknown Author";
            }
        }
        return "Unknown Author";
    }

    // Helper function to handle the summary logic
    private static function get_summary($content)
    {
        return wp_trim_words($content, 30, '...');
    }

    public function create(array $requestParams)
    {
        // Helper function to handle the author logic

        // 1. WordPress Internal Articles
        $args = array(
            'post_type' => 'post',
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        $wp_query = new WP_Query($args);

        if (is_wp_error($wp_query)) {
            error_log('Error occured while creating an internal post object: ' . $wp_query->get_error_message());
            return;  // Exit early if there is an error
        }

        if ($wp_query->have_posts()) {
            while ($wp_query->have_posts()) {
                $wp_query->the_post();
                $author_id = get_the_author_meta('ID');

                // Build the internal article array
                $article = array(
                    'ID' => get_the_ID(),
                    'post_author' => $author_id,
                    'post_date' => get_the_date('Y-m-d H:i:s'),
                    'post_modified' => get_the_modified_date('Y-m-d H:i:s'),
                    'post_content' => get_the_content(),
                    'post_excerpt' => get_the_excerpt(),
                    'post_title' => get_the_title(),
                    'post_status' => get_post_status(),
                    'comment_status' => get_post_meta(get_the_ID(), 'comment_status', true),
                    'ping_status' => get_post_meta(get_the_ID(), 'ping_status', true),
                    'post_name' => get_post_field('post_name', get_the_ID()),
                    'guid' => get_permalink(),
                    'post_type' => get_post_type(),
                    'comment_count' => get_comments_number(),
                    'link' => get_permalink(),
                    'author' => self::get_author_name($author_id),
                    'summary' => self::get_summary(get_the_content()),
                    'primaryImage' => has_post_thumbnail() ? get_the_post_thumbnail_url(get_the_ID(), 'full') : null,
                    'source' => 'internal',
                );

                // Add to internal articles
                $this->internal_posts[] = $article;
            }
            wp_reset_postdata();
        }

        // 2. External API Articles (Quacks)
        $this->quacksDataObj = $this->connector->getQuacksAndUsers(ProduckPlugin::getMaxPostsToRecall());
        if (is_wp_error($this->quacksDataObj)) {
            error_log('Error occured while creating an external post object: ' . $this->quacksDataObj->get_error_message());
            return;  // Exit early if external API failed
        }
        $usersData = !empty($this->quacksDataObj['users']) ? $this->quacksDataObj['users'] : null;

        $modePrimaryImages = ProduckPlugin::getPostsWoPrimaryImageModus();

        if (!empty($this->quacksDataObj['quacks'])) {
            foreach ($this->quacksDataObj['quacks'] as $quack) {

                if (!isset($quack['title']) || strlen($quack['title']) < 1 || !isset($quack['id']) || strlen($quack['id']) < 1 || ($modePrimaryImages == 1 && !isset($quack['primaryImage']))) {
                    continue;
                }

                $quackId = $quack['id'];
                $userId = $quack['userId'];
                $user = $usersData[$userId] ?? null;
                $title = $quack['title'];
                $summary = isset($quack['summary']) ? self::get_summary($quack['summary']) : '';
                $publicationTime = date('Y-m-d H:i:s', strtotime($quack['publicationTime']));
                $prettyUrlTitlePart = ProduckPlugin::transformTitleToUrlPart($title);
                $quackLink = rtrim(home_url(), '/') . '/quack/' . $quackId . '/' . $prettyUrlTitlePart;

                // Build the external article array
                $article = array(
                    'ID' => $quack['id'],
                    'post_title' => $title,
                    'post_name' => $title,
                    'post_content' => $summary,
                    'post_excerpt' => $summary,
                    'post_date' => $publicationTime,
                    'post_modified' => isset($quack['lastModifiedTime']) ? date('Y-m-d H:i:s', strtotime($quack['lastModifiedTime'])) : $publicationTime,
                    'post_author' => $userId,
                    'post_author_name' => self::get_author_name(null, $user),
                    'link' => $quackLink,
                    'primaryImage' => isset($quack['primaryImage']) ? $quack['primaryImage'] : ProduckPlugin::getImageURL('monstec_image_placeholder.jpg'),
                    'portraitImage' => isset($user['portraitImg']) ? $user['portraitImg'] : ProduckPlugin::getImageURL('ducky_portrait_placeholder_transparent_xs_borderless.png'),
                    'post_type' => $quack['referenceType'],
                    'post_status' => $quack['quackMode'],
                    'tags' => $quack['tags'],
                    'views' => $quack['views'],
                    'source' => 'external_api',
                );

                // Add to external articles
                $this->external_posts[] = $article;
            }
        }

        // Combine internal and external articles into the combined_posts array
        $this->combined_posts = array_merge($this->internal_posts, $this->external_posts);

        // Sort by date
        usort($this->combined_posts, function ($a, $b) {
            return strtotime($b['post_date']) - strtotime($a['post_date']);
        });

        return $this->combined_posts; // Return the combined articles as the main output
    }

    private static function create_virtual_wp_post($article)
    {
        if (is_wp_error($article)) {
            error_log('Error occured while creating a virtual post object: ' . $article->get_error_message());
            return null;
        }

        // Create a new WP_Post object for each article
        $post = new \stdClass(); // Create a new stdClass to hold post data

        // Assign unique data from the article to the new post
        $post->ID = 900000 + abs($article['ID']); // Use high value for post id to avoid conflicts
        $post->post_author = -abs($article['post_author']); // Default to 1 or the article's author
        $post->post_author_id = $article['post_author']; // Default to 1 or the article's author
        $post->post_author_name = $article['post_author_name'];
        $post->post_date = $article['post_date'];
        $post->post_date_gmt = get_gmt_from_date($article['post_date']);
        $post->post_content = $article['post_content'];
        $post->post_title = $article['post_title'];
        $post->post_excerpt = $article['post_excerpt'];
        $post->post_status = 'publish';
        $post->comment_status = 'closed';
        $post->ping_status = 'closed';
        $post->post_password = '';
        $post->post_name = sanitize_title($post->post_title); // Create a sanitized post slug
        $post->to_ping = '';
        $post->pinged = '';
        $post->post_modified = isset($article['post_modified']) ? $article['post_modified'] : '';
        $post->post_modified_gmt = isset($article['post_modified']) ? $article['post_modified'] : '';
        $post->post_content_filtered = '';
        $post->post_parent = 0;
        $post->guid = $article['link']; // Use article link or home URL
        $post->menu_order = 0;
        $post->post_type = 'post';
        $post->post_mime_type = '';
        $post->comment_count = 0;
        $post->filter = 'raw';
        // Mark the post as produck virtual
        $post->is_produck_virtual = true;

        if (isset($article['primaryImage'])) {
            $post->primaryImage = $article['primaryImage'];
            update_post_meta($post->ID, 'featured_image', $article['primaryImage']);
            update_post_meta($post->ID, '_thumbnail_id', $article['primaryImage']);
            update_post_meta($post->ID, 'custom_image_url', $article['primaryImage']);
        }

        if (isset($article['portraitImage'])) {
            $post->post_author_portrait = $article['portraitImage'];
        }
        self::set_produck_category($post);

        return new \WP_Post($post); // Convert stdClass to WP_Post object
    }

    public function pre_get_posts_handler($query)
    {
        if ($query->is_main_query() && !$query->is_admin()) {
            error_log("pre_get_posts handler running on main query");

            // Fetch external posts using the connector
            $this->external_posts = $this->getExternalPostData();

            if (empty($this->external_posts)) {
                error_log("External posts are empty in pre_get_posts");
            } else {
                error_log("External posts fetched in pre_get_posts: ");
            }
        }
    }

    /**
     * Turn if the query is related to the menu.
     */
    public function the_posts_handler($posts, $query, $modeOfIntegration, $modeOfDuplicatePosts, $maxLoops)
    {
        $localMainQuery = $query->is_main_query();
        $globalQuery = is_main_query();

        $queryRealm = $modeOfIntegration == 'integrateInMainQuery' ? $localMainQuery : $globalQuery;
        
        // Exclude menu queries
        if ($this->is_menu_query($query) || !$queryRealm || !is_front_page() || is_admin()) {
            error_log("This is a non-valid (menu/off-realm/not front page or admin) query, skipping external posts.");
            return $posts;
        }
        
        
        // Merge external posts for various queries on the front page, i.e., sidebar and widgets
        if (empty($this->external_posts)) {
            error_log("No external posts found and thus not set in the_posts handler.");
            return $posts;  // Return original posts if nothing was fetched
        }
        
        // Merge external posts with the main posts
        error_log("Post Handler is now merging external and internal posts.") ;        
        
        $filtered_external_posts = $this->external_posts;
        if ($this->implementationLoopsRun >= $maxLoops && $modeOfDuplicatePosts == 1) {
            $filtered_external_posts = array_filter($this->external_posts, function ($external_post) {
                return !in_array($external_post['ID'], $this->added_external_post_ids);
            });

            if (empty($filtered_external_posts)) {
                error_log("All external posts are duplicates; returning only internal posts.");
                return $posts;
            }
        }

        // Convert remaining external posts to WP_Post objects
        $new_posts = array_map(function ($external_post) {
            // Track this post ID as added to prevent duplication in other areas
            $this->added_external_post_ids[] = $external_post['ID'];
            return $this->create_virtual_wp_post($external_post);
        }, $filtered_external_posts);

        // Merge the new virtual posts with the existing posts
        $merged_posts = array_merge($posts, $new_posts);

        // Sort the merged posts by date (most recent first)
        usort($merged_posts, function ($a, $b) {
            return strtotime($b->post_date) - strtotime($a->post_date);
        });

        // Cache each virtual post to make them behave like real posts in WP
        foreach ($merged_posts as $virtual_post) {
            if (!wp_cache_get($virtual_post->ID, 'posts')) {
                wp_cache_set($virtual_post->ID, $virtual_post, 'posts');
            }
        }

        $this->implementationLoopsRun++;
        error_log("Merging run " . $this->implementationLoopsRun . " of " . $maxLoops . " runs accomplished. Returning merged posts.");
        return $merged_posts;
    }

    /**
     * Helper function to determine if the query is related to the menu.
     */
    private function is_menu_query($query)
    {
        // Check for typical menu-related query characteristics to avoid, external produck posts are displayed in the menu

        // 1. Check if it's querying pages, which i.e. could be a menu item
        if (isset($query->query_vars['post_type']) && $query->query_vars['post_type'] === 'page') {
            error_log("Query identified as a 'page' post type query, might be for the menu.");
            return true;
        }

        // 2. Check for custom queries by wp_list_pages or wp_nav_menu
        if (did_action('wp_list_pages') || did_action('wp_nav_menu')) {
            error_log("Query triggered by wp_list_pages or wp_nav_menu.");
            return true;
        }

        // 3. Check for query parameters specific to navigation or menus
        if (!empty($query->query_vars['menu_item']) || isset($query->query_vars['post_type']) && $query->query_vars['post_type'] === 'nav_menu_item') {
            error_log("Query related to 'nav_menu_item', might be for the menu.");
            return true;
        }

        return false;
    }

    public function createRedirectToMergedOverview()
    {
        if (is_front_page()) {

            // Call the getPostContent()-Methode auf
            $mergedContentOutput = $this->getPostContent();
            if (!empty($mergedContentOutput)) {
                get_header();
                echo '<div id="primary">';
                echo '<main id="main" class="site-main" role="main">';
                echo '<div class="combined-articles">';
                echo $mergedContentOutput;
                echo '</div>';
                echo '</main>';
                get_sidebar();
                echo '</div';
                get_footer();
                exit();
            }
        }
    }

    public function getPostMetadata($value, $post_id, $meta_key, $single)
    {

        if (is_front_page()) {
            $post = get_post($post_id);
            error_log("Metadata filter called for post ID: $post_id and meta key: $meta_key");

            if ($meta_key === '_thumbnail_id' && isset($post->is_produck_virtual)) {
                error_log("Returning dummy thumbnail ID for virtual post: $post_id");
                return -1; // Dummy thumbnail ID for virtual posts
            } else if (($meta_key === 'custom_image_url' || $meta_key === '_wp_attachment_metadata' || $meta_key === 'featured_image') && isset($post->is_produck_virtual)) {
                error_log("Returning dummy thumbnail ID for virtual post: $post_id");
                return $value;
            }
        }

        return $value;
    }

    public function setPostThumbnail($html, $post_id, $post_thumbnail_id, $size, $attr)
    {
        $post = get_post($post_id);

        if (is_front_page() && isset($post->is_produck_virtual) && $post->is_produck_virtual) {
            // Retrieve the external image URL from the post's meta or API
            $external_image_url = get_post_meta($post_id, 'featured_image', true);

            if (!empty($external_image_url)) {
                $default_attr = array(
                    'class' => 'wp-block-cover__image-background wp-post-image',
                    'loading' => 'lazy',
                    'decoding' => 'async',
                    'alt' => esc_attr(get_the_title($post_id) . ' thumbnail') // Set alt attribute based on post title
                );

                // Merge the attributes passed from the theme with the defaults
                $attributes = wp_parse_args($attr, $default_attr);
                $attributes = apply_filters('wp_get_attachment_image_attributes', $attributes, $post_thumbnail_id, $post_id, $size);

                $html = '<img src="' . esc_url($external_image_url) . '"';
                // Add all attributes dynamically
                foreach ($attributes as $name => $value) {
                    $html .= ' ' . esc_attr($name) . '="' . esc_attr($value) . '"';
                }
                $html .= ' />';
            }
        }

        return $html;
    }

    public function getAttachementImage($image, $attachment_id, $size, $icon)
    {
        $post = get_post();

        // Check if the current post is virtual and override the image URL
        if (isset($post->is_produck_virtual) && $post->is_produck_virtual) {
            // Get external image URL
            $external_image_url = get_post_meta($post->ID, 'featured_image', true);

            if (!empty($external_image_url)) {
                // Return the external image as an array similar to wp_get_attachment_image_src output
                return array($external_image_url, 800, 600, true); // Modify width/height as needed
            }
        }

        return $image; // Default image handling for non-virtual posts
    }

    // Filtering the authors display name is theme dependent, so subsequently we use multiple approaches to cover all themes
    // Filter to override the author name display
    public function setAuthorName($author)
    {
        global $post;
        // Check if this is a virtual post
        if (is_front_page() && isset($post->is_produck_virtual) && $post->is_produck_virtual) {
            if (isset($post->post_author_name)) {
                // Return the external author name
                return $post->post_author_name;
            }
        }
        return $author; // Return the default if it's not a virtual post
    }

    //Filter to override the author display name
    public function setAuthorDisplayName($display_name, $user_id)
    {
        global $post;

        // Check if this is a virtual post
        if (is_front_page() && isset($post->is_produck_virtual) && $post->is_produck_virtual) {
            if (isset($post->post_author_name)) {
                // Return the external author name
                return $post->post_author_name;
            }
        }

        return $display_name; // Return the default if it's not a virtual post
    }

    // add correct linking for produck author 
    public function setAuthorLink($link, $author_id)
    {
        global $post;

        // Check if this is a virtual post and you want to override the link
        if (is_front_page() && isset($post->is_produck_virtual) && $post->is_produck_virtual) {
            if (isset($post->post_author_id)) {
                // Return your custom author link
                $custom_link = 'https://produck.de/profile/' . $post->post_author_id;
                return $custom_link;
            }
        }

        // Return the default link for non-virtual posts
        return $link;
    }

    // Filter the avatar URL or HTML for virtual posts
    public function setAvatarImg($avatar, $id_or_email, $size, $default, $alt, $args)
    {
        global $post;

        if (is_front_page() && isset($post->is_produck_virtual) && $post->is_produck_virtual) {
            if (isset($post->post_author_portrait)) {
                // Set a custom avatar URL for virtual authors
                $custom_avatar_url = $post->post_author_portrait;

                // Construct the new avatar HTML
                $avatar = '<img alt="' . esc_attr($alt) . '" src="' . esc_url($custom_avatar_url) . '" class="avatar avatar-' . esc_attr($size) . ' photo avatar-default" height="' . esc_attr($size) . '" width="' . esc_attr($size) . '" loading="lazy" decoding="async">';
            }
        }

        return $avatar; // Return either the modified or the original avatar HTML
    }

    private static function set_produck_category($post)
    {
        $category_name = 'ProDuck Guestpost';
        $category = get_term_by('name', $category_name, 'category');

        if (!$category) {
            // Create the category if it doesn't exist
            $new_category = wp_insert_term(
                $category_name,
                'category',      // The taxonomy, which in this case is 'category'
                array(
                    'description' => 'Guest posts contributed by ProDuck.de authors.',
                    'slug' => 'produck-guestpost',
                )
            );

            if (is_wp_error($new_category)) {
                error_log('Failed to create category: ' . $new_category->get_error_message());
                return; // Exit if we couldn't create the category
            }

            $category_id = $new_category['term_id'];
        } else {
            $category_id = $category->term_id;
        }

        // Assign the category to the post
        wp_set_post_terms($post->ID, [$category_id], 'category');
    }

    // Getter to access internal articles
    public function getInternalPostData()
    {
        $this->create(array());
        return $this->internal_posts;
    }

    // Getter to access external articles
    public function getExternalPostData()
    {
        $this->create(array());
        return $this->external_posts;
    }

    // Getter to access combined articles
    public function getCombinedPostData()
    {
        $this->create(array());
        return $this->combined_posts;
    }

    public function getPostTitle()
    {
        $externalPosts = ProduckPlugin::getTranslations(false, 'text', 'external_posts');
        if (isset($externalPosts)) {
            return $externalPosts;
        } else {
            return 'Post Overview';
        }
    }

    public function getPostContent()
    {
        $articles = $this->create(array());
        $output = '<div class="combined-articles">';

        foreach ($articles as $article) {

            $output .= '<article class="article-' . esc_attr($article['source']) . '">';

            // Primäres Bild (falls vorhanden)
            if (isset($article['primaryImage'])) {
                $output .= '<img src="' . esc_url($article['primaryImage']) . '" alt="' . esc_attr($article['post_title']) . '" class="article-image">';
            }

            // Titel
            $output .= '<h2>' . esc_html($article['post_title']) . '</h2>';

            $authorOutput = function ($authorName, $post_date = null) {
                $output = '<p class="article-author">From: ' . esc_html($authorName);

                // If post_date is set, add it to the output
                if ($post_date) {
                    $output .= ' | ' . esc_html(date('d.m.Y', strtotime($post_date)));
                }

                $output .= '</p>';
                return $output;
            };

            // Check if the author name is set
            if (isset($article['post_author_name'])) {
                $output .= $authorOutput($article['post_author_name'], $article['post_date']);
            } else if (isset($article['author'])) {
                $output .= $authorOutput($article['author'], $article['post_date']);
            }

            // Kurzfassung/Abstract
            if (isset($article['post_excerpt']) && !empty($article['post_excerpt'])) {
                $output .= '<p class="article-summary">' . esc_html($article['post_excerpt']) . '</p>';
            }

            // Link
            $readMore = ProduckPlugin::getTranslations(false, 'text', 'read_more');

            $output .= '<a href="' . esc_url($article['guid']) . '">' . $readMore . '</a>';

            // Quelle
            $output .= ($article['source'] == 'internal') ? '' : '<p><small>Quelle: ProDuck.de</small></p>';
            $output .= '</article>';
        }

        $output .= '</div>';
        return $output;
    }

    public function getQuacksData()
    {
        return $this->quacksDataObj;
    }
}
