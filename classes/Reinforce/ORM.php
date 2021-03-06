<?php

defined('SYSPATH') OR die('No direct script access.');

class Reinforce_ORM extends Kohana_ORM {

    protected $_pivot_relaction;

    /**
     * ※ 修正即便使用了小寫 Model 名稱，在 runtime 時，也會正確轉為 Model 名稱
     *    避免了在大小寫敏感的 Liunx 系統會發生錯誤
     *
     * ※ 增加第三個參數記錄樞紐表
     *
     * Creates and returns a new model.
     * Model name must be passed with its' original casing, e.g.
     *
     *    $model = ORM::factory('User_Token');
     *    $model = ORM::factory('user_token'); ← 現在就算不小寫打成這樣也可以
     *
     * @chainable
     * @param   string $model Model name
     * @param   mixed $id Parameter for find()
     * @return  ORM
     */
    public static function factory($model, $id = NULL, $pivot_relaction = array()) {
        $className = 'Model_' . Inflector::words_to_upper(Inflector::underscore($model));
        $modelClass = new ReflectionClass($className);
        return $modelClass->newInstanceArgs(array($id, $pivot_relaction));
    }

    /**
     * Constructs a new model and loads a record if given
     *
     * @param  mixed $id Parameter for find or object to load
     * @param  mixed $id Parameter for find or object to load
     */
    public function __construct($id = NULL, $pivot_relaction = NULL) {
        $this->_pivot_relaction = $pivot_relaction;
        // 避免關聯表別名因為左右空白而起肖
        foreach (array('_has_one', '_belongs_to', '_has_many') as $relationship) {
            $_clear = array();
            foreach ($this->$relationship as $alias => $relation) {
                $_clear[trim($alias)] = $relation;
            }
            $this->$relationship = $_clear;
        }
        parent::__construct($id);
    }

    /**
     * 使用 $orm-values() 的欄位名單(第二個參數)，如果有設定黑名單
     * 會從白名單再扣除黑名單的欄位，白名單若為空值，ORM 會自動掃描
     * table 所有欄位，然後排除 PK 欄來當做白名單
     *
     * @var string
     */
    protected $_guarded = array(); //黑名單欄位
    protected $_fillable = array(); //白名單欄位

    /**
     * 使用的 DB 連線
     */
    protected $_connection = 'default';

// ======== 原生函式強化 =========================================================
//   這區段的函式是原本 ORM 就有的函式
//   只是將它再做一些強化的補充
// =============================================================================

    /**
     * 強化 table_columns 能夠遞迴取得 with 關聯的欄位資訊
     *
     * @param Boolean $include_with 是否將包含 with 的部分也帶出來
     * @return Array 表單欄位資訊
     */
    public function table_columns($include_with = FALSE) {
        $table_columns = $this->_table_columns;
        if ($include_with) {
            foreach (array_keys($this->_with_applied) as $relation) {
                if ($this->$relation instanceof ORM) {
                    $table_columns[$relation] = $this->$relation->table_columns();
                }
            }
        }
        return $table_columns;
    }

    /**
     * 自動建立白名單
     * @param array $values
     * @param array $expected
     */
    public function values(array $values, array $expected = NULL) {
        if ($expected === NULL) {
            //若沒有設定白名單，使用 table 預設欄位當白名單
            if (empty($this->_fillable)) {
                $this->_fillable = array_keys($this->table_columns());
                //扣除 PK 欄位
                unset($this->_fillable[$this->_primary_key]);
            }
            //將白名單中再扣除黑名單
            $expected = array_diff($this->_fillable, $this->_guarded);
        }
        return parent::values($values, $expected);
    }

