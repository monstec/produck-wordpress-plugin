<?php
namespace MonsTec\Produck;

// prevent direct access
defined('ABSPATH') or die('Quidquid agis, prudenter agas et respice finem!');

class Page {
    private $url;
    private $content;
    private $template;
    private $wp_post;

    function __construct($url, $template = 'page.php') {
        $this->url = filter_var($url, FILTER_SANITIZE_URL);
        $this->setTemplate($template);
    }

    function getUrl() {
        return $this->url;
    }

    function getTemplate() {
        return $this->template;
    }

    function setContent(DynamicPageContent $content) {
        $this->content = $content;
        return $this;
    }

    function setTemplate($template) {
        $this->template = $template;
        return $this;
    }

    function asWpPost($requestParams) {
        if (is_null($this->wp_post)) {
            if ($this->content) {
                $this->content->create($requestParams);
                $postContent = $this->content->getPostContent();
                $postTitle = $this->content->getPostTitle();
            } else {
                $postContent = '';
                $postTitle = 'untitled';
            }

            $post = array(
                'ID'             => 0,
                'post_title'     => $postTitle,
                'post_name'      => sanitize_title($postTitle),
                'post_content'   => $postContent,
                'post_excerpt'   => '',
                'post_parent'    => 0,
                'menu_order'     => 0,
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
                'comment_count'  => 0,
                'post_password'  => '',
                'to_ping'        => '',
                'pinged'         => '',
                'guid'           => home_url($this->getUrl()),
                'post_date'      => current_time('mysql'),
                'post_date_gmt'  => current_time('mysql', 1),
                'post_author'    => is_user_logged_in() ? get_current_user_id() : 0,
                'is_virtual'     => TRUE,
                'filter'         => 'raw'
            );
            $this->wp_post = new \WP_Post((object)$post);
        }
        return $this->wp_post;
    }
}
?>