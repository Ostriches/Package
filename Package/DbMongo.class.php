<?php
/**
 * MongoDB封装类
 * 1、获取资源: $mongo = DbMongo::getInstance($config),其中$config是数据库配置信息
 * =========config配置信息=========
 * username:用户
 * password: 密码
 * hostname:服务器IP
 * port:服务器端口，默认27017
 * database:数据库名称，默认为admin
 * timeout:数据库名称，默认为6000
 * ===============================
 * 2、选择一个数据集合：$coll = $mongo->switchCollection($collection);其中collection为字符集名称
 * 3、插入一条数据：$result = $coll->insert($data,$replace=false);其中data是插入的数据，replace是是否替换库中原有的数据
 * 4、获取自增ID：$pk = $coll->mongoNextId($pk);其中pk是需要自增的key值
 * 5、插入多条：$result = insertAll($dataList,$options=array()) 
 * 6、按条件删除记录：delete($query=array())，query为查询条件
 * 7、清空数据集合：clear()
 * 8、修改数据：update($query,$set)，其中$query为条件；$set为修改内容
 * 9、按条件查询多条数据：select($query=array(), $fields = array(), $orderBy = NULL, $limit = NULL)，其中$query为条件；$fields为返回的字段（自带_id），orderBy为排序字段，limit为返回多少数据
 * 10、按条件查询一条数据：find($query=array(), $fields = array())，其中$query为条件；$fields为返回的字段（自带_id）
 */
class DbMongo
{
    private static $_instance = null; // _instance object
    protected $_mongo = null; // MongoDb Object
    protected $_collection = null; // MongoCollection Object
    protected $_dbName = ''; // dbName
    protected $comparison = array('neq'=>'ne','ne'=>'ne','gt'=>'gt','egt'=>'gte','gte'=>'gte','lt'=>'lt','elt'=>'lte','lte'=>'lte','in'=>'in','not in'=>'nin','nin'=>'nin');
    /**
     * 构造函数 读取数据库配置信息
     * @access public
     * @param array $config 数据库配置数组
     */
    private function __construct($config = '')
    {
        $auth = '';
        $host = array();
        $dbConfig = array(
            'dbms' => 'MongoDB',
            'username' => isset($config['username']) ? $config['username'] : '',
            'password' => isset($config['password']) ? $config['password'] : '',
            'hostname' => isset($config['hostname']) ? $config['hostname'] : '127.0.0.1',
            'port' => isset($config['port']) ? $config['port'] : 27017,
            'database' => isset($config['database']) && $config['database'] !='' ? $config['database'] : 'admin',
            'timeout' => isset($config['timeout']) ? $config['timeout'] : 6000,
        );
        if($dbConfig['username'] && $dbConfig['password'])
        {
            $auth = $dbConfig['username'] . ':' . $dbConfig['password'] . '@';
        }
        $host = 'mongodb://' . $auth . $dbConfig['hostname'] . ':' . $dbConfig['port'] . '/' . $dbConfig['database'];
        try
        {
            $options = array(
                'connectTimeoutMS' => $dbConfig['timeout'],
            );
            $this->_mongo = new MongoClient($host,$options);
            $this->_dbName = $dbConfig['database'];
        }
        catch(MongoConnectionException $e)
        {
        }
    }

    /**
     * 析构函数
     */	
    function __destruct()
    {
        try
        {
            if($this->isConnected())
            {
               $this->_mongo->close(); 
            }
        }
        catch(MongoException $e)
        { 
        }
    }

    /**
     * 判断是否连接成功
     * @collection  string 数据集名称
     */
    public function isConnected()
    {
        if ($this->_mongo)
        {
            return true;
        }else{
            return false;
        }
    }

    /**
     * 单件方法
     * @config array
     * @return $_instance
     */
    public static function getInstance($config) 
    {
        try
        {
            if(!is_null(self::$_instance))
            {
                return self::$_instance;
            }
            return self::$_instance = new self($config);
        }
        catch(Exception $e)
        {
        }
    }

    /**
     * 选择数据集
     * @collection  string 数据集名称
     */
    public function switchCollection($collection)
    {
        try
        {
            $this->_collection = $this->_mongo->selectDB($this->_dbName)->selectCollection($collection);
        }
        catch(MongoConnectionException $e)
        {
        }
    }