    /**
     * Removes a relationship between this model and another.
     *
     *     // Remove a role using a model instance
     *     $model->remove('roles', ORM::factory('role', array('name' => 'login')));
     *     // Remove the role knowing the primary key
     *     $model->remove('roles', 5);
     *     // Remove multiple roles (for example, from checkboxes on a form)
     *     $model->remove('roles', array(1, 2, 3, 4));
     *     // Remove all related roles
     *     $model->remove('roles');
     *
     * @param  string $alias Alias of the has_many "through" relationship
     * @param  mixed $far_keys Related model, primary key, or an array of primary keys
     * @return ORM
     */
    public function remove($alias, $far_keys = NULL) {
        $far_keys = ($far_keys instanceof ORM) ? $far_keys->pk() : $far_keys;

        if (isset($this->_has_many[$alias]['through'])) {
            $table = $this->_has_many[$alias]['through'];
        } else {
            $table = $this->_has_many[$alias]['table'];
        }

        $query = DB::delete($table)
                ->where($this->_has_many[$alias]['foreign_key'], '=', $this->pk());

        if ($far_keys !== NULL) {
            $far_keys = (array) $far_keys;
            if (!empty($far_keys)) {
                // Remove all the relationships in the array
                $query->where($this->_has_many[$alias]['far_key'], 'IN', $far_keys);
            } else {
                return $this;
            }
        }

        $query->execute($this->_db);

        return $this;
    }

    /**
     * ※ 修正如果 rules() 被覆寫後，但沒有回傳陣列時，會發生錯誤的問題
     *
     * Initializes validation rules, and labels
     *
     * @return void
     */
    protected function _validation() {
        // Build the validation object with its rules
        $this->_validation = Validation::factory($this->_object)
                ->bind(':model', $this)
                ->bind(':original_values', $this->_original_values)
                ->bind(':changed', $this->_changed);

        $rules = is_array($this->rules()) ? $this->rules() : array();
        foreach ($rules as $field => $rules) {
            $this->_validation->rules($field, $rules);
        }

        // Use column names by default for labels
        $columns = array_keys($this->_table_columns);

        // Merge user-defined labels
        $labels = array_merge(array_combine($columns, $columns), $this->labels());

        foreach ($labels as $field => $label) {
            $this->_validation->label($field, $label);
        }
    }

    /**
     * ※ 因應 pivot 的功能，所以如果是多對多關聯表的，要記錄樞紐表資訊
     *
     * Handles getting of column
     * Override this method to add custom get behavior
     *
     * @param string $column Column name
     * @throws Kohana_Exception
     * @return mixed
     */
    public function get($column) {
        if (array_key_exists($column, $this->_object)) {
            return (in_array($column, $this->_serialize_columns)) ?
                    $this->_unserialize_value($this->_object[$column]) : $this->_object[$column];
        } else if (isset($this->_related[$column])) {
            // Return related model that has already been fetched
            return $this->_related[$column];
        } else if (isset($this->_belongs_to[$column])) {
            $model = $this->_related($column);

            // Use this model's column and foreign model's primary key
            $col = $model->_object_name . '.' . $model->_primary_key;
            $val = $this->_object[$this->_belongs_to[$column]['foreign_key']];

            // Make sure we don't run WHERE "AUTO_INCREMENT column" = NULL queries. This would
            // return the last inserted record instead of an empty result.
            // See: http://mysql.localhost.net.ar/doc/refman/5.1/en/server-session-variables.html#sysvar_sql_auto_is_null
            if ($val !== NULL) {
                $model->where($col, '=', $val)->find();
            }

            return $this->_related[$column] = $model;
        } else if (isset($this->_has_one[$column])) {
            $model = $this->_related($column);

            // Use this model's primary key value and foreign model's column
            $col = $model->_object_name . '.' . $this->_has_one[$column]['foreign_key'];
            $val = $this->pk();

            $model->where($col, '=', $val)->find();

            return $this->_related[$column] = $model;
        } else if (isset($this->_has_many[$column])) {

            if (isset($this->_has_many[$column]['through'])) {

                $this->_has_many[$column]['foreign_key_id'] = $this->id;

                // Grab has_many "through" relationship table
                $through = $this->_has_many[$column]['through'];

                $model = ORM::factory($this->_has_many[$column]['model'], NULL, $this->_has_many[$column]);

                // Join on through model's target foreign key (far_key) and target model's primary key
                $join_col1 = $through . '.' . $this->_has_many[$column]['far_key'];
                $join_col2 = $model->_object_name . '.' . $model->_primary_key;

                $model->join($through)->on($join_col1, '=', $join_col2);

                // Through table's source foreign key (foreign_key) should be this model's primary key
                $col = $through . '.' . $this->_has_many[$column]['foreign_key'];
                $val = $this->pk();
            } else {
                $model = ORM::factory($this->_has_many[$column]['model']);
                // Simple has_many relationship, search where target model's foreign key is this model's primary key
                $col = $model->_object_name . '.' . $this->_has_many[$column]['foreign_key'];
                $val = $this->pk();
            }

            return $model->where($col, '=', $val);
        } else {
            throw new Kohana_Exception('The :property property does not exist in the :class class', array(':property' => $column, ':class' => get_class($this)));
        }
    }

// ======== 擴充函式強化 =========================================================
// 下面的函式為原生 ORM 官方所沒有的
// 但因為好用或使用頻繁，所以擴充追加
// =============================================================================

