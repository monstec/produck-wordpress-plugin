<?php

namespace MonsTec\Produck;

// prevent direct access
defined('ABSPATH') or die('Quidquid agis, prudenter agas et respice finem!');

class ProduckConnector
{
    protected $api;
    protected $cache;

    function __construct($produckApi, $produckCache)
    {
        $this->api = $produckApi;
        $this->cache = $produckCache;
    }

    /**
     * Get a single quack from the cache or fetch it from Produck if the cache is expired or the quack simply
     * is not in the cache currently.
     */
    public function getQuack($id)
    {
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
    public function getQuacks($max = 0)
    {
        //TODO checkCache

        $response = $this->api->getQuacks();
        $quackData = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON decode error: ' . json_last_error_msg());
        } else if (isset($quackData['quacks']['content']) && is_array($quackData['quacks']['content']) && !empty($quackData['quacks']['content'])) {
            if (isset($max) && is_numeric($max) && $max > 0 && $max <= sizeof($quackData['quacks']['content'])) {
                $quackData = array_slice($quackData['quacks']['content'], 0, $max);
            }
            return $quackData['quacks']['content'];
        } else {
            // Handle the case where 'content' is not found or is empty
            error_log('Content key not found or empty in the response');
            return null;
        }
    }

    /**
     * Get the user data from quacks object from cache or api if cache is expired.
     */
    public function getUsers($max = 0)
    {
        // Retrieve the response
        $response = $this->api->getQuacks();
        $userData = json_decode($response, true);

        // Check for JSON decode errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON decode error: ' . json_last_error_msg());
            return null;
        }

        // Check if 'users' key exists and is an array
        if (isset($userData['users']) && is_array($userData['users']) && !empty($userData['users'])) {
            // Handle the case where max limit is set
            if ($max > 0 && $max <= count($userData['users'])) {
                return array_slice($userData['users'], 0, $max, true);
            }

            return $userData['users'];
        } else {
            // Handle the case where 'users' is not found or is empty
            error_log('Users key not found or empty in the response');
            return null;
        }
    }
}
