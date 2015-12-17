<?php
/**
 * Redis封装类(单例模式)
 * 获取资源: $mem = DbRedis::getInstance($config),其中$config是数据库配置信息
 * =========config配置信息=========
 * hostname:服务器
 * port: 端口
 * timeout: 超时时间
 * ===============================
 * 2、设置数据库：$return = $redis->selectDB($DB_number);其中$DB_number为数据库ID
 * 3、设置缓存：$return = $redis->set($key, $value, $timeOut=0);其中$key为键值，$value为对应的数据，$timeOut为缓存有效时间
 * 4、获取缓存数据：$return = $redis->get($key);其中$key是键值
 * 5、按条件删除记录：$return = $redis->delete($key)，key为键值
 * 6、清空整个缓存：$return = $redis->flushAll()
 */
class DbRedis 
{ 
    private $_redis = null; //redis对象 
    private static $_instance = null; // _instance object

    /** 
     * 构造函数 读取数据库配置信息
     * @param array $config 
     */ 
    private function __construct($config = array()) 
    {
        $dbConfig = array(
            'dbms' => 'Redis',
            'hostname' => isset($config['hostname']) ? $config['hostname'] : '127.0.0.1',
            'port' => isset($config['port']) ? $config['port'] : 6379,
            'timeout' => isset($config['timeout']) ? $config['timeout'] : 0,
        );
        $this->_redis = new Redis();
        try 
        {
            $rc = $this->_redis->connect($dbConfig['hostname'], $dbConfig['port'],$dbConfig['timeout']);
            if (!$rc) 
            {
                return false;
            }
            return $this->_redis;
        } 
        catch (RedisException $e) 
        {
        }
    }

    /**
     * 析构函数
     * 关闭连接
     */
    function __destruct()
    {
        try
        {
            if(!is_null($this->_redis))
            {
                $this->_redis->close();
            }
        }
        catch(RedisException $e)
        {  
        }
    }

    /**
     * 单件方法
     * @return instance
     */
    public static function getInstance($config)
    {
        if(!is_null(self::$_instance)) 
        {
            return self::$_instance;
        }
        return self::$_instance = new self($config);
    }

    //==========redis操作相关==========//
    /**
     * 更换数据库ID
     * @param string $database
     * @return boolean
     */
    public function selectDB($database)
    {
        try
        {
            if(!is_null($this->_redis))
            {
                if($database == 0)
                {
                    return true;
                }
                return $this->_redis->select($database);
            }
            return false;
        }
        catch (RedisException $e)
        {
        }
    }
      
    /** 
     * 清空数据 
     */  
    public function flushAll() 
    {  
        return $this->_redis->flushAll();  
    }

    /** 
     * key是否存在，存在返回ture 
     * @param string $key KEY名称 
     */ 
    public function close()
    {
        return $this->_redis->close();
    }
     
    /** 
     * key是否存在，存在返回ture 
     * @param string $key KEY名称 
     */  
    public function exists($key)
    {  
        return $this->_redis->exists($key);  
    }

    //==========key-value操作==========//
    /** 
     * 设置值 
     * @param string $key KEY名称 
     * @param string $value 获取得到的数据 
     * @param int $timeOut 时间 
     */  
    public function set($key, $value, $timeOut = 0) 
    {  
        $retRes = $this->_redis->set($key, $value);  
        if ($timeOut > 0)
        {
            $this->_redis->setTimeout($key, $timeOut);  
        }
        return $retRes;
    }  
    /** 设置值 
     * @param array fields KEY名称 
     * @param int $timeOut 时间 
     */  
    public function mset($fields, $timeOut = 0) 
    {  
        $retRes = $this->_redis->mset($fields);  
        if ($timeOut > 0)
        {
            foreach($fields as $key => $value)
            {
                $this->_redis->setTimeout($key, $timeOut);  
            }
        }
        return $retRes;
    }  
    /** 
     * 数据自增 
     * @param string $key KEY名称 
     */  
    public function increment($key)
    {  
        return $this->_redis->incr($key);  
    }  
  