    /**
     * 插入记录
     * @access public
     * @param mixed $data 数据
     * @param array $options 参数表达式
     * @param boolean $replace 是否replace
     * @return false | integer
     */
    public function insert($data,$replace = false) 
    {
        try
        {
            $result =  $replace ? $this->_collection->save($data) : $this->_collection->insert($data);
            return $result;
        } 
        catch (MongoCursorException $e) 
        {
        }
    }

    /**
     * 生成下一条记录ID 用于自增非MongoId主键
     * @access public
     * @param string $pk 主键名
     * @return integer
     */
    public function mongoNextId($pk) 
    {
        try
        {
            $result = $this->_collection->find(array(),array($pk=>1))->sort(array($pk=>-1))->limit(1);
            $data = $result->getNext();
            return isset($data[$pk]) ? $data[$pk]+1 : 1;
        } 
        catch (MongoCursorException $e) 
        {
        }
    }

    /**
     * 插入多条记录
     * @access public
     * @param array $dataList 数据
     * @param array $options 参数表达式
     * @return bool
     */
    public function insertAll($dataList,$options = array()) 
    {
        try
        {
            $result = $this->_collection->batchInsert($dataList);
            return $result;
        } 
        catch (MongoCursorException $e) 
        {
        }
    }

    /**
     * 删除记录
     * @access public
     * @param array $options 表达式
     * @return false | integer
     */
    public function delete($query = array()) 
    {
        $query = $this->_parseWhere($query);
        try{
            $result = $this->_collection->remove($query);
            return $result;
        } catch (MongoCursorException $e) 
        {
        }
    }

    /**
     * 清空记录
     * @access public
     * @param array $options 表达式
     * @return false | integer
     */
    public function clear()
    {
        try
        {
            $result = $this->_collection->drop();
            return $result;
        } 
        catch (MongoCursorException $e) 
        {
        }
    }

    /**
     * 更新记录
     * @access public
     * @param mixed $data 数据
     * @param array $options 表达式
     * @return bool
     */
    public function update($query,$set) 
    {
        $query = $this->_parseWhere($query);
        $set = $this->parseSet($set);
        try
        {
            $result = $this->_collection->update($query,$set,array('multiple' => true));
            return $result;
        }
        catch (MongoCursorException $e) 
        {
        }
    }

    /**
     * 查找记录
     * @access public
     * @param array $options 表达式
     * @return iterator
     */
    public function select($query = array(), $fields = array(), $orderBy = NULL, $limit = NULL) 
    {
        try
        {
            $query = $this->_parseWhere($query);
            $field = $this->_parseField($fields);
            $_cursor = $this->_collection->find($query,$field);
            if(!is_null($orderBy)) 
            {
                $order = $this->parseOrder($orderBy);
                $_cursor = $_cursor->sort($order);
            }
            if(!is_null($limit)) 
            {
                list($offset,$length) = $this->parseLimit($limit);
                if(!empty($offset)) 
                {
                    $_cursor = $_cursor->skip(intval($offset));
                }
                $_cursor = $_cursor->limit(intval($length));
            }
            $resultSet = iterator_to_array($_cursor);
            return $resultSet;
        } 
        catch (MongoCursorException $e) 
        {
        }
    }

    /**
     * 查找某个记录
     * @access public
     * @param array $options 表达式
     * @return array
     */
    public function find($query = array(), $fields = array())
    {
        try
        {
            $query = $this->_parseWhere($query);
            $fields = $this->_parseField($fields);
            $result = $this->_collection->findOne($query,$fields);
            return $result;
        } 
        catch (MongoCursorException $e)
        {
        }
    }

    /**
     * 统计记录数
     * @access public
     * @param array $options 表达式
     * @return iterator
     */
    public function count($query = array())
    {
        $query = $this->_parseWhere($query);
        try
        {
            $count = $this->_collection->count($query);
            return $count;
        } 
        catch (MongoCursorException $e)
        {
        }
    } 

