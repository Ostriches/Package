<?php

class DbMemcache
{
	//�Ƿ���memcached��չ
	private $memcachedEnable;
	/**
	 * ��������ʵ��
	 * @var instance
	 */
	private static $instance;
	
	/**
	 * ������Դ
	 * @var mc
	 */
	private $mc = null;
	
	private function __construct($servers, $multi = false) {
		//��չ�ж�
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
			//���ι�ϣ�㷨(libketama)
			$this->mc->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
			//���÷���������ģʽ
			$this->mc->setOption(Memcached::OPT_NO_BLOCK, true);
			//�������ӳ�ʱ
			$this->mc->setOption(Memcached::OPT_CONNECT_TIMEOUT, 200);
			//����POLL��ʱ
			$this->mc->setOption(Memcached::OPT_POLL_TIMEOUT, 50);		
		}else{
			$this->mc = new Memcache();			
			$this->mc->addServer($servers['host'], $servers['port']);
		}
		
	}
	
	/**
	 * ��������
	 * @param string $alias
	 * @param boolean $multi �Ƿ��̨
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
	 * ��������
	 * �ر�����
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
	 * ���û���ֵ(searilizeֵ)
	 * @param string $key
	 * @param mixed $value
	 * @param int $expireTime
	 * @return boolean
	 */
	public function set($key, $value, $expireTime=0) {
		return $this->mc->set($key, $value, $expireTime);
	}
	/**
	 * һ�����ö��ֵ(key => value)��ֵ��
	 * @param array $items
	 * @param int $expireTime
	 * @return boolean
	 */
	public function setMulti($items, $expireTime) {
		return $this->mc->setMulti($items, $expireTime);
	}
	/**
	 * ��ȡ����ֵ(unsearilizeֵ)
	 * @param string $key
	 * @return mixed
	 */
	public function get($key) {
		return $this->mc->get($key);
	}
	/**
	 * ��ȡ����ֵ(unsearilizeֵ)
	 * @param array $keys
	 * @return mixed
	 */
	public function getMulti($keys) {
		return $this->mc->getMulti($keys);
	}
	/**
	 * Ҫɾ���ļ�ֵ
	 * @param string $key
	 * @return boolean
	 */
	public function delete($key) {
		return $this->mc->delete($key);
	}
	
	/**
     * �������
     * @access public
     * @return boolen
     */
    public function clear() {
        return $this->mc->flush();
    }

}

?>