    /**
     * 取得資料表單前綴詞
     * @return string
     */
    public function table_prefix() {
        return $this->_db->table_prefix();
    }

    /**
     * 取得/更新樞紐表資料
     *
     * @example
     *
     *   $user = ORM::factory('User');
     *
     *   foreach ($user->roles->find_all() as $role)
     *   {
     *       // 取得屬性
     *       echo Arr::get($role->pivot(), 'created_at');
     *       // 設定屬性
     *       $role->pivot(array(
     *           'created_at' => date()
     *       ));
     *   }
     *
     * 設定屬性會受到黑名單(pivot_guarded)和白名單(pivot_fillable)影響
     *
     *    protected $_has_many = array(
     *      'characters' => array(
     *          'model' => 'Character',
     *          'foreign_key' => 'player_id',
     *          'through' => 'characters_players',
     *          'pivot_guarded' => array(),
     *          'pivot_fillable' => array(),
     *      ),
     *   )
     *
     * @param mix $data 欲更新的資料
     */
    public function pivot($data = NULL) {
        extract($this->_pivot_relaction);
        // 若有設定資料，就更新
        if ($data) {
            $pivot_guarded = (empty($pivot_guarded) || !is_array($pivot_guarded)) ? array() : $pivot_guarded;
            //若沒有設定白名單，使用 table 預設欄位當白名單
            if (empty($pivot_fillable) || !is_array($pivot_fillable)) {
                $pivot_fillable = array_flip(array_keys(Database::instance()->list_columns($through)));
                $pivot_fillable = array_keys($pivot_fillable);
            }
            //將白名單中再扣除黑名單
            $expected = array_diff($pivot_fillable, $pivot_guarded);
            //扣除 FK 欄位
            unset($pivot_fillable[$far_key]);
            unset($pivot_fillable[$foreign_key]);
            $valid_columns = array_flip($expected);
            $valid_data = array_intersect_key($data, $valid_columns);
            // 更新 pivot 資料
            $query = DB::update($through)
                    ->set($valid_data)
                    ->where($far_key, '=', $this->id)
                    ->and_where($foreign_key, '=', $foreign_key_id)
                    ->execute();
            return $query;
        }
        $pivot = DB::select()
                ->from($through)
                ->where($far_key, '=', $this->id)
                ->and_where($foreign_key, '=', $foreign_key_id)
                ->as_object()
                ->execute()
                ->current();
        return (Array) $pivot;
    }

    /**
     * Loads a database result, either as a new record for this model, or as
     * an iterator for multiple rows.
     *
     * @chainable
     * @param  bool $multiple Return an iterator or load a single row
     * @return ORM|Database_Result
     */
    protected function _load_result($multiple = FALSE) {
        $this->_db_builder->from(array($this->_table_name, $this->_object_name));

        if ($multiple === FALSE) {
            // Only fetch 1 record
            $this->_db_builder->limit(1);
        }

        // Select all columns by default
        $this->_db_builder->select_array($this->_build_select());

        if (!isset($this->_db_applied['order_by']) AND ! empty($this->_sorting)) {
            foreach ($this->_sorting as $column => $direction) {
                if (strpos($column, '.') === FALSE) {
                    // Sorting column for use in JOINs
                    $column = $this->_object_name . '.' . $column;
                }

                $this->_db_builder->order_by($column, $direction);
            }
        }

        if ($multiple === TRUE) {
            // Return database iterator casting to this object type
            // echo get_class($this);
            // echo "id:{$this->id}";
            //echo "pivot_id:{$this->_pivot_id}";
            $result = $this->_db_builder->as_object(get_class($this), array(NULL, $this->_pivot_relaction))->execute($this->_db);

            $this->reset();

            return $result;
        } else {
            // Load the result as an associative array
            $result = $this->_db_builder->as_assoc()->execute($this->_db);

            $this->reset();

            if ($result->count() === 1) {
                // Load object values
                $this->_load_values($result->current());
            } else {
                // Clear the object, nothing was found
                $this->clear();
            }

            return $this;
        }
    }

