<?php

defined("SYSPATH") or die("No direct script access.");

abstract class Kohana_Controller_Api_Resource extends Controller_Rest
{

    /**
     * @var string 資料模型名稱
     */
    protected $_model_name = NULL;

    /**
     * @var ORM 資料模型物件
     */
    protected $_model = NULL;

    /**
     * @var array 預備輸出的回應結果
     */
    protected $_responses = array();

    /**
     * @var array 以 GET 方式傳入的資料群
     */
    protected $_get;

    /**
     * @var array 以 POST 方式傳入的資料群
     */
    protected $_post;

    /**
     * @var array 以 PUT 方式傳入的資料群
     */
    protected $_put;

    /**
     * @var array 以 DELETE 方式傳入的資料群
     */
    protected $_delete;

    /**
     * @var array 以路由分析網址後的資料群
     */
    protected $_param;

    /**
     * @var array 以混合資料群
     */
    protected $_request;

    /**
     * @var array Datatable.js 特用
     */
    protected $_DT_RowClass;

    /**
     * @var array Datatable.js 特用
     */
    protected $_DT_RowData;

    /**
     * 前置函式
     */
    public function before()
    {
        parent::before();

        // 取得 REST 資料群
        $this->_get = $this->request->query();
        $this->_post = $this->request->post();
        $this->_put = $this->request->put();
        $this->_delete = $this->request->delete();
        $this->_param = $this->request->param();
        $this->_request = array_merge(
                $this->_param, $this->_get, $this->_post, $this->_put, $this->_delete
        );

        // 接收以 GET 模式改變語系設定
        I18n::lang($this->request->get("lang"));

        //嘗試建立 ORM 模型物件
        $this->_model = ORM::factory($this->_model_name);
    }

    /**
     * 讀取資源
     *
     * @param int $id 資源識別碼
     * @return array json
     */
    public function get_read()
    {
        // 取得欲讀取的資源識別碼
        $id = Arr::get($this->_get, "id", Arr::get($this->_param, "id"));
        $skip = Arr::get($this->_get, "skip", Arr::get($this->_get, 'start'));
        $take = Arr::get($this->_get, "take", Arr::get($this->_get, 'length'));
        $sort = Arr::get($this->_get, "sort", NULL);

        // 讀取之前先清空資型模型
        $this->_model->clear();

        //若有指定資源識別碼僅回傳單一筆資料
        if (is_numeric($id)) {
            $this->before_find();
            $this->_model->where("{$this->_model->object_name()}.id", "=", $id)->find();
            if (!$this->_model->loaded()) {
                return $this->_error_404($id);
            }
            $this->_responses = $this->_model->as_array();
            $this->after_find($this->_model);
        } else {

            // 先計算此資源的資料總數
            $this->_responses["recordsTotal"] = $this->_model->count_all();

            // 再計算此資源的過濾後資料數量
            $this->before_find_all();
            $this->_responses["recordsFiltered"] = $this->_model->count_all();

            // Kohana ORM 只要 count_all() 過後，WHERE 條件會被清空
            // 所以必需再呼叫一次 before_find_all() 來重設條件
            $this->before_find_all();

            // 設定讀取的範圍
            if (intval($take) > 0) {
                $this->_responses["take"] = intval($take);
            } else {
                $this->_responses["take"] = $this->_responses["recordsFiltered"];
            }
            if (intval($skip) > 0) {
                $this->_responses["skip"] = intval($skip);
            } else {
                $this->_responses["skip"] = 0;
            }
            $this->_model->offset($this->_responses["skip"]);
            $this->_model->limit($this->_responses["take"]);

            // 設定排序規則(支持多個 sort=id,-name,title )
            $sorts = explode(',', $sort);
            if (count($sorts)) {
                foreach ($sorts as $sort) {
                    // 排序方向
                    $direction = strpos($sort, "-") === FALSE ? "asc" : "desc";
                    // 欄位名稱
                    $column = trim(trim($sort, "-"));
                    // 排序欄位(需檢查是否有此欄位)
                    if (array_key_exists($column, $this->_model->table_columns())) {
                        $this->_model->order_by("{$this->_model->object_name()}.{$column}", $direction);
                    }
                }
            }

            $result = $this->_model->find_all();
            $this->_responses['data'] = array();
            foreach ($result as $key => $row) {
                $this->_responses['data'][$key] = $row->as_array();
            }
            $this->after_find_all($result);
        }
        return $this->_responses;
    }

