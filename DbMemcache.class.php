<?php

class DbMemcache
{
	//是否开启memcached扩展
	private $memcachedEnable;
	/**
	 * 单件方法实现
	 * @var instance
	 */
	private static $instance;
	
	/**
	 * 连接资源
	 * @var mc
	 */
	private $mc = null;
	
	private function __construct($servers, $multi = false) {
		//扩展判断
		$this->memcachedEnable = extension_loaded('Memcached');

		if($this->memcachedEnable) {
			$this->mc = new Memcached();	
			if($multi) {
				foreach ($servers as $key => $value) {
					$servers[] = array($value['host'], $value['port'], $value['weight']);
				}
				$this->mc->addServers($servers);
			} else {
				$this->mc->addServer($servers['host'], $servers['port'], $servers['weight']);
			} 		
			//环形哈希算法(libketama)
			$this->mc->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
			//设置非阻塞传输模式
			$this->mc->setOption(Memcached::OPT_NO_BLOCK, true);
			//设置连接超时
			$this->mc->setOption(Memcached::OPT_CONNECT_TIMEOUT, 200);
			//设置POLL超时
			$this->mc->setOption(Memcached::OPT_POLL_TIMEOUT, 50);		
		}else{
			$this->mc = new Memcache();			
			$this->mc->addServer($servers['host'], $servers['port']);
		}
		
	}
	
	/**
	 * 单件方法
	 * @param string $alias
	 * @param boolean $multi 是否多台
	 * @return instance|MC
	 */
	public static function Instance($servers, $multi = false) {
		$instanceKey = md5(implode(',',$servers));
		if(isset(self::$instance[$instanceKey])) {
			return self::$instance[$instanceKey];
		}
		return self::$instance[$instanceKey] = new self($servers, $multi = false);
	}
	/**
	 * 析构函数
	 * 关闭连接
	 */
	function __destruct()
    {
		try{
			if(gettype($this->mc) == 'object'){
				if($this->memcachedEnable) {
					$this->mc->quit();
				}else{
					$this->mc->close();
				}
			}
		}catch(MongoException $e){
		}
    }
	
	/**
	 * 设置缓存值(searilize值)
	 * @param string $key
	 * @param mixed $value
	 * @param int $expireTime
	 * @return boolean
	 */
	public function set($key, $value, $expireTime=0) {
		return $this->mc->set($key, $value, $expireTime);
	}
	/**
	 * 一次设置多个值(key => value)键值对
	 * @param array $items
	 * @param int $expireTime
	 * @return boolean
	 */
	public function setMulti($items, $expireTime) {
		return $this->mc->setMulti($items, $expireTime);
	}
	/**
	 * 获取缓存值(unsearilize值)
	 * @param string $key
	 * @return mixed
	 */
	public function get($key) {
		return $this->mc->get($key);
	}
	/**
	 * 获取缓存值(unsearilize值)
	 * @param array $keys
	 * @return mixed
	 */
	public function getMulti($keys) {
		return $this->mc->getMulti($keys);
	}
	/**
	 * 要删除的键值
	 * @param string $key
	 * @return boolean
	 */
	public function delete($key) {
		return $this->mc->delete($key);
	}
	
	/**
     * 清除缓存
     * @access public
     * @return boolen
     */
    public function clear() {
        return $this->mc->flush();
    }

}

?>