    /**
     * Removes a relationship between this model and another.
     *
     *     // Remove a role using a model instance
     *     $model->remove_except('roles', ORM::factory('role', array('name' => 'login')));
     *     // Remove the role knowing the primary key
     *     $model->remove_except('roles', 5);
     *     // Remove multiple roles (for example, from checkboxes on a form)
     *     $model->remove_except('roles', array(1, 2, 3, 4));
     *     // Remove all related roles
     *     $model->remove('roles');
     *
     * @param  string $alias Alias of the has_many "through" relationship
     * @param  mixed $far_keys Related model, primary key, or an array of primary keys
     * @return ORM
     */
    public function remove_except($alias, $far_keys = NULL) {
        $far_keys = ($far_keys instanceof ORM) ? $far_keys->pk() : $far_keys;

        if (isset($this->_has_many[$alias]['through'])) {
            $table = $this->_has_many[$alias]['through'];
        } else {
            $table = $this->_has_many[$alias]['table'];
        }
        $query = DB::delete($table)
                ->where($this->_has_many[$alias]['foreign_key'], '=', $this->pk());

        if ($far_keys !== NULL) {
            $far_keys = (array) $far_keys;
            if (!empty($far_keys)) {
                // Remove all the relationships in the array
                $query->where($this->_has_many[$alias]['far_key'], 'NOT IN', $far_keys);
            } else {
                return $this;
            }
        }

        $query->execute($this->_db);

        return $this;
    }

    public function where_null($column) {
        $this->where($column, "IS", DB::expr('NULL'));
        return $this;
    }

    public function where_not_null($column) {
        $this->where($column, "IS NOT", DB::expr('NULL'));
        return $this;
    }

    public function where_in($column, array $values) {
        $this->where($column, "IN", $values);
        return $this;
    }

    public function where_not_in($column, array $values) {
        $this->where($column, "NOT IN", $values);
        return $this;
    }

    /**
     * 以字串是否帶有 % 開頭或 ≠ 開頭，自動決定使用 LIKE 或 = 或 !=
     */
    public function where_string_such($column, $string) {
        // 如果欄位名稱中沒有 . ，追加自己的 object name
        if (substr_count($column, '.') === 0) {
            $column = $this->object_name() . '.' . $column;
        }
        $string_arr = array_filter(explode('|', $string));
        $this->and_where_open();
        foreach ($string_arr as $string) {
            if (Text::starts_with($string, '%') || Text::ends_with($string, '%')) {
                $this->or_where($column, 'LIKE', $string);
                //$this->and_where_close();
            } else if (Text::starts_with($string, '≠')) {
                $this->or_where($column, '!=', trim($string, '≠'));
            } else {
                $this->or_where($column, '=', $string);
            }
        }
        $this->and_where_close();
        return $this;
    }