    /**
     * 建立資源資料
     *
     * @return array
     */
    public function post_create()
    {
        unset($this->_post['id']);
        try {
            $this->before_create();
            $this->before_save();
            $this->_model->values($this->_post);
            $this->_model->save();
            $this->after_create();
            $this->after_save();
            $this->http_status = 201;
            $this->_get["id"] = $this->_model->id;
            return $this->get_read();
        } catch (ORM_Validation_Exception $exc) {
            return $this->_error_400($exc);
        } catch (Exception $exc) {
            return $this->_error_500($exc);
        }
    }

    /**
     * 更新資源資料
     *
     * @param int $id 資源識別碼
     * @return array json
     */
    public function put_update()
    {
        // 取得欲更新的資源識別碼
        $id = Arr::get($this->_put, "id", Arr::get($this->_get, "id", Arr::get($this->_param, "id")));

        try {
            // 先檢查資源存不存在
            $this->_model->where("{$this->_model->object_name()}.id", "=", $id)->find();
            if (!$this->_model->loaded()) {
                return $this->_error_404($id);
            }
            // 進行更新程序
            $this->before_update();
            $this->before_save();
            $this->_model->values($this->_put);
            $this->_model->save();
            $this->_get["id"] = $this->_model->id;
            $this->after_update();
            $this->after_save();
            $this->http_status = 200;
            return $this->get_read();
        } catch (ORM_Validation_Exception $exc) {
            return $this->_error_400($exc);
        } catch (Exception $exc) {
            return $this->_error_500($exc);
        }
    }

    /**
     * 刪除資源資料(改成也支援多筆了)
     *
     * @param int $id 資源識別碼
     * @return array json
     */
    public function delete_delete()
    {
        // 取得欲刪除的資源識別碼
        $id = Arr::get($this->_delete, "id", Arr::get($this->_get, "id", Arr::get($this->_param, "id")));

        // 追加成可以支援多 id 刪除
        $ids = explode(',', $id);

        if (count($ids)) {
            foreach ($ids as $id) {
                $this->_model->clear();
                $this->_model->where("{$this->_model->object_name()}.id", "=", $id)->find();
                $this->before_delete();
                $this->_model->delete();
                $this->after_delete($id);
            }
        }
        $this->http_status = 204;
        /*
          // 取得欲刪除的資源識別碼
          $id = Arr::get($this->_delete, "id", Arr::get($this->_get, "id", Arr::get($this->_param, "id")));


          try {
          // 先檢查資源存不存在
          $this->_model->where("{$this->_model->object_name()}.id", "=", $id)->find();
          if (!$this->_model->loaded()) {
          return $this->_error_404($id);
          }
          // 進行刪除程序
          $this->before_delete();
          $this->_model->delete();
          $this->after_delete($id);
          $this->http_status = 204;
          } catch (Exception $exc) {
          return $this->_error_500($exc);
          } */
    }

    /**
     * 刪除資源資料(單筆或多筆)
     *
     * @param string $id 資源識別碼
     * @return array json
     */
    public function delete_destroy()
    {
        // 取得欲刪除的資源識別碼
        $id = Arr::get($this->_delete, "id", Arr::get($this->_get, "id", Arr::get($this->_param, "id")));

        // 追加成可以支援多 id 刪除
        $ids = explode(',', $id);

        if (count($ids)) {
            foreach ($ids as $id) {
                $this->_model->clear();
                $this->_model->where("{$this->_model->object_name()}.id", "=", $id)->find();
                $this->before_delete();
                $this->_model->delete();
                $this->after_delete($id);
            }
        }
        $this->http_status = 204;
    }

