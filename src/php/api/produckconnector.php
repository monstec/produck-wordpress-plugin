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

        // check if quack contains required properties
        if (!isset($quackData['messages']) || !isset($quackData['messages'][0])) {
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

        if (!isset($quackData['title'])) {
            return null;
        }

        return $quackData;
    }

    /**
     * Get the quack overview from cache or api if cache is expired.
     */
    public function getQuacks($max = 0) {
        //TODO checkCache

        $response = $this->api->getQuacks();
        $quackData = json_decode($response, true);

        if (is_array($quackData) && sizeof($quackData) > 0) {
            if (isset($max) && is_numeric($max) && $max > 0 && $max <= sizeof($quackData)) {
                $quackData = array_slice($quackData, 0, $max);
            }
            return $quackData;
        } else {
            return null;
        }
    }
}
?>