    /**
     * 以字串表達時間型範圍查詢
     *
     * 例如： '2015'             表示 2015-01-01 00:00:00 到 2015-12-31 23:59:59
     * 例如： '2015-03'          表示 2015-03-01 00:00:00 到 2015-03-31 23:59:59
     * 例如： '2015-03-15'       表示 2015-03-15 00:00:00 到 2015-03-15 23:59:59
     * 例如： '2015~2016'        表示 2015-01-01 00:00:00 到 2016-12-31 23:59:59
     * 例如： '2015-03~2015-04'  表示 2014-03-01 00:00:00 到 2015-04-30 23:59:59
     * 例如： '>2015'            表示 >  2015-12-31 23:59:59
     * 例如： '>=2015'           表示 >= 2015-01-01 00:00:00
     * 例如： '<2015'            表示 <  2015-01-01 00:00:00
     * 例如： '<=2015'           表示 <= 2015-12-31 23:59:59
     *
     * @param $query
     * @param $column 查詢欄位
     * @param $range 查詢字串
     * @return $query
     */
    public function where_datetime_range($column, $range) {

        // 如果欄位名稱中沒有 . ，追加自己的 object name
        if (substr_count($column, '.') === 0) {
            $column = $this->object_name() . '.' . $column;
        }
        // 移除無效的字元
        $range_chars = str_split($range);
        foreach ($range_chars as $index => $char) {
            if (!in_array($char, array('>', '=', '<', '.', '!', '~', ' ', '-', ':')) AND ! is_numeric($char)) {
                unset($range_chars[$index]);
            }
        }
        $range = join('', $range_chars);

        // 將多個空白字元取代成一個空白字元，並移除頭尾空白
        $range = trim(preg_replace('/ {2,}/', ' ', str_replace(array("\r", "\n", "\t", "\x0B", "\x0C"), ' ', $range)));

        // 移除放在最後的運算子
        $range = trim(rtrim($range, '>=<'));

        // 是否包含 '>','<','=','~' 運算子
        // 例如將 2015 轉成 2015~2015
        $intersect = array_intersect(str_split($range), array('>', '<', '=', '~'));
        $range = count($intersect) ? $range : "{$range}~{$range}";

        // 將 ~2015 轉成 <=2015
        $range = Text::starts_with($range, '~') ? "<=" . trim($range, "~>=<") : $range;
        // 將 2015~ 轉成 >=2015
        $range = Text::ends_with($range, '~') ? ">=" . trim($range, "~>=<") : $range;

        // 決定最後運算子
        foreach (array('<=', '>=', '<', '>') as $valid_op) {
            if (Text::starts_with($range, $valid_op)) {
                $op = $valid_op;
                break;
            }
            $op = '~';
            $between = array_filter(explode('~', $range));
            $left = Arr::get($between, 0, '');
            $right = Arr::get($between, 1, '');
        }

        // 組合最終查詢
        switch ($op) {
            case '>':
            case '<=':
                $datetime = $this->strToYmdHis(trim($range, '>=<'), true);
                $this->and_where_open();
                $this->where($column, $op, $datetime);
                $this->and_where_close();
                break;
            case '<':
            case '>=':
                $datetime = $this->strToYmdHis(trim($range, '>=<'), false);
                $this->and_where_open();
                $this->where($column, $op, $datetime);
                $this->and_where_close();
                break;
            case '~':
            default:
                $start = $this->strToYmdHis(trim($left, '>=<'), false);
                $end = $this->strToYmdHis(trim($right, '>=<'), true);
                $this->and_where_open();
                $this->where($column, 'BETWEEN', array($start, $end));
                $this->and_where_close();
        }

        return $this;
    }

    /**
     * 以字串表達數字型查詢
     *
     * 例如： '50~100' 表示 50 到 100
     * 例如： '>=50' 表示 50 以上，'50~' 亦同
     * 例如： '<=50' 表示 50 以下，'~50' 亦同
     * 例如： '>50' 超過 50
     * 例如： '<50' 不足 50
     * 例如： '50' 等於 50
     *
     * @param $query
     * @param $column 查詢欄位
     * @param $range 查詢字串
     * @return $query
     */
    public function where_number_range($column, $range) {
        // 如果欄位名稱中沒有 . ，追加自己的 object name
        if (substr_count($column, '.') === 0) {
            $column = $this->object_name() . '.' . $column;
        }
        // 移除無效的字元
        $range_chars = str_split($range);
        foreach ($range_chars as $index => $char) {
            if (!in_array($char, array('>', '=', '<', '.', '!', '~', '-')) and ! is_numeric($char)) {
                unset($range_chars[$index]);
            }
        }
        $range = join('', $range_chars);

        // 移除放在最後的運算子
        $range = rtrim($range, '>=<');

        if (Text::contains($range, '~')) {
            $between = array_filter(explode('~', $range));
            $min = Arr::get($between, 0, '');
            $max = Arr::get($between, 1, '');

            if (is_numeric($min) and is_numeric($max)) {
                // 處理 '1~50' 或 '50~1'
                $min = (min(array($min, $max)));
                $max = (max(array($min, $max)));
                $this->and_where_open();
                $this->where($column, 'BETWEEN', array($min, $max));
                $this->and_where_close();
                return $this;
            } else if (is_numeric($min) and ! is_numeric($max)) {
                // 將 '50~' 轉成 '>=50'
                $range = ">={$min}";
            } else if (!is_numeric($min) and is_numeric($max)) {
                // 將 '~50' 轉成 '<=50'
                $range = "<={$max}";
            }
        }
        // 取得運算符號和數字
        $op = preg_replace('/[0-9]+/', '', $range);
        $number = preg_replace('/[^0-9]+/', '', $range);

        if (in_array($op, array('<', '<=', '>', '>='))) {
            $this->and_where_open();
            $this->where($column, $op, ($number));
            $this->and_where_close();
            return $this;
        } else if (is_numeric($number)) {
            $this->and_where_open();
            $this->where($column, '=', ($number));
            $this->and_where_close();
            return $this;
        }
    }

