<?php

defined('SYSPATH') OR die('No direct script access.');

abstract class Kohana_Controller_Rest extends Kohana_Controller {

    /**
     * @var integer 輸出的 HTTP 態碼
     */
    protected $http_status = NULL;

    /**
     * @var string 預設的輸出格式
     */
    protected $rest_format = 'json';

    /**
     * @var string the 最後輸出的格式
     */
    protected $format = NULL;

    /**
     * @var string 當輸出 xml 格式時，基本的節點名稱
     */
    protected $xml_basenode = 'xml';

    /**
     * @var mix 最後欲執行的 method
     */
    protected $method = null;

    /**
     * @var boolean 混合模式
     */
    protected $hybird = FALSE;

    /**
     * @var boolean json_encode 時，使用 JSON_NUMERIC_CHECK 參數
     */
    protected $json_numeric_check = TRUE;

    /**
     * @var array 支援的輸出格式
     */
    private $_supported_formats = array(
        'xml' => 'application/xml',
        'yaml' => 'application/yaml',
        'json' => 'application/json',
        'jsonp' => 'text/javascript',
        'serialized' => 'application/vnd.php.serialized',
        'php' => 'text/plain',
        'html' => 'text/html',
        'msgpack' => 'application/x-msgpack',
    );

    /**
     * @var mix 準備輸出的內容
     */
    protected $_content = null;

    /**
     * @var mix 最後欲執行的 action
     */
    private $_action = null;

    /**
     * 可支援 GET、PUT、DELETE、POST 為前綴詞的執行方法
     * 例如 get_index、put_index、delete_index 等…
     * 依照不同的請求 method 呼叫對應的 action
     *
     * @return  Response
     */
    public function execute() {

        // 取得小寫的 method 和 action
        $method = strtolower($this->request->method());
        $action = strtolower($this->request->action());

        // 若 REST 的 action 不存在，將以 action 為代替的 method
        $this->method = method_exists($this, "{$method}_{$action}") ? $method : 'action';

        // 取得路由參數集合
        $params = $this->request->param();

        // 取出其中的 format 格式參數
        $this->format = Arr::pull($params, 'format');

        // 在執行 action 之前，呼叫前置函式
        $this->_content = $this->before();

        if (empty($this->_content) AND method_exists($this, "{$this->method}_{$action}")) {
            $this->_content = call_user_func_array(array($this, "{$this->method}_{$action}"), $params);
        } else if ($this->hybird) {
            throw HTTP_Exception::factory(404, 'The requested URL :uri was not found on this server.', array(':uri' => $this->request->uri()))
                    ->request($this->request);
        } else {
            $this->method = $method;
            $this->http_status = 404;
            $this->_content = array(
                "error" => array(
                    'message' => sprintf('The requested URL %s was not found on this server.', $this->request->uri()),
                )
            );
        }

        // 如果為 REST 請求，對內容進行格式化
        if ($this->method != 'action') {

            $this->_format_content();
        }

        // 在執行 action 之後，呼叫前置後式
        $this->after();

        // 回應結果
        return $this->response;
    }

    /**
     * 依照格式輸出 REST 結果
     */
    private function _format_content() {
        //設定輸出的 HTTP 狀態碼
        $this->response->status($this->http_status);

        //檢查輸出格式是否支援
        $this->format = array_key_exists($this->format, $this->_supported_formats) ? $this->format : $this->rest_format;

        //指定輸出格式的類型
        $this->response->headers('Content-Type', Arr::get($this->_supported_formats, $this->format, 'text/plain') . '; charset=utf-8;');

        $formater = Format::factory($this->_content);

        //如果需要特別傳送參數的格式，再抽出來獨立處理
        switch ($this->format) {
            case 'html':
                $this->_content = empty($this->_content) ? '' : var_dump($this->_content, true);
                break;
            case 'xml':
                $this->_content = $formater->to_xml($this->_content, null, $this->xml_basenode);
                break;
            case 'json':
                // 為了能通用 IE 和其它瀏覽器，Content-Type 必需設定為 text/x-json
                $this->response->headers('Content-Type', "text/x-json; charset=utf-8;");
                if ($this->json_numeric_check) {
                    $this->_content = ($this->_content === null ) ? '' : json_encode($this->_content, JSON_NUMERIC_CHECK);
                } else {
                    $this->_content = ($this->_content === null ) ? '' : json_encode($this->_content);
                }
                break;
            default:
                $this->_content = call_user_func_array(array($formater, "to_{$this->format}"), array());
        }
        $this->response->body($this->_content);
    }

}

// End Rest
