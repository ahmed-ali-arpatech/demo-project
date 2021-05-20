<?php

namespace Leads\Classes;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Hash;
use \Cache;
use Illuminate\Http\UploadedFile;

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

class APIHandler
{
    private $APIs;
    private $apiBase;
    private $authToken;
    private $assumeRole;

    public function __construct(){
        $this->APIs = config('apis');

        $this->apiBase = config('app.API_BASEURL');
        $this->authToken = \AuthHandler::check() ? \AuthHandler::authToken() : NULL;
        $this->userID = \AuthHandler::check() ? \AuthHandler::userID() : null;
        $this->cacheTime = config('main.cache_time');
        $this->assumeRole = \AuthHandler::getAssumeRole();
    }

    /**
     * Get result for specific API
     * @param  string $group    API key for config
     * @param  array  $data     Parametrs and values
     * @param  string $method
     * @param  int $index
     * @return json output
     */
    public function get($group, $method = 'GET', $index = 0, $data = [], $authToken = NULL)
    {

        $api = $this->APIs[$group][$method][$index];

        // Use header token or not
        $useToken = isset($api['use_token']) ? $api['use_token'] : true;

        // Final end point
        $endPoint = $api['end_point'];

        // API required headers
        $apiHeaders = isset($api['headers']) ? $api['headers'] : [];

        // Replace URL parameters with data
        $endPoint = $this->handleURLParameters($endPoint, $data);

        // Check if API needs to be cached
        $cache = isset($api['cache']) ? $api['cache'] : true;

        // API level cache time
        $cacheTime = isset($api['cache_time']) ? $api['cache_time'] : $this->cacheTime;

        // User Id
        $userID = $this->userID ;

        // random data to be use in cache key
        $mix = (!isset($api['cacheParams']) || $api['cacheParams']) ? $data : [];

        if (isset($api['dependents'])) {
            $this->clearDependents($api['dependents']);
        }
        // cache key for API
        $cacheKey = $this->createCacheKey($group,$method, $index,$mix,$userID);
        // Get or create cache for this end point
        if (0) {
            return $this->getOrCreateCache($cacheKey, $endPoint, $data, $method, $useToken, $cacheTime, $apiHeaders, $authToken);
        }

        try{

            // Direct call to API if cache is false
            return $this->callApi($endPoint, $data, $method, $useToken, $apiHeaders, $authToken);

        }catch(\Exception $ex){

        }


    }

    /**
     * Get result for specific API from endpoint
     * @param  string $endPoint
     * @param  array  $params
     * @return json output
     */
    private function callApi($endPoint, $params = [], $method, $useToken = true, $apiHeaders = [], $authToken = NULL)
    {
        $headers = [];

        if ($useToken) {
            $headers['headers'] = [
                'Authorization' => 'Bearer ' . (isset($authToken) ? $authToken : $this->authToken)
            ];
        }

        $headers['headers']['User-Agent'] = $_SERVER['HTTP_USER_AGENT'];

        $headers['http_errors'] = false;

        $headers['base_uri'] = $this->apiBase;

        // $headers['debug'] = true;

        $params['portal_type'] = 'customer';

        if(isset($params['json'])){
            $headers['json'] = $params['json'];
            $headers['json']['portal_type'] = 'customer';
        } elseif (isset($params['multipart'])) {
            $multipart = [];

            foreach ($params['multipart'] as $name=>$content) {
                // Adding File
                if ($name === 'files' && is_array($content)) {
                    foreach ($content as $key => $file) {
                        if ($file instanceof UploadedFile) {
                            if ($file->isValid()) {
                                $multipart[] = [
                                    'filename' => $file->getClientOriginalName(),
                                    'contents' => fopen($file->getPathname(), 'r'),
                                    'name' => $key,
                                ];
                            }
                        }
                    }
                } else {
                    if (!is_array($content)) {
                        $multipart[] = [
                            'contents' => $content,
                            'name' => $name,
                        ];
                    } else {
                        foreach($content as $multiKey => $multiValue){
                            $multiName = $name . '[' .$multiKey . ']' . (is_array($multiValue) ? '[' . key($multiValue) . ']' : '' ) . '';
                            $multipart[] = ['name' => $multiName, 'contents' => (is_array($multiValue) ? reset($multiValue) : $multiValue)];
                        }
                    }
                }
            }
            $multipart[] = [
                'contents' => 'customer',
                'name' => 'portal_type',
            ];
            $headers['multipart'] = $multipart;
        } else {
            $headers[$method == 'POST' ? 'form_params' : 'query'] = $params;
        }

        // Include required headers for the API
        $headers = array_merge_recursive($headers,$apiHeaders);
        if ($this->assumeRole && is_array($this->assumeRole) && count($this->assumeRole) > 0){
            $assume_role = $this->assumeRole;
            foreach($assume_role as $key => $value)
                $assume_role[ucfirst(strtolower(str_replace('_', "-", $key)))] = $value;
            $headers['headers'] = array_merge_recursive($headers['headers'], $assume_role);
        }

        $client = new Client([ 'verify' => false ]);

        $response = $client->request($method, $endPoint, $headers);

        $hasJson = strpos($response->getHeaderLine('content-type'), 'json');

        // Data to send to view
        $data = [];
        $data['statusCode'] = $response->getStatusCode();
        $data['contentType'] = $response->getHeaderLine('content-type');


        $data['content'] = $response->getStatusCode() == 204 ? json_encode([]) : $response->getBody()->getContents();
        // Decode if has json header
        if ($hasJson){
            $data['response'] = \GuzzleHttp\json_decode($data['content']);
        }
        if ($response->getStatusCode() != 200 && $hasJson) {
            $body = json_decode($data['content'], true);
            $data += $body;
        }

        // Log API Response Except Success, Post Success and No Content
        if (!in_array($response->getStatusCode(), [200, 201, 204]))
            $this->setApiLog($method, $this->apiBase . $endPoint, $data, $headers);

        return $data;
    }


