<?php
/**Memcache封装类(单例模式)
 * 1、获取资源: $mem = DbMemcache::getInstance($config),其中$config是数据库配置信息
 * =========config配置信息=========
 * host:服务器
 * port: 端口
 * weight:权重
 * ===============================
 * 2、设置缓存：$return = $mem->set($key, $value, $expireTime=0);其中$key为键值，$value为对应的数据，$expireTime为缓存有效时间
 * 3、同时设置多个缓存：$return = $mem->setMulti($items, $expireTime);其中items是缓存的数组（array(key => value)），$expireTime为缓存有效时间
 * 4、获取缓存数据：$return = $mem->get($key);其中$key是键值
 * 5、获取多个缓存数据：$return = $mem->getMulti($keys);其中$keys是键值数组
 * 6、按条件删除记录：$return = $mem->delete($key)，key为键值
 * 7、清空整个缓存：$return = $mem->clear()
 */
 
class DdRedis { 

    private $redis = null; //redis对象 
	/**
	 * 单件方法实现
	 * @var instance
	 */
	private static $instance; 
    /** 
     * 初始化Redis 
     * 简单处理,
     * @param array $config 
     */ 
    private function __construct($config = array()) {
        $redis_conn = empty($config) ? array('server' => '127.0.0.1', 'port' => '6379') : $config;
        $this->redis = new Redis();
        try {
            $rc = $this->redis->connect($redis_conn['server'], $redis_conn['port']);
            if (!$rc) {
            	throw new Exception('redis connect failed host:'.$redis_conn['server'].' port:'.$redis_conn['port']);
            }
        } catch (RedisException $e) {
		
        }
        return $this->redis;
    }
	
	/**
	 * 析构函数
	 * 关闭连接
	 */
	function __destruct()
    {
		try{
			if(!is_null($this->redis)){
				$this->redis->close();
			}
		}catch(RedisException $e){
		}
    }
     
	/**
	 * 单件方法
	 * @param string $alias
	 * @param boolean $multi 是否多台
	 * @return instance
	 */
	public static function Instance($config) {
		$instanceKey = md5(implode(',',$config));
		if(isset(self::$instance[$instanceKey])) {
			return self::$instance[$instanceKey];
		}
		return self::$instance[$instanceKey] = new self($config);
	}
	
	/**
	 * 更换数据库ID
	 * @param string $DB_number
	 * @return boolean
	 */
	public function selectDB($DB_number)
    {
        try
        {
			if(!is_null($this->redis)){
				if($DB_number == 0){
					return true;
				}
				return $this->redis->select($DB_number);
			}
			
			return false;
        }
        catch (RedisException $e)
        {
            return false;
        }
    }
	
    /** 
     * 设置值 
     * @param string $key KEY名称 
     * @param string|array $value 获取得到的数据 
     * @param int $timeOut 时间 
     */  
    public function set($key, $value, $timeOut = 0) {  
        $value = json_encode($value, TRUE);  
        $retRes = $this->redis->set($key, $value);  
        if ($timeOut > 0){
			$this->redis->setTimeout($key, $timeOut);  
		}
        return $retRes;
    }  
  
    /** 
     * 通过KEY获取数据 
     * @param string $key KEY名称 
     */  
    public function get($key) {  
        $result = $this->redis->get($key);  
        return json_decode($result, TRUE);  
    }  
      
    /** 
     * 删除一条数据 
     * @param string $key KEY名称 
     */  
    public function delete($key) {  
        return $this->redis->delete($key);  
    }  
      
    /** 
     * 清空数据 
     */  
    public function flushAll() {  
        return $this->redis->flushAll();  
    }
      
    /** 
     * 数据入队列 
     * @param string $key KEY名称 
     * @param string|array $value 获取得到的数据 
     * @param bool $right 是否从右边开始入 
     */  
    public function push($key, $value ,$right = true) {  
        $value = json_encode($value);
        return $right ? $this->redis->rPush($key, $value) : $this->redis->lPush($key, $value);  
    }
      
    /** 
     * 数据出队列 
     * @param string $key KEY名称 
     * @param bool $left 是否从左边开始出数据 
     */  
    public function pop($key , $left = true) {  
        $val = $left ? $this->redis->lPop($key) : $this->redis->rPop($key);  
        return json_decode($val,true);  
    }  
      
    /** 
     * 数据自增 
     * @param string $key KEY名称 
     */  
    public function increment($key) {  
        return $this->redis->incr($key);  
    }  
  
    /** 
     * 数据自减 
     * @param string $key KEY名称 
     */  
    public function decrement($key) {  
        return $this->redis->decr($key);  
    }  
      
    /** 
     * key是否存在，存在返回ture 
     * @param string $key KEY名称 
     */  
    public function exists($key) {  
        return $this->redis->exists($key);  
    }
    
    public function close(){
        return $this->redis->close();
    }
	
    public function hkeys($key){
    	return $this->redis->hKeys($key);
    }
	
    public function hset($key, $field, $value){
    	return $this->redis->hSet($key, $field, $value);
    }
	
    public function hget($key, $field){
    	return $this->redis->hGet($key, $field);
    }
	
    public function hdel($key, $field){
    	return $this->redis->hDel($key, $field);
    }
}  