    // #########################################################################
    // 錯誤處理與 HTTP STATUS 代碼參考
    //
    // 200 完成 - 查詢完成，要求的作業已正確的執行，通常在完成 GET、PUT、PATCH 請求時顯示
    // 201 完成 - 新增成功，通常在完成 POST 請求時顯示，回應新建的資料
    // 204 完成 - 異動成功，通常在完成 DELETE 請求時顯示
    // 304 完成 - 查詢的資料沒有異動，請使用快取的資料即可
    // 400 錯誤請求 - 通常發生在資料新增(POST)、更新(PUT)時，所輸入欄位資料不正確(驗證失敗)，或傳送的參數格式、類型不正確。
    // 401 尚未驗證 - 使用者尚未登入或提供有效 API 驗證金鑰。
    // 403 權限不足 - 使用者雖然已經登入，但擁有的權限不足夠執行要求的作業。通常是權限不足查詢(GET)
    // 404 資料不存在 - 不正確的URL，或是欲查詢(GET)、更新(PUT)、刪除(DELETE)的資料識別碼(ID)不存在。
    // 405 操作不予許 - 雖然已登入，沒有更新(PUT)、刪除(DELETE)此資源的權限
    // 500 伺服器錯誤 - 伺服器端發生了未知的狀況或錯誤。
    // #########################################################################

    /**
     * 400 錯誤請求 - 通常發生在資料新增(POST)、更新(PUT)時，所輸入欄位資料不正確(驗證失敗)，或傳送的參數格式、類型不正確。
     */
    protected function _error_400($exc = NULL)
    {
        $this->http_status = 400;
        // 取得欄位資料無效的錯誤訊息
        $field_errors = array();
        foreach ($exc->errors($this->_model_name) as $field => $error) {
            $field_errors[$field] = $error;
        }
        return array(
            "error" => array(
                'message' => 'Some field data invaild',
                'fields' => $field_errors,
            )
        );
    }

    /**
     * 404 資料不存在 - 不正確的URL，或是欲查詢(GET)、更新(PUT)、刪除(DELETE)的資料識別碼(ID)不存在。
     */
    protected function _error_404($id = NULL)
    {
        $this->http_status = 404;
        return array(
            "error" => array(
                "message" => "id `{$id}` not found",
            )
        );
    }

    /**
     * 500 伺服器錯誤 - 伺服器端發生了未知的狀況或錯誤。
     */
    protected function _error_500($exc)
    {
        $this->http_status = 500;
        return array(
            "error" => array(
                "message" => $exc->getMessage(),
            )
        );
    }

    // #########################################################################
    //     別名函式
    // #########################################################################
    public function get_index()
    {
        return $this->get_read();
    }

    public function post_index()
    {
        return $this->post_create();
    }

    public function put_index()
    {
        return $this->put_update();
    }

    public function delete_index()
    {
        return $this->delete_delete();
    }

    // #########################################################################
    //     事件函式 (這些函式應該依需求被覆寫)
    // #########################################################################
    protected function before_find()
    {

    }

    protected function after_find()
    {
        $this->_responses["DT_RowId"] = "row_{$this->_model->id}";
        $this->_responses["DT_RowClass"] = "";
        $this->_responses["DT_RowData"] = array();
    }

