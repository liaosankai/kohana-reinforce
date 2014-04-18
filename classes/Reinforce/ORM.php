<?php

defined('SYSPATH') OR die('No direct script access.');

/**
 * @license http://www.opensource.org/licenses/BSD-3-Clause    New BSD License
 */
class Reinforce_ORM extends Kohana_ORM {

    /**
     * Creates and returns a new model. 
     * Model name must be passed with its' original casing, e.g.
     * 
     *    $model = ORM::factory('User_Token');
     *
     * @chainable
     * @param   string  $model  Model name
     * @param   mixed   $id     Parameter for find()
     * @return  ORM
     */
    public static function factory($model, $id = NULL)
    {
        $className = 'Model_' . Inflector::words_to_upper(Inflector::underscore($model));
        $modelClass = new ReflectionClass($className);
        return $modelClass->newInstanceArgs(array($id));
    }

    /*
      public function _initialize()
      {
      parent::_initialize();
      $parent_has_one = arr::get(get_class_vars(get_parent_class($this)), '_has_one', array());
      $parent_has_many = arr::get(get_class_vars(get_parent_class($this)), '_has_many', array());
      $parent_belongs_to = arr::get(get_class_vars(get_parent_class($this)), '_belongs_to', array());

      $this->_has_one = $this->_has_one + $parent_has_one;
      $this->_has_many = $this->_has_many + $parent_has_many;
      $this->_belongs_to = $this->_belongs_to + $parent_belongs_to;
      }
     */

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
     * ======== 原生函式強化 ========
     */

    /**
     * 自動建立白名單
     * @param array $values
     * @param array $expected
     */
    public function values(array $values, array $expected = NULL)
    {
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
        parent::values($values, $expected);
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
     * @param  string $alias    Alias of the has_many "through" relationship
     * @param  mixed $far_keys Related model, primary key, or an array of primary keys
     * @return ORM
     */
    public function remove($alias, $far_keys = NULL)
    {
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
     * Initializes validation rules, and labels
     *
     * @return void
     */
    protected function _validation()
    {
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
     * ======== 擴充函式強化 ========
     * 下面的函式為原生 ORM 官方所沒有的
     * 但因為好用或使用頻繁，所以擴充追加
     */

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
     * @param  string $alias    Alias of the has_many "through" relationship
     * @param  mixed $far_keys Related model, primary key, or an array of primary keys
     * @return ORM
     */
    public function remove_except($alias, $far_keys = NULL)
    {
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

    public function where_null($column)
    {
        $this->where($column, "IS", DB::expr('NULL'));
        return $this;
    }

    public function where_not_null($column)
    {
        $this->where($column, "IS NOT", DB::expr('NULL'));
        return $this;
    }

    public function where_in($column, array $values)
    {
        $this->where($column, "IN", $values);
        return $this;
    }

    public function where_not_in($column, array $values)
    {
        $this->where($column, "NOT IN", $values);
        return $this;
    }

    /**
     * Proxy method to Database list_columns.
     *
     * @return array
     */
    public function list_columns()
    {
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
     *
     *
     * @param String $column 欄位
     * @param String $range 範圍字串
     * @param String $delimiter 範圍的分隔符號
     * @return \Reinforce_ORM
     */
    public function where_range($column, $range = null, $delimiter = '~')
    {
        $between = explode($delimiter, $range);

        if (count($between) == 2) {
            $start = trim(Arr::get($between, 0, ''));
            $end = trim(Arr::get($between, 1, ''));

            if (($start == '' or $start == '*') and $end != '') {
                //range: "* ~ 10"
                $this->where($column, '<=', $end);
            } else if ($start != '' and ( $end == '' or $end == '*')) {
                //range: "1 ~ *"
                $this->where($column, '>=', $start);
            } else {
                //range: "1 ~ 10"
                $this->where($column, "BETWEEN", DB::expr("{$start} AND {$end}"));
            }
        }
        return $this;
    }

    /**
     * Increment the value of a column by a given amount.
     *
     * @param  string $column
     * @param  int $amount
     * @return int
     */
    public function increment($column, $amount = 1)
    {
        return $this->adjust($column, $amount, ' + ');
    }

    /**
     * Decrement the value of a column by a given amount.
     *
     * @param  string $column
     * @param  int $amount
     * @return int
     */
    public function decrement($column, $amount = 1)
    {
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
    protected function adjust($column, $amount, $operator)
    {
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
    public function order_by($column, $direction = NULL)
    {
        $direction = in_array($direction, array('desc', 'asc')) ? $direction : 'asc';
        return parent::order_by($column, $direction);
    }

    /**
     * ======== 觀察者事件追加 ========
     * 下面針對原生 ORM 的事些資料操作
     * 註冊了一些事件觸發的行為
     */

    /**
     * Finds and loads a single database row into the object.
     *
     * @chainable
     * @throws Kohana_Exception
     * @return ORM
     */
    public function find()
    {
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
    public function find_all()
    {
        $this->before_find_all();
        Event::trigger('model.find_all.before', array($this));
        $result = parent::find_all();
        $this->after_find_all($result);
        Event::trigger('model.find_all.after', array($result, $this));
        return $result;
    }

    /**
     * Deletes a single record while ignoring relationships.
     *
     * @chainable
     * @throws Kohana_Exception
     * @return ORM
     */
    public function delete()
    {
        $this->before_delete();
        $result = parent::delete();
        $this->after_delete();
        return $result;
    }

    /**
     * Updates or Creates the record depending on loaded()
     *
     * @chainable
     * @param  Validation $validation Validation object
     * @return ORM
     */
    public function save(Validation $validation = NULL)
    {
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
    public function create(Validation $validation = NULL)
    {
        $this->before_create($validation);
        Event::trigger('model.create.before', array($this, $validation));
        $result = parent::create($validation);
        $this->after_create();
        Event::trigger('model.create.after', array($result));
        return $result;
    }

    /**
     * Updates a single record or multiple records
     *
     * @chainable
     * @param  Validation $validation Validation object
     * @throws Kohana_Exception
     * @return ORM
     */
    public function update(Validation $validation = NULL)
    {
        $this->before_update($validation);
        Event::trigger('model.update.before', array($this, $validation));
        $result = parent::update($validation);
        $this->after_update();
        Event::trigger('model.update.after', array($result));
        return $result;
    }

    /**
     * 下面這些函式應該被依照所需進行覆寫
     */
    protected function before_save(Validation $validation = NULL)
    {
        // 如果欄位類型是 mysql 的 set 或 enum 類型，而且欄位資料是陣列方式儲存
        // 將自動轉換成以逗號為分隔的字串
        foreach ($this->_table_columns as $column_name => $column_info) {
            if (in_array($column_info["data_type"], array("set", "enum")) AND is_array($this->$column_name)) {
                $this->$column_name = join(',', $this->$column_name);
            }
        }
    }

    protected function after_save()
    {
        
    }

    protected function before_create(Validation $validation = NULL)
    {
        
    }

    protected function after_create()
    {
        
    }

    protected function before_update(Validation $validation = NULL)
    {
        
    }

    protected function after_update()
    {
        
    }

    protected function before_delete()
    {
        
    }

    protected function after_delete()
    {
        
    }

    protected function before_find()
    {
        
    }

    protected function after_find(ORM $object)
    {
        
    }

    protected function before_find_all()
    {
        
    }

    protected function after_find_all(Database_Result $result)
    {
        
    }

}
