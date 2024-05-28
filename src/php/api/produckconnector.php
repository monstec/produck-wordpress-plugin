<?php
namespace MonsTec\Produck;

// prevent direct access
defined('ABSPATH') or die('Quidquid agis, prudenter agas et respice finem!');

class ProduckConnector {
    protected $api;
    protected $cache;

    function __construct($produckApi, $produckCache) {
        $this->api = $produckApi;
        $this->cache = $produckCache;
    }

    /**
     * Get a single quack from the cache or fetch it from Produck if the cache is expired or the quack simply
     * is not in the cache currently.
     */
    public function getQuack($id) {
        //TODO checkCache

        $response = $this->api->getQuack($id);
        $quackData = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON decode error: ' . json_last_error_msg());
        } else if (isset($quackData) && !empty($quackData)) {            
            return $quackData;
        } else {
            // Handle the case where 'content' is not found or is empty
            error_log('Empty in the response');
            return null;
        }
    }

    /**
     * Get the quack overview from cache or api if cache is expired.
     */
    public function getQuacks($max = 0) {
        //TODO checkCache

        $response = $this->api->getQuacks();
        $quackData = json_decode($response, true);


        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON decode error: ' . json_last_error_msg());
        } else if (isset($quackData['content']) && is_array($quackData['content']) && !empty($quackData['content'])) {
            if (isset($max) && is_numeric($max) && $max > 0 && $max <= sizeof($quackData['content'])) {
                $quackData = array_slice($quackData['content'], 0, $max);
            }
            return $quackData['content'];
        } else {
            // Handle the case where 'content' is not found or is empty
            error_log('Content key not found or empty in the response');
            return null;
        }
    }
}
?>