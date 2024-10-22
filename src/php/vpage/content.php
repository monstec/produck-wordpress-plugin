<?php
namespace MonsTec\Produck;

// prevent direct access
defined('ABSPATH') or die('Quidquid agis, prudenter agas et respice finem!');

interface DynamicPageContent {
    /*
     * Creates among others title and content.
     */
    public function create(Array $requestParams);

    /**
     * Returns the title of the virtual post. May be only available after calling create!
     */
    public function getPostTitle();

    /**
     * Returns the content of the virtual post. May be only available after calling create!
     */
    public function getPostContent();
}
?>