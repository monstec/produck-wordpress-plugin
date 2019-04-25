<?php
namespace MonsTec\Produck;

use ProduckPlugin;

// prevent direct access
defined('ABSPATH') or die('Quidquid agis, prudenter agas et respice finem!');

class Controller {
    private $pages;
    private $loader;
    private $matched;
    private $params;

    function __construct(TemplateLoader $loader) {
        $this->pages = new \SplObjectStorage;
        $this->loader = $loader;
    }

    function init() {
        do_action('produck_virtual_pages', $this);
    }

    function addPage(Page $page) {
        $this->pages->attach( $page );
        return $page;
    }

    function dispatch($bool, \WP $wp) {
        if ($this->checkRequest() && $this->matched instanceof Page) {
            $this->loader->init($this->matched);
            $wp->virtual_page = $this->matched;
            do_action('parse_request', $wp);
            $this->setupQuery();
            do_action('wp', $wp);
            $this->loader->load();
            $this->handleExit();
        }
        return $bool;
    }

    private function checkRequest() {
        $this->pages->rewind();

        $rawPath = $this->getPathInfo();
        $path = '';
        $realParamsPart = ''; // "real" means the params after a question mark
        $realParamsStartIndex = strpos($rawPath, '?'); //will return FALSE if question mark not found

        if ($realParamsStartIndex) {
            $path = substr($rawPath, 0, $realParamsStartIndex);
            // if there are indeed characters after the question mark, preserve that part
            if (strlen($rawPath) > strlen($path) + 1) {
                $realParamsPart = substr($rawPath, $realParamsStartIndex + 1);
            }
        } else {
            $path = $rawPath;
        }

        $path = trim($path, '/');

        while($this->pages->valid()) {
            $trimmedPageUrl = rtrim($this->pages->current()->getUrl(), '/' );
            $nicePageUrlStart = $trimmedPageUrl.'/'; // make sure the  '/' is there (and only one)

            if ($path === $trimmedPageUrl  // for matches of type shop.com/quack of (shop.com)/quack?id=1
                    || ProduckPlugin::startsWith($path, $nicePageUrlStart)) { // for matches of nice URL type like (shop.com)/quack/1/ein-quack
                $pathParamsPart = '';
                $niceStartLength = strlen($nicePageUrlStart);
                if (strlen($path) > $niceStartLength) {
                    $pathParamsPart = substr($path, $niceStartLength);
                }

                $this->matched = $this->pages->current();
                $this->params = $this->extractParams($realParamsPart, $pathParamsPart);

                return TRUE;
            }

            $this->pages->next();
        }
    }

    /**
     * Extracts parameters and values out of URL parts. Only supports one-dimensional parameters.
     * Path parameters win over "real" parameters, which means that in a URL of the form
     * foo.com/quack/1/bar?1=123
     * the indexed param '0' will have the value 1 and not 123.
     */
    private function extractParams($rawRealParams, $rawPathParams) {
        $parameters = array();
        //first process "real" params
        if (strlen($rawRealParams) > 0) {
            $pairs = explode('&', $rawRealParams);
            foreach($pairs as $pairString) {
                $pair = explode('=', $pairString);

                if (isset($pair[0])) {
                    $value = (isset($pair[1])) ? $pair[1] : '';
                    $parameters[$pair[0]] = $value;
                }
            }
        }

        // then pass path params so that these will win in case of a conflict
        if (strlen($rawPathParams) > 0) {
            $rawPathParams = trim($rawPathParams, '/');
            $values = explode('/', $rawPathParams);

            $index = 0;
            forEach ($values as $value) {
                $parameters[$index] = $value;
                $index++;
            }
        }

        return $parameters;
    }

    private function getPathInfo() {
        $home_path = parse_url(home_url(), PHP_URL_PATH);
        // $home_path will be NULL if wordpress is installed directly in the web-root
        // Omitting the second argument of add_query_arg (i.e. an URL) lets the method return
        // the value of $_SERVER['REQUEST_URI'].
        return preg_replace("#^/?{$home_path}/#", '/', add_query_arg(array()));
    }

    private function setupQuery() {
        global $wp_query;
        $wp_query->init();
        $wp_query->is_page       = TRUE;
        $wp_query->is_singular   = TRUE;
        $wp_query->is_home       = FALSE;
        $wp_query->found_posts   = 1;
        $wp_query->post_count    = 1;
        $wp_query->max_num_pages = 1;
        $posts = (array) apply_filters(
            'the_posts', array($this->matched->asWpPost($this->params)), $wp_query
        );
        $post = $posts[0];
        $wp_query->posts          = $posts;
        $wp_query->post           = $post;
        $wp_query->queried_object = $post;
        $GLOBALS['post']          = $post;
        $wp_query->virtual_page   = $post instanceof \WP_Post && isset($post->is_virtual)
            ? $this->matched
            : NULL;
    }

    public function handleExit() {
        exit();
    }
}
?>