    /**
     * field分析
     * @access private
     * @param mixed $fields
     * @return array
     */
    private function _parseField($fields)
    {
        if(empty($fields)) 
        {
            $fields = array();
        }
        if(is_string($fields)) 
        {
            $fields = explode(',',$fields);
        }
        return $fields;
    }
	
    /**
     * where分析
     * @access protected
     * @param mixed $where
     * @return array
     */
    private function _parseWhere($where)
    {
        $query = array();
        foreach ($where as $key=>$val)
        {
            if('_id' != $key && 0===strpos($key,'_')) 
            {
                // 解析特殊条件表达式
                $query = $this->_parseSpecialWhere($key,$val);
            }else
            {
                // 查询字段的安全过滤
                if(!preg_match('/^[A-Z_\|\&\-.a-z0-9]+$/',trim($key)))
                {
                   return '_ERROR_QUERY_' . $key;
                }
                $key = trim($key);
                if(strpos($key,'|')) 
                {
                    $array =  explode('|',$key);
                    $str = array();
                    foreach ($array as $k)
                    {
                        $str[] = $this->_parseWhereItem($k,$val);
                    }
                    $query['$or'] = $str;
                }
                elseif(strpos($key,'&'))
                {
                    $array = explode('&',$key);
                    $str = array();
                    foreach ($array as $k)
                    {
                        $str[] = $this->_parseWhereItem($k,$val);
                    }
                    $query = array_merge($query,$str);
                }
                else
                {
                    $str = $this->_parseWhereItem($key,$val);
                    $query = array_merge($query,$str);
                }
            }
        }
        return $query;
    }

    /**
     * 特殊条件分析
     * @access private
     * @param string $key
     * @param mixed $val
     * @return string
     */
    private function _parseSpecialWhere($key,$val) 
    {
        $query   = array();
        switch($key) 
        {
            case '_query': // 字符串模式查询条件
                parse_str($val,$query);
                if(isset($query['_logic']) && strtolower($query['_logic']) == 'or' ) 
                {
                    unset($query['_logic']);
                    $query['$or'] = $query;
                }
                break;
            case '_string':// MongoCode查询
                $query['$where'] = new MongoCode($val);
                break;
        }
        return $query;
    }

    /**
     * where子单元分析
     * @access private
     * @param string $key
     * @param mixed $val
     * @return array
     */
    private function _parseWhereItem($key,$val) 
    {
        $query   = array();
        if(is_array($val)) 
        {
            if(is_string($val[0])) 
            {
                $con = strtolower($val[0]);
                $data_type = isset($val[2]) ? strtolower($val[2]) : '';
                if(in_array($con,array('neq','ne','gt','egt','gte','lt','lte','elt'))) 
                { // 比较运算
                    $k = '$'.$this->comparison[$con];
                    $val[1] = $this->data_type_convert($data_type,$val[1]);                // 数据类型转换
                    $query[$key] = array($k=>$val[1]);
                }
                elseif('like' == $con)
                { // 模糊查询 采用正则方式
                    $val[1] = $this->data_type_convert($data_type,$val[1]);                // 数据类型转换
                    $query[$key] = new MongoRegex('/' . $val[1] . '/');  
                }
                elseif('mod' == $con)
                { // mod 查询
                    $val[1] = $this->data_type_convert($data_type,$val[1]);                // 数据类型转换
                    $query[$key] = array('$mod'=>$val[1]);
                }
                elseif('regex' == $con)
                { // 正则查询
                    $val[1] = $this->data_type_convert($data_type,$val[1]);                // 数据类型转换
                    $query[$key] = new MongoRegex($val[1]);
                }
                elseif(in_array($con,array('in','nin','not in')))
                { // IN NIN 运算
                    $data = is_string($val[1])? explode(',',$val[1]):$val[1];
                    $data = $this->data_type_convert($data_type,$data);                    // 数据类型转换
                    $k = '$' . $this->comparison[$con];
                    $query[$key] = array($k=>$data);
                }
                elseif('all' == $con)
                { // 满足所有指定条件
                    $data = is_string($val[1])? explode(',',$val[1]):$val[1];
                    $data = $this->data_type_convert($data_type,$data);                    // 数据类型转换
                    $query[$key] = array('$all'=>$data);
                }
                elseif('between' == $con)
                { // BETWEEN运算
                    $data = is_string($val[1])? explode(',',$val[1]):$val[1];
                    $data = $this->data_type_convert($data_type,$data);                    // 数据类型转换
                    $query[$key] = array('$gte'=>$data[0],'$lte'=>$data[1]);
                }
                elseif('not between' == $con)
                {
                    $data = is_string($val[1])? explode(',',$val[1]):$val[1];
                    $data = $this->data_type_convert($data_type,$data);                    // 数据类型转换
                    $query[$key] = array('$lt'=>$data[0],'$gt'=>$data[1]);
                }
                elseif('exp' == $con)
                { // 表达式查询
                    $query['$where'] = new MongoCode($val[1]);
                }
                elseif('exists' == $con)
                { // 字段是否存在
                    $query[$key] = array('$exists'=>(bool)$val[1]);
                }
                elseif('size' == $con)
                { // 限制属性大小
                    $query[$key] = array('$size'=>intval($val[1]));
                }
                elseif('type' == $con)
                { // 限制字段类型 1 浮点型 2 字符型 3 对象或者MongoDBRef 5 MongoBinData 7 MongoId 8 布尔型 9 MongoDate 10 NULL 15 MongoCode 16 32位整型 17 MongoTimestamp 18 MongoInt64 如果是数组的话判断元素的类型
                    $query[$key] = array('$type'=>intval($val[1]));
                }
                else
                {
                    $query[$key] = $val;
                }
                return $query;
            }
        }
        $query[$key]  =  $val;
        return $query;
    }