    protected function before_find_all()
    {
        // 欄位資訊
        $table_columns = $this->_model->table_columns();
        // 過濾的條件
        $sort_columns = array();
        foreach (Arr::get($this->_get, "columns", array()) as $key => $column) {
            // 取得排序的欄位名稱
            $sort_column = Arr::path($column, 'data.sort', Arr::path($column, 'name', NULL));
            $sort_column_explode = explode('.', $sort_column);
            if (count($sort_column_explode) == 1) {
                $model_name = "`{$this->_model->table_prefix()}{$this->_model->object_name()}`.`{$sort_column_explode[0]}`";
            } else {
                //$explode_column[0] = $this->_model->table_prefix() . $this->_model->$explode_column[0]->object_name();
                $sort_column_explode[0] = $this->_model->table_prefix() . $sort_column_explode[0];
                $column_name = array_pop($sort_column_explode);
                $model_name = "`" . join(':', $sort_column_explode) . "`.`{$column_name}`";
            }
            $sort_columns[$key] = $model_name;

            // 取得過濾的欄位名稱
            $filter_column = Arr::path($column, 'data.filter', Arr::path($column, 'name', NULL));
            if (empty($filter_column)) {
                continue;
            }
            $filter_column_explode = explode('.', $filter_column);
            if (count($filter_column_explode) == 1) {
                $model_name = "`{$this->_model->table_prefix()}{$this->_model->object_name()}`.`{$filter_column_explode[0]}`";
            } else {
                //$explode_column[0] = $this->_model->table_prefix() . $this->_model->$explode_column[0]->object_name();
                $filter_column_explode[0] = $this->_model->table_prefix() . $filter_column_explode[0];
                $column_name = array_pop($filter_column_explode);
                $model_name = "`" . join(':', $filter_column_explode) . "`.`{$column_name}`";
            }
            if (empty($model_name)) {
                continue;
            }

            // 檢查欲過濾的值
            $searchable = Arr::path($column, 'searchable');
            $search_value = ltrim(rtrim(Arr::path($column, 'search.value', ''), '$'), '^'); // 移除 ^value$ 的頭尾符號
            if (empty($searchable) || empty($search_value)) {
                continue;
            }

            // 過濾的欄位類型
            $data_type = Arr::path($table_columns, "{$filter_column}.data_type");
            // 如果為日期或時間，切開範圍
            if ($data_type == 'datetime' || $data_type == 'date') {
                $date_range = explode('~', $search_value);
                $date_range_start = date('Y-m-d 00:00:00', strtotime(trim($date_range[0])));
                $date_range_end = date('Y-m-d 23:23:59', strtotime(trim($date_range[1])));
                $this->_model->where(DB::expr($model_name), 'BETWEEN', array($date_range_start, $date_range_end));
            } else {
                // 過濾的方式是用 `LIKE` 還是 `=`
                $search_regex = Arr::path($column, 'search.regex', FALSE);
                $search_op = filter_var($search_regex, FILTER_VALIDATE_BOOLEAN) ? "=" : "LIKE";

                // 若用 LIKE ，搜尋值前後加 %
                $search_value = ($search_op == "LIKE") ? "%{$search_value}%" : $search_value;
                $this->_model->where(DB::expr($model_name), $search_op, $search_value);
            }
        }

        // 排序的條件
        foreach (Arr::get($this->_get, "order", array()) as $sort) {
            $column_index = Arr::path($sort, "column", NULL);
            $column_name = Arr::path($sort_columns, $column_index);
            $sort_dir = Arr::path($sort, "dir", 'ASC');
            $this->_model->order_by(DB::expr($column_name), $sort_dir);
        }
    }

    protected function after_find_all(Database_Result $result)
    {
        // 為了支援 datatable.js 所追加的屬性
        $this->_responses['draw'] = Arr::get($this->_get, "draw", 0);
        foreach ($result as $key => $row) {
            $rowdata = Arr::get($this->_responses['data'], $key);
            if ($rowdata) {
                $rowdata["DT_RowId"] = "row_{$row->id}";
                $rowdata["DT_RowClass"] = "";
                $rowdata["DT_RowData"] = array();
                $this->_responses['data'][$key] = $rowdata;
            }
        }
    }

    protected function before_save()
    {

    }

    protected function after_save()
    {

    }

    protected function before_create()
    {

    }

    protected function after_create()
    {
        /**
         * 處理非 through 類型的 has_many
         */
        foreach ($this->_model->has_many() as $alias => $relation) {
            // 處理的資料是否為 through 類型，若是就略過
            if (Arr::get($relation, 'through', '') !== '') {
                continue;
            }
            // 若沒有符合別名的資料傳入，就略過不處理
            if (!array_key_exists($alias, $this->_post)) {
                continue;
            }
            $data = Arr::get($this->_post, $alias, array());
            $model = Arr::get($relation, 'model');
            $orm = ORM::factory($model);
            $fk = Arr::get($relation, 'foreign_key');

            foreach ($data as $row) {
                $action = Arr::get($row, 'action');
                $orm->clear();
                switch ($action) {
                    case 'create':
                        unset($row['id']);
                        $orm->$fk = $this->_model->id;
                        $orm->values($row)->save();
                        break;
                    case 'update':
                        $id = $row['id'];
                        unset($row['id']);
                        $orm->where('id', '=', $id)->find();
                        $orm->$fk = $this->_model->id;
                        $orm->values($row)->save();
                        break;
                    case 'delete':
                        $id = $row['id'];
                        unset($row['id']);
                        $orm->where('id', '=', $id)->find();
                        if ($orm->loaded()) {
                            $orm->delete();
                        }
                        break;
                }
            }
        }
        /**
         * 處理是 through 類型的 has_many
         */
        foreach ($this->_model->has_many() as $alias => $relation) {
            // 只做是 through 類型的 has_many
            if (Arr::get($relation, 'through', '') === '') {
                continue;
            }
            // 欲處理的 has_many 資料
            $data = Arr::get($this->_post, $alias, array());
            $model = Arr::get($relation, 'model');
            $orm = ORM::factory($model);
            // 將資料轉成陣列
            $data = (in_array(gettype($data), array('string', 'integer'))) ? explode(',', $data) : $data;
            foreach ($data as $id) {
                $orm->clear();
                $orm->where('id', '=', $id)->find();
                if ($orm->loaded()) {
                    $this->_model->add($alias, $orm);
                }
            }
        }
        /**
         * 處理 has_one
         */
        foreach ($this->_model->has_one() as $alias => $relation) {
            // 若沒有符合別名的資料傳入，就略過不處理
            if (!array_key_exists($alias, $this->_post)) {
                continue;
            }
            // 處理別名專用的資料
            $data = Arr::get($this->_post, $alias, array());
            // 建立相關資料模型的 ORM
            $model = Arr::get($relation, 'model');
            $orm = ORM::factory($model);
            $fk = Arr::get($relation, 'foreign_key');
            // 新增資料(若沒有指定 id 轉為新增)
            $orm->clear();
            $orm->$fk = $this->_model->id;
            $orm->values($data);
            $orm->save();
        }
    }