    /**
     * Proxy method to Database list_columns.
     *
     * @return array
     */
    public function list_columns() {
        $cache_id = 'orm_' . $this->_table_name;
        $cached = Kohana::cache($cache_id);
        if ($cached) {
            return $cached;
        }

        // Proxy to database
        $cached = parent::list_columns();
        Kohana::cache($cache_id, $cached);
        return $cached;
    }

    /**
     * Increment the value of a column by a given amount.
     *
     * @param  string $column
     * @param  int $amount
     * @return int
     */
    public function increment($column, $amount = 1) {
        return $this->adjust($column, $amount, ' + ');
    }

    /**
     * Decrement the value of a column by a given amount.
     *
     * @param  string $column
     * @param  int $amount
     * @return int
     */
    public function decrement($column, $amount = 1) {
        return $this->adjust($column, $amount, ' - ');
    }

    /**
     * Adjust the value of a column up or down by a given amount.
     *
     * @param  string $column
     * @param  int $amount
     * @param  string $operator
     * @return int
     */
    protected function adjust($column, $amount, $operator) {
        $wrapped = $this->_db->escape($column);

        // To make the adjustment to the column, we'll wrap the expression in an
        // Expression instance, which forces the adjustment to be injected into
        // the query as a string instead of bound.
        $value = DB::expr($wrapped . $operator . $amount);

        // Update a single record
        return DB::update($this->_table_name)
                        ->set(array($column => $value))
                        ->where($this->_primary_key, '=', $this->pk())
                        ->execute($this->_db);
    }

    /**
     * 追加對 $direction 的檢查
     */
    public function order_by($column, $direction = NULL) {
        $direction = in_array(strtolower($direction), array('desc', 'asc')) ? $direction : 'asc';
        return parent::order_by($column, $direction);
    }

    /**
     * 追加可以使用字串表達法來設定 order_by
     */
    public function sort_by($sort_by) {
        $sort_segment = array_filter(explode(',', $sort_by));

        foreach ($sort_segment as $sort_str) {
            // 去除排序字串頭尾空白
            $sort_str = trim($sort_str);

            // 從字串結尾判斷排序方向
            $last_char = substr($sort_str, strlen($sort_str) - 1, 1);
            $sort_direction = ($last_char == '-') ? 'desc' : 'asc';

            // 將判斷排序方向的字元從字串中移除
            $sort_column = str_replace('-', '', $sort_str);
            $sort_column = str_replace('+', '', $sort_column);

            // 如果欄位名稱中沒有 . ，追加自己的 object name
            if (substr_count($sort_column, '.') === 0) {
                $sort_column = $this->object_name() . '.' . $sort_column;
            }
            $this->order_by($sort_column, $sort_direction);
        }
        return $this;
    }