    /**
     * 数据类型转换
     * @access private
     * @param str  $data_type
     * @param mixed  $target
     * @return mixed
     */
    private function data_type_convert($data_type,$target)
    {
        switch($data_type){
            case 'int':
                if(is_array($target))
                {
                    foreach($target as $key => $val)
                    {
                        $target[$key] = intval($val);
                    }
                }
                else
                {
                    $target = intval($target);
                }
                break;
            case 'str':
                if(is_array($target))
                {
                    foreach($target as $key => $val)
                    {
                        $target[$key] = strval($val);
                    }
                }
                else
                {
                    $target = strval($target);
                }
                break;
            case 'float':
                if(is_array($target))
                {
                    foreach($target as $key => $val)
                    {
                        $target[$key] = floatval($val);
                    }
                }
                else
                {
                    $target = floatval($target);
                }
                break;
            default:
                break;
        }
        return $target;
    }

    /**
     * order分析
     * @access private
     * @param mixed $order
     * @return array
     */
    private function parseOrder($order) 
    {
        if(is_string($order)) 
        {
            $array = explode(',',$order);
            $order = array();
            foreach ($array as $key=>$val)
            {
                $arr = explode(' ',trim($val));
                if(isset($arr[1])) 
                {
                    $arr[1] = $arr[1]=='asc'?1:-1;
                }else
                {
                    $arr[1] = 1;
                }
                $order[$arr[0]] = $arr[1];
            }
        }
        return $order;
    }

    /**
     * limit分析
     * @access private
     * @param mixed $limit
     * @return array
     */
    private function parseLimit($limit) {
        if(strpos($limit,','))
        {
            $array = explode(',',$limit);
        }
        else
        {
            $array = array(0,$limit);
        }
        return $array;
    }

    /**
     * set分析
     * @access private
     * @param array $data
     * @return string
     */
    private function parseSet($data)
    {
        $result = array();
        foreach ($data as $key=>$val)
        {
            if(is_array($val)) 
            {
                switch($val[0]) 
                {
                    case 'inc':
                        $result['$inc'][$key]  =  (int)$val[1];
                        break;
                    case 'set':
                    case 'unset':
                    case 'push':
                    case 'pushall':
                    case 'addtoset':
                    case 'pop':
                    case 'pull':
                    case 'pullall':
                        $result['$'.$val[0]][$key] = $val[1];
                        break;
                    default:
                        $result['$set'][$key] = $val;
                }
            }
            else
            {
                $result['$set'][$key] = $val;
            }
        }
        return $result;
    }
}