    /**
     * Replace any variable included in the end point
     * @param  string $endpoint
     * @param  array $data
     * @return string
     */
    public function handleURLParameters($endPoint, $data)
    {
        $endPoint = $this->removeFilter($endPoint);

        return preg_replace_callback('/(\{.*?\})/',
            function($matches) use ($data) {
                $key = trim($matches[1],"{}");
                $pram = isset($data[$key]) ? $data[$key] : $matches[1];
                return ($key == 'mfgName')?$pram:urlencode($pram);
            },
            $endPoint
        );
    }

    /**
     * Remove Subfilter :filter
     * @param  string $endPoint
     * @return string valid endpoint for gyro
     */
    function removeFilter($endPoint)
    {
        $pos = strrpos($endPoint, '::');

        // Check if has filter
        if($pos !== false)
        {
            // Get string before colon
            $endPoint = strstr($endPoint, '::', true);
        }

        return $endPoint;
    }
    /**
     * Create a unique key for the caceh
     * @param $apiKey
     * @param $method
     * @param $index
     * @param  int $userID
     * @param string $params
     * @return str
     */
    public function createCacheKey($group,$endpoint,$index,$param = [],$userid)
    {
        return Hash('sha256', $group .$endpoint .$index. print_r($param, true) . $userid);
    }

    /**
     * Get or create cache for specific API
     * @param  string $endPoint
     * @param  array  $params
     * @param  int    $cacheTime
     * @return json output
     */
    private function getOrCreateCache($cacheKey, $endPoint, $params = [], $method, $useToken, $cacheTime, $apiHeaders, $authToken)
    {
        // Get it from cache
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Get the API responce
        $result = $this->callApi($endPoint, $params, $method, $useToken, $apiHeaders, $authToken);

        // Cache the result output
        if ($result['statusCode'] == 200) {
            Cache::put($cacheKey, $result, $cacheTime * 60);
        }

        return $result;
    }
    /**
     * Clear Dependants of API
     * @param  string $dependents
     * @return object
     */
    public function clearDependents($dependents)
    {
        if (!is_array($dependents)) return false;

        foreach ($dependents as $dependent) {
            $dependent = explode(',', $dependent);
            $userID = $this->userID ;
            $cacheKey = $this->createCacheKey($dependent[0], $dependent[1], $dependent[2],[], $userID);
            $this->clearCache($cacheKey);
        }
    }
    /**
     * Clear specific cache item
     * @param  str $cacheKey
     */
    public function clearCache($cacheKey)
    {
        return Cache::forget($cacheKey);
    }

    /**
     * Create User Specific Logs
     *
     * @param $method
     * @param $endPoint
     * @param $data
     * @param $headers
     * @return void
     */
    public static function setApiLog($method, $endPoint, $data, $headers)
    {
        $data['Request_headers'] = $headers;
        $handler = new RotatingFileHandler(storage_path() . '/logs/' . \AuthHandler::userID() . '.log', 0, Logger::INFO, true, 0664);
        $logger = new Logger(\AuthHandler::firstName() . " " . \AuthHandler::lastName());
        $handler->setFilenameFormat('{date}_{filename}', 'Y_m_d');
        $logger->pushHandler($handler);
        $array = [$method . ' - ' . $endPoint, json_encode($data)];

        $logger->addError('API-Response', $array);
    }

}
