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

            if (isset($_SERVER['REQUEST_METHOD'])) {
                // Use the server request method
                $method = $_SERVER['REQUEST_METHOD'];
            } else {
                // Default to GET requests
                $method = HTTP_Request::GET;
            }

            if ((!empty($_SERVER['HTTPS']) AND filter_var($_SERVER['HTTPS'], FILTER_VALIDATE_BOOLEAN))
                    OR ( isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
                    AND $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                    AND in_array($_SERVER['REMOTE_ADDR'], Request::$trusted_proxies)) {
                // This request is secure
                $secure = TRUE;
            }

            if (isset($_SERVER['HTTP_REFERER'])) {
                // There is a referrer for this request
                $referrer = $_SERVER['HTTP_REFERER'];
            }

            if (isset($_SERVER['HTTP_USER_AGENT'])) {
                // Browser type
                Request::$user_agent = $_SERVER['HTTP_USER_AGENT'];
            }

            if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                // Typically used to denote AJAX requests
                $requested_with = $_SERVER['HTTP_X_REQUESTED_WITH'];
            }

            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])
                    AND isset($_SERVER['REMOTE_ADDR'])
                    AND in_array($_SERVER['REMOTE_ADDR'], Request::$trusted_proxies)) {
                // Use the forwarded IP address, typically set when the
                // client is using a proxy server.
                // Format: "X-Forwarded-For: client1, proxy1, proxy2"
                $client_ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);

                Request::$client_ip = array_shift($client_ips);

                unset($client_ips);
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])
                    AND isset($_SERVER['REMOTE_ADDR'])
                    AND in_array($_SERVER['REMOTE_ADDR'], Request::$trusted_proxies)) {
                // Use the forwarded IP address, typically set when the
                // client is using a proxy server.
                $client_ips = explode(',', $_SERVER['HTTP_CLIENT_IP']);

                Request::$client_ip = array_shift($client_ips);

                unset($client_ips);
            } elseif (isset($_SERVER['REMOTE_ADDR'])) {
                // The remote IP address
                Request::$client_ip = $_SERVER['REMOTE_ADDR'];
            }

            $rest_data = array();
            if ($method !== HTTP_Request::GET) {
                // Ensure the raw body is saved for future use
                $body = file_get_contents('php://input');

                // Handle raw data
                $parse_json = json_decode($body, TRUE);
                $json_data = (json_last_error() === JSON_ERROR_NONE AND is_array($parse_json)) ? $parse_json : array();

                // Handle xml data
                $xml_data = array();
                if (empty($json_data)) {
                    libxml_use_internal_errors(TRUE);
                    $parse_xml = json_decode(json_encode(simplexml_load_string($body)), TRUE);
                    $xml_data = (json_last_error() === JSON_ERROR_NONE AND is_array($parse_xml)) ? $parse_xml : array();
                }

                // Handle x-www-form-urlencode
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
            }

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
        return $this->query($key = NULL, $value = NULL);
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

    /**
     * Processes the request, executing the controller action that handles this
     * request, determined by the [Route].
     *
     * 1. Before the controller action is called, the [Controller::before] method
     * will be called.
     * 2. Next the controller action will be called.
     * 3. After the controller action is called, the [Controller::after] method
     * will be called.
     *
     * By default, the output from the controller is captured and returned, and
     * no headers are sent.
     *
     *     $request->execute();
     *
     * @return  Response
     * @throws  Request_Exception
     * @throws  HTTP_Exception_404
     * @uses    [Kohana::$profiling]
     * @uses    [Profiler]
     */
    public function execute()
    {
        if (!$this->_external) {
            $processed = Request::process($this, $this->_routes);

            if ($processed) {
                // Store the matching route
                $this->_route = $processed['route'];
                $params = $processed['params'];

                // Is this route external?
                $this->_external = $this->_route->is_external();

                if (isset($params['directory'])) {
                    // Controllers are in a sub-directory
                    $this->_directory = $params['directory'];
                }

                // Store the controller
                $this->_controller = $params['controller'];

                // Store the action
                $this->_action = (isset($params['action'])) ? $params['action'] : Route::$default_action;

                // These are accessible as public vars and can be overloaded
                unset($params['controller'], $params['action'], $params['directory']);

                // Params cannot be changed once matched
                $this->_params = $params;
            }
        }

        if (!$this->_route instanceof Route) {
            return HTTP_Exception::factory(404, 'Unable to find a route to match the URI: :uri', array(
                                ':uri' => $this->_uri,
                            ))->request($this)
                            ->get_response();
        }

        if (!$this->_client instanceof Request_Client) {
            throw new Request_Exception('Unable to execute :uri without a Kohana_Request_Client', array(
        ':uri' => $this->_uri,
            ));
        }

        return $this->_client->execute($this);
    }

    /**
     * Renders the HTTP_Interaction to a string, producing
     *
     *  - Protocol
     *  - Headers
     *  - Body
     *
     *  If there are variables set to the `Kohana_Request::$_post`
     *  they will override any values set to body.
     *
     * @return  string
     */
    public function render()
    {
        if (!$post = $this->post()) {
            $body = $this->body();
        } else {
            $this->headers('content-type', 'application/x-www-form-urlencoded; charset=' . Kohana::$charset);
            $body = http_build_query($post, NULL, '&');
        }

        // Set the content length
        $this->headers('content-length', (string) $this->content_length());

        // If Kohana expose, set the user-agent
        if (Kohana::$expose) {
            $this->headers('user-agent', Kohana::version());
        }

        // Prepare cookies
        if ($this->_cookies) {
            $cookie_string = array();

            // Parse each
            foreach ($this->_cookies as $key => $value) {
                $cookie_string[] = $key . '=' . $value;
            }

            // Create the cookie string
            $this->_header['cookie'] = implode('; ', $cookie_string);
        }

        $output = $this->method() . ' ' . $this->uri() . ' ' . $this->protocol() . "\r\n";
        $output .= (string) $this->_header;
        $output .= $body;

        return $output;
    }

}