    protected function before_update()
    {

    }

    protected function after_update()
    {
        /**
         * 處理非 through 類型的 has_many
         */
        foreach ($this->_model->has_many() as $alias => $relation) {
            // 處理的資料是否為 through 類型，若是就略過
            if (Arr::get($relation, 'through', '') !== '') {
                continue;
            }
            // 若沒有符合別名的資料傳入，就略過不處理
            if (!array_key_exists($alias, $this->_put)) {
                continue;
            }
            $data = Arr::get($this->_put, $alias, array());
            $model = Arr::get($relation, 'model');
            $orm = ORM::factory($model);
            $fk = Arr::get($relation, 'foreign_key');

            foreach ($data as $row) {
                $action = Arr::get($row, 'action');
                $orm->clear();
                switch ($action) {
                    case 'create':
                        unset($row['id']);
                        $orm->$fk = $this->_model->id;
                        $orm->values($row)->save();
                        break;
                    case 'update':
                        $id = $row['id'];
                        unset($row['id']);
                        $orm->where('id', '=', $id)->find();
                        $orm->$fk = $this->_model->id;
                        $orm->values($row)->save();
                        break;
                    case 'delete':
                        $id = $row['id'];
                        unset($row['id']);
                        $orm->where('id', '=', $id)->find();
                        if ($orm->loaded()) {
                            $orm->delete();
                        }
                        break;
                }
            }
        }
        /**
         * 處理是 through 類型的 has_many
         */
        foreach ($this->_model->has_many() as $alias => $relation) {
            // 處理的資料是否為 through 類型，若不是就略過
            if (Arr::get($relation, 'through', '') === '') {
                continue;
            }
            // 若沒有符合別名的資料傳入，就若略過處理
            if (array_key_exists($alias, $this->_put)) {
                $this->_model->remove($alias);
            }
            $data = Arr::get($this->_put, $alias, array());
            $model = Arr::get($relation, 'model');
            $orm = ORM::factory($model);
            // 將資料轉成陣列一併處理
            $data = (in_array(gettype($data), array('string', 'integer'))) ? explode(',', $data) : $data;
            foreach ($data as $id) {
                $orm->clear();
                $orm->where('id', '=', $id)->find();
                if ($orm->loaded()) {
                    $this->_model->add($alias, $orm);
                }
            }
        }

        /**
         * 處理 has_one
         */
        foreach ($this->_model->has_one() as $alias => $relation) {
            // 若沒有符合別名的資料傳入，就略過不處理
            if (!array_key_exists($alias, $this->_put)) {
                continue;
            }
            // 處理別名專用的資料
            $data = Arr::get($this->_put, $alias, array());
            // 建立相關資料模型的 ORM
            $model = Arr::get($relation, 'model');
            $orm = ORM::factory($model);
            $fk = Arr::get($relation, 'foreign_key');
            // 更新資料(若沒有指定 id 轉為新增)
            $orm->clear();
            $orm->where("{$orm->object_name()}.{$fk}", '=', $this->_model->id)->find();
            $orm->values($data);
            $orm->save();
        }
    }

    protected function before_delete()
    {

    }

    protected function after_delete($id = NULL)
    {

    }

}

// Controller/Api/Resource.php