    /**
     *  將字串填補成 Y-m-d H:i:s 字串格式
     *
     *  時間格式為 `{年-月-日 * 時:分:秒}`，若有省略的部分，系統將會依下列規則自動補上字串
     *
     *  `年`，若空值最終整個會回傳空字串
     *  `月`，在 {!isLast} 會自動補 01，在 {isLast} 會自動補 12
     *  `日`，在 {!isLast} 會自動補 01，在 {isLast} 會自動補 28|29|30|30 (月的最後一天)
     *  `時`，在 {!isLast} 會自動補 00，在 {isLast} 會自動補 23
     *  `分`，在 {!isLast} 會自動補 00，在 {isLast} 會自動補 59
     *  `秒`，在 {!isLast} 會自動補 00，在 {isLast} 會自動補 59
     *
     * @param  string $datetime 一個需要被填補的時間字串
     * @param  string $isLast 填補該位置的最後(大)值
     * @return string Y-m-d H:i:s 的字串
     */
    private function strToYmdHis($datetime = '', $isLast = false) {
        // 將多個空白取代成一個後，再用空白做切開
        $datetime = preg_replace('/ {2,}/', ' ', str_replace(array("\r", "\n", "\t", "\x0B", "\x0C"), ' ', $datetime));
        $datetime = array_filter(explode(' ', trim($datetime)));

        // 處理日期部分
        $date = Arr::get($datetime, 0, '');
        $Ymd = array_filter(explode('-', $date));
        $Y = Arr::get($Ymd, 0, '');
        $m = Arr::get($Ymd, 1, ($isLast) ? '12' : '01');
        $d = Arr::get($Ymd, 2, ($isLast) ? date("t", strtotime("{$Y}-{$m}-01")) : '01');

        // 處理時間部分
        $time = Arr::get($datetime, 1, '');
        $His = array_filter(explode(':', $time));
        $H = Arr::get($His, 0, ($isLast) ? '23' : '00');
        $i = Arr::get($His, 1, ($isLast) ? '59' : '00');
        $s = Arr::get($His, 2, ($isLast) ? '59' : '00');

        return strlen($Y) ? date('Y-m-d H:i:s', mktime($H, $i, $s, $m, $d, $Y)) : '';
    }

    /**
     * ======== 觀察者事件追加 ========
     * 下面針對原生 ORM 的事些資料操作
     * 註冊了一些事件觸發的行為
     * 注意：會依賴 Event 這個 helper
     */

    /**
     * Finds and loads a single database row into the object.
     *
     * @chainable
     * @throws Kohana_Exception
     * @return ORM
     */
    public function find() {
        $this->before_find();
        Event::trigger('model.find.before', array($this));
        $result = parent::find();
        $this->after_find($result);
        Event::trigger('model.find.after', array($result));
        return $result;
    }

    /**
     * Finds multiple database rows and returns an iterator of the rows found.
     *
     * @throws Kohana_Exception
     * @return Database_Result
     */
    public function find_all() {
        $this->before_find_all();
        Event::trigger('model.find_all.before', array($this));
        $result = parent::find_all();
        $this->after_find_all($result);
        Event::trigger('model.find_all.after', array($result, $this));
        return $result;
    }

    /**
     * Count the number of records in the table.
     *
     * @return integer
     */
    public function count_all() {
        $this->before_count_all();
        Event::trigger('model.count_all.before', array($this));
        $records = parent::count_all();
        $this->after_count_all($records);
        Event::trigger('model.count_all.after', array($records, $this));
        return $records;
    }

    /**
     * Deletes a single record while ignoring relationships.
     *
     * @chainable
     * @throws Kohana_Exception
     * @return ORM
     */
    public function delete() {
        $this->before_delete();
        Event::trigger('model.delete.before', array($this));
        $result = parent::delete();
        $this->after_delete();
        Event::trigger('model.delete.after', array($this));
        return $result;
    }

    /**
     * Updates or Creates the record depending on loaded()
     *
     * @chainable
     * @param  Validation $validation Validation object
     * @return ORM
     */
    public function save(Validation $validation = NULL) {
        $this->before_save($validation);
        Event::trigger('model.save.before', array($this, $validation));
        $result = parent::save($validation);
        $this->after_save();
        Event::trigger('model.save.after', array($this, $validation));
        return $result;
    }

