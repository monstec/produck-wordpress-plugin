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
     * Get all quack data from cache or api if cache is expired.
     */
    public function getQuacksAndUsers($max = 0)
    {
        // Retrieve the response
        $response = $this->api->getQuacks();
        $data = json_decode($response, true);

        // Check for JSON decode errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON decode error: ' . json_last_error_msg());
            return null;
        }

        // Initialize the result array
        $result = [
            'quacks' => null,
            'users' => null,
            'totalPages' => 0,
            'pageNumber' => 0
        ];

        // Check if 'quacks' key exists and is an array
        if (isset($data['quacks']['content']) && is_array($data['quacks']['content']) && !empty($data['quacks']['content'])) {
            if (isset($max) && is_numeric($max) && $max > 0 && $max <= sizeof($data['quacks']['content'])) {
                $result['quacks'] = array_slice($data['quacks']['content'], 0, $max);
            } else {
                $result['quacks'] = $data['quacks']['content'];
            }

            // Add totalPages and pageNumber to the result
            $result['totalPages'] = $data['quacks']['totalPages'] ?? 0;
            $result['pageNumber'] = $data['quacks']['pageable']['pageNumber'] ?? 0;
        } else {
            error_log('Content key not found or empty in the response');
        }

        // Check if 'users' key exists and is an array
        if (isset($data['users']) && is_array($data['users']) && !empty($data['users'])) {
            if ($max > 0 && $max <= count($data['users'])) {
                $result['users'] = array_slice($data['users'], 0, $max, true);
            } else {
                $result['users'] = $data['users'];
            }
        } else {
            error_log('Users key not found or empty in the response');
        }

        return $result;
    }
}