    /** 
     * 数据自减 
     * @param string $key KEY名称 
     */  
    public function decrement($key)
    {  
        return $this->_redis->decr($key);  
    }  

    /** 
     * 通过KEY获取数据 
     * @param string $key KEY名称 
     */  
    public function get($key) 
    {  
        $result = $this->_redis->get($key);  
        return $result;  
    }

    /** 
     * 通过KEY列表获取数据 
     * @param array $key KEY名称 
     */  
    public function getMultiple($keys) 
    {  
        $result = $this->_redis->getMultiple($keys);  
        return $result;  
    }

    /** 
     * 删除一条数据 
     * @param string $key KEY名称 
     */  
    public function delete($key) 
    {  
        return $this->_redis->delete($key);  
    }

    //==========队列操作==========//  
    /** 
     * 数据入队列 
     * @param string $key KEY名称 
     * @param string $value 获取得到的数据 
     * @param bool $right 是否从右边开始入 
     */  
    public function push($key, $value ,$timeOut = 0,$right = true) 
    {
        $retRes = $right ? $this->_redis->rPush($key, $value) : $this->_redis->lPush($key, $value);  
        if ($timeOut > 0)
        {
            $this->_redis->setTimeout($key, $timeOut);  
        }
        return $retRes;
    }

    /** 
     * 数据出队列 
     * @param string $key KEY名称 
     * @param bool $left 是否从左边开始出数据 
     */  
    public function pop($key , $left = true) 
    {  
        $val = $left ? $this->_redis->lPop($key) : $this->_redis->rPop($key);  
        return $val;  
    }
    /** 
     * 返回名称为key的list有多少个元素 
     * @param string $key KEY名称 
     * @param 元素个数 
     */  
    public function lSize($key) 
    {  
        return $this->_redis->lSize($key);
    }

    //==========Hash操作==========//
    /** 
     * 返回名称为key的hash中所有键
     * @param string $key hash的名称
     * @return 对应的key值
     */ 
    public function hkeys($key)
    {
        return $this->_redis->hKeys($key);
    }

    /** 
     * 向名称为h的hash中添加元素key1—>hello
     * @param string $key hash的名称
     * @param string $field hash中的key
     * @param string $value 对应的value
     * @param int $timeOut 过期时间
     * @return 
     */
    public function hset($key, $field, $value,$timeOut = 0)
    {
        $retRes = $this->_redis->hSet($key, $field, $value);
        if ($timeOut > 0)
        {
            $this->_redis->setTimeout($key, $timeOut);  
        }
        return $retRes;
    }

    /** 
     * 向名称为h的hash中批量添加元素
     * @param string $key hash的名称
     * @param array $field hash中的元素
     * @param int $timeOut 过期时间
     * @return 
     */
    public function hmset($key, $fields,$timeOut = 0)
    {
        $retRes = $this->_redis->hMset($key, $fields);
        if ($timeOut > 0)
        {
            $this->_redis->setTimeout($key, $timeOut);  
        }
        return $retRes;
    }

    /** 
     * 返回名称为h的hash中key1对应的value
     * @param string $key hash的名称
     * @param string $field hash中的key
     * @return 对应的value
     */ 
    public function hget($key, $field)
    {
        return $this->_redis->hGet($key, $field);
    }

    /** 
     * 返回名称为h的hash的所有键和对应值
     * @param string $key hash的名称
     * @return 对应的value
     */ 
    public function hgetall($key)
    {
        return $this->_redis->hGetAll($key);
    }

    /** 
     * 删除名称为h的hash中键为key1的域
     * @param string $key hash的名称
     * @param string $field hash中的key
     * @return 
     */ 
    public function hdel($key, $field)
    {
        return $this->_redis->hDel($key, $field);
    }
}  