    /**
     * Insert a new object to the database
     * @param  Validation $validation Validation object
     * @throws Kohana_Exception
     * @return ORM
     */
    public function create(Validation $validation = NULL) {
        $this->before_create($validation);
        Event::trigger('model.create.before', array($this, $validation));

        // BEGIN::修復 MySQL 5.6+ 嚴謹判定字串與數字所產生的錯誤
        foreach ($this->_object as $column => $val) {
            if (Arr::path($this->_table_columns, "{$column}.type") == 'int' && $val == '') {
                $this->_object[$column] = intval($val, 10);
            }
            if (Arr::path($this->_table_columns, "{$column}.type") == 'float' && $val == '') {
                $this->_object[$column] = floatval($val);
            }
        }
        // 如果 PK 欄位有設定 auto_increment，需要設成 NULL 而不是空字串。
        if (Arr::path($this->_table_columns, "{$this->_primary_key}.extra") == 'auto_increment') {
            $this->_object[$this->_primary_key] = NULL;
        }
        // END::修復

        $result = parent::create($validation);
        $this->after_create();
        Event::trigger('model.create.after', array($result));
        return $result;
    }

    /**
     * ※ 修復 MySQL 5.6+ 嚴謹判定字串與數字所產生的錯誤
     *
     * Updates a single record or multiple records
     *
     * @chainable
     * @param  Validation $validation Validation object
     * @throws Kohana_Exception
     * @return ORM
     */
    public function update(Validation $validation = NULL) {
        $this->before_update($validation);
        Event::trigger('model.update.before', array($this, $validation));

        // BEGIN::修復 MySQL 5.6+ 嚴謹判定字串與數字所產生的錯誤
        foreach ($this->_object as $column => $val) {
            if (Arr::path($this->_table_columns, "{$column}.type") == 'int' && $val == '') {
                $this->_object[$column] = intval($val, 10);
            }
            if (Arr::path($this->_table_columns, "{$column}.type") == 'float' && $val == '') {
                $this->_object[$column] = floatval($val);
            }
        }
        // END::修復

        $result = parent::update($validation);
        $this->after_update();
        Event::trigger('model.update.after', array($result));
        return $result;
    }

    /**
     * 下面這些函式應該被依照所需進行覆寫
     */
    protected function before_save(Validation $validation = NULL) {
        // 如果欄位資料
        //
            // 1) 欄位類型是 mysql 的 set 或 enum 類型，
        // 2) 欄位資料是陣列方式
        // 3) 不存在 $_serialize_columns 陣列中
        //
        // 就自動轉換成以逗號為分隔的字串
        foreach ($this->_table_columns as $column_name => $column_info) {
            $is_set_or_enum = in_array($column_info["data_type"], array("set", "enum"));
            $is_array = is_array($this->$column_name);
            $is_not_in_serialize_columns = !array_key_exists($column_name, $this->_serialize_columns);
            if ($is_set_or_enum AND $is_array AND $is_not_in_serialize_columns) {
                $this->$column_name = join(',', $this->$column_name);
            }
        }
    }

    protected function after_save() {

    }

    protected function before_create(Validation $validation = NULL) {

    }

    protected function after_create() {

    }

    protected function before_update(Validation $validation = NULL) {

    }

    protected function after_update() {

    }

    protected function before_delete() {

    }

    protected function after_delete() {

    }

    protected function before_find() {

    }

    protected function after_find(ORM $object) {

    }

    protected function before_find_all() {

    }

    protected function after_find_all(Database_Result $result) {

    }

    protected function before_count_all() {

    }

    protected function after_count_all($records) {

    }

    protected function next() {
        if ($this->loaded()) {
            // 下一筆(Next)的 ID
            $next_sql = "SELECT id FROM {$this->table_prefix()}{$this->table_name()} WHERE id = "
                    . "(SELECT MIN(id) FROM {$this->table_prefix()}{$this->table_name()} WHERE id > {$this->id})";
            $next_id = DB::query(Database::SELECT, $next_sql)->execute()->get('id', 0);

            return $this->clear()->reset()->find($next_id);
        }
    }

    protected function prev() {
        if ($this->loaded()) {
            // 上一筆(Prev)的 ID
            $prev_sql = "SELECT id FROM {$this->table_prefix()}{$this->table_name()} WHERE id = "
                    . "(SELECT MAX(id) FROM {$this->table_prefix()}{$this->table_name()} WHERE id < {$this->id})";
            $prev_id = DB::query(Database::SELECT, $prev_sql)->execute()->get('id', 0);

            return $this->clear()->reset()->find($prev_id);
        }
    }

}
