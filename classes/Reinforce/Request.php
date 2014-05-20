<?php

defined('SYSPATH') OR die('No direct script access.');

/**
 * Request. Uses the [Route] class to determine what
 * [Controller] to send the request to.
 *
 * @package    Kohana
 * @category   Base
 * @author     Kohana Team
 * @copyright  (c) 2008-2012 Kohana Team
 * @license    http://kohanaframework.org/license
 */
class Reinforce_Request extends Kohana_Request {

    /**
     * @var array    query parameters
     */
    protected $_put = array();

    /**
     * @var array    post parameters
     */
    protected $_delete = array();

    public static function factory($uri = TRUE, $client_params = array(), $allow_external = TRUE, $injected_routes = array())
    {
        // If this is the initial request
        if (!Request::$initial) {
            $protocol = HTTP::$protocol;

            // Use the server request method or Default to GET requests
            $method = getenv('REQUEST_METHOD') ? getenv('REQUEST_METHOD') : HTTP_Request::GET;

            if ((!getenv('HTTPS') AND filter_var(getenv('HTTPS'), FILTER_VALIDATE_BOOLEAN))
                    OR ( getenv('HTTP_X_FORWARDED_PROTO')
                    AND getenv('HTTP_X_FORWARDED_PROTO') === 'https')
                    AND in_array(getenv('REMOTE_ADDR'), Request::$trusted_proxies)) {
                // This request is secure
                $secure = TRUE;
            }
            // There is a referrer for this request
            $referrer = getenv('HTTP_REFERER');

            // Browser type
            Request::$user_agent = getenv('HTTP_USER_AGENT');

            // Typically used to denote AJAX requests
            $requested_with = getenv('HTTP_X_REQUESTED_WITH');


            if (getenv('HTTP_X_FORWARDED_FOR') AND getenv('REMOTE_ADDR') AND in_array(getenv('REMOTE_ADDR'), Request::$trusted_proxies)) {
                // Use the forwarded IP address, typically set when the
                // client is using a proxy server.
                // Format: "X-Forwarded-For: client1, proxy1, proxy2"
                $client_ips = explode(',', getenv('HTTP_X_FORWARDED_FOR'));

                Request::$client_ip = array_shift($client_ips);

                unset($client_ips);
            } else if (getenv('HTTP_CLIENT_IP')
                    AND getenv('REMOTE_ADDR')
                    AND in_array(getenv('REMOTE_ADDR'), Request::$trusted_proxies)) {
                // Use the forwarded IP address, typically set when the
                // client is using a proxy server.
                $client_ips = explode(',', getenv('HTTP_CLIENT_IP'));

                Request::$client_ip = array_shift($client_ips);

                unset($client_ips);
            } elseif (getenv('REMOTE_ADDR')) {
                // The remote IP address
                Request::$client_ip = getenv('REMOTE_ADDR');
            }

            // Ensure the raw body is saved for future use
            $body = ($method !== HTTP_Request::GET) ? file_get_contents('php://input') : NULL;

            // Handle raw to json data
            $parse_json = json_decode($body, TRUE);
            $json_data = (json_last_error() === JSON_ERROR_NONE AND is_array($parse_json)) ? $parse_json : array();

            // Handle raw to xml
            $xml_data = array();
            if (empty($json_data)) {
                libxml_use_internal_errors(TRUE);
                $parse_xml = json_decode(json_encode(simplexml_load_string($body)), TRUE);
                $xml_data = (json_last_error() === JSON_ERROR_NONE AND is_array($parse_xml)) ? $parse_xml : array();
            }

            // Handle x-www-form-urlencode data
            $form_data = array();
            if ($method !== HTTP_Request::POST AND empty($json_data) AND empty($xml_data)) {
                parse_str($body, $form_data);
                $form_data = is_array($form_data) ? $form_data : array();
            } else if (empty($_POST)) {
                $_POST = array_merge($_POST, $xml_data, $json_data);
                $_REQUEST = array_merge($_POST, $_GET);
            }
            
            // Merge data
            $rest_data = array_merge($form_data, $xml_data, $json_data);


            if ($uri === TRUE) {
                // Attempt to guess the proper URI
                $uri = Request::detect_uri();
            }

            $cookies = array();

            if (($cookie_keys = array_keys($_COOKIE))) {
                foreach ($cookie_keys as $key) {
                    $cookies[$key] = Cookie::get($key);
                }
            }

            // Create the instance singleton
            Request::$initial = $request = new Request($uri, $client_params, $allow_external, $injected_routes);

            switch ($method) {
                case HTTP_Request::PUT:
                    $request->put($rest_data);
                    break;
                case HTTP_Request::DELETE:
                    $request->delete($rest_data);
                    break;
            }

            // Store global GET and POST data in the initial request only
            $request->protocol($protocol)
                    ->query($_GET)
                    ->post($_POST);

            if (isset($secure)) {
                // Set the request security
                $request->secure($secure);
            }

            if (isset($method)) {
                // Set the request method
                $request->method($method);
            }

            if (isset($referrer)) {
                // Set the referrer
                $request->referrer($referrer);
            }

            if (isset($requested_with)) {
                // Apply the requested with variable
                $request->requested_with($requested_with);
            }

            if (isset($body)) {
                // Set the request body (probably a PUT type)
                $request->body($body);
            }

            if (isset($cookies)) {
                $request->cookie($cookies);
            }
        } else {
            $request = new Request($uri, $client_params, $allow_external, $injected_routes);
        }

        return $request;
    }

    public function get($key = NULL, $value = NULL)
    {
        return $this->query($key , $value);
    }

    public function put($key = NULL, $value = NULL)
    {
        if (is_array($key)) {
            // Act as a setter, replace all query strings
            $this->_put = $key;

            return $this;
        }

        if ($key === NULL) {
            // Act as a getter, all query strings
            return $this->_put;
        } elseif ($value === NULL) {
            // Act as a getter, single query string
            return Arr::path($this->_put, $key);
        }

        // Act as a setter, single query string
        $this->_put[$key] = $value;

        return $this;
    }

    public function delete($key = NULL, $value = NULL)
    {
        if (is_array($key)) {
            // Act as a setter, replace all query strings
            $this->_delete = $key;

            return $this;
        }

        if ($key === NULL) {
            // Act as a getter, all query strings
            return $this->_delete;
        } elseif ($value === NULL) {
            // Act as a getter, single query string
            return Arr::path($this->_delete, $key);
        }

        // Act as a setter, single query string
        $this->_delete[$key] = $value;

        return $this;
    }

}
