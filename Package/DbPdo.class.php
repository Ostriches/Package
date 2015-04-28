<?php
/**Pdo封装类(单例模式)
 * 1、获取DB资源   : $db = DbPdo::Instance($alias);
 * 2、$res = $db->add($table, $column); //插入单条记录
 * 3、$res = $db->query($sql, $params); //查询多条记录
 * 4、$res = $db->one($sql, $params); //查询单条记录
 * 5、$db->startTrans();   //支持事务，注意:事务对数据表使用的引擎有关系，使用的时候请检查表的引擎
 * 6、$res = $db->exec();//支持直接执行语句/返回PDOStatement对象
 * @link
 * @copyright 	2015
 */

final class DbPdo {
	/**
	 * 单件方法实现
	 * @var instance
	 */
	private static $_instance = null;
	private static $config;

	/**
	 * 连接资源
	 * @var db
	 */
	private $db = null;
	private $stmt = null;
	
	const NONE 	= null;
	const ONE 	= 'one';
	const ALL 	= 'all';
	const STMT 	= 'stmt';

	private function __construct($config) {
		$this->config = $config;
		//服务器
		$type = $config['type'];
		$dsn  = $config['dsn'];
		$user = $config['user'];
		$pass = $config['pass'];
		$opts = $config['opts'];
		try {
			if ($type == 'oracle')
			{
				putenv('ORACLE_HOME=/usr/lib/oracle/11.1/client64/lib');
			}
			$this->db = new PDO($dsn, $user, $pass, $opts);
			$this->db->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
		} catch (PDOException $e) {
			$this->logErr(__FUNCTION__ ,$e);
			return false;
		}
	}

	
	function __destruct()
    {
		try{
			if($this->stmt){
				$this->stmt->closeCursor();
			}
		}catch(PDOException $e){
			$this->logErr(__FUNCTION__ ,$e);
		}
    }
	
	public static function Instance($config) {
		if(!self::$_instance instanceof self || self::$config['dsn']!= $config['dsn']){
			self::$_instance = new self($config);
		}else if(self::$config!=$config){
			self::$_instance = new self($config);
		}
		return self::$_instance;
	}
	
	/**
     * 启动事务
     * @access public
     * @return void
     */
    public function startTrans() {
        try{
			if($this->db){
				$this->db->setAttribute(PDO::ATTR_AUTOCOMMIT, 0);
				$this->db->beginTransaction();
			}
		}catch(PDOException $e){
			$this->logErr(__FUNCTION__ ,$e);
		}
        return ;
    }
	
	/**
     * 提交事务
     */
    public function commit() {
		try{
			if($this->db){
				$this->db->commit();
			}
		}catch(PDOException $e){
			$this->logErr(__FUNCTION__ ,$e);
		}
    }
	
	/**
     * 回滚事务
     */
    public function rollback() {
		try{
			if($this->db){
				$this->db->rollback();
			}
		}catch(PDOException $e){
			$this->logErr(__FUNCTION__ ,$e);
		}
    }
	
	/**
     * 筛选记录 --绑定变量方法
     * @access public
     * @param mixed $sql
     * @param array $params 变量数组
     * return mixed
     */
    public function query($sql, $params=array()) {
		return $this->exec($sql, $params, self::ALL);
    }
	
	/**
     * 筛选记录 --绑定变量方法
     * @access public
     * @param mixed $sql
     * @param array $params 变量数组
     * return mixed
     */
	
	public function one($sql, $params=array())
    {
		return $this->exec($sql, $params, self::ONE);
    }
	
	private function mapColumn($ele) {
		return ':'.$ele;
	}
	
	/**
	 * 插入记录
	 * @param unknown_type $table
	 * @param unknown_type $column
	 */
	public function add($table, $columns) {
		if(!$this->db){
			return false;
		}
		$keys = array_keys($columns);
		$sql = 'INSERT INTO `'.$table.'` (`'.implode('`, `', $keys).'`) VALUES ('.implode(', ', array_map(array($this, 'mapColumn'), $keys)).')';
		return $this->exec($sql, $columns, self::NONE);
	}
	
	/**
	 * 导入记录 ---暂只支持mysql
	 * @param string $table  //表名
	 * @param array() $column //对应字段名称
	 * @param string $infile //对应文件路径
	 */
	public function import($table, $infile,$columns = array(),$character = 'utf8',$fields = ',',$lines = '\n') {
		if(!$this->db){
			return false;
		}
		$sql = 'LOAD DATA LOCAL INFILE "' . $infile . '" INTO TABLE ' . $table . 
			' character set ' . $character . ' fields terminated by "' . $fields .
			'"  lines terminated by "' . $lines . '"';
		if(is_array($columns) && count($columns)){
			$sql = $sql . ' (' . implode(',', $columns) . ')';
		}
		return $this->exec($sql);
	}
	
	/**
	 * 执行查询
	 * @param unknown_type $sql
	 * @param array $params
	 * @param unknown_type $result
	 * @param unknown_type $mode
	 */
	public function exec($sql, $params = array(), $return = DbPdo::NONE, $mode = PDO::FETCH_ASSOC) {
		if(!$this->db || !is_array($params)) {
			return false;
		}
		
		try {
			//预处理SQL
			$this->stmt = $this->db->prepare($sql);
			if(!$this->stmt) {
				return false;
			}
			//执行查询
			if (count($params) > 0)
			{
				$key = array_keys($params);
				if (!is_numeric($key[0]) && (substr($key[0], 0, 1) == ':'))
				{
					foreach ($params as $keyParams => $valueParams)
					{
						if (is_bool($valueParams)) 
						{
							$binType =  PDO::PARAM_BOOL;
						} 
						elseif (is_int($valueParams)) 
						{
							$binType =  PDO::PARAM_INT;
						} 
						elseif (is_null($valueParams)) 
						{
							$binType = PDO::PARAM_NULL;
						} 
						else 
						{
							$binType = PDO::PARAM_STR;
						}
						$this->stmt->bindValue($keyParams, $valueParams, $binType);
					}
					$result = $this->stmt->execute();
				}
				else
				{

				  $result = $this->stmt->execute($params);
				}
			}else{
				$result = $this->stmt->execute();
			}
			if($result === false) {
				$this->logErr(__FUNCTION__ , print_r($this->stmt->errorInfo(), true));
				return false;
			}
		} catch (PDOException $e) {
			$this->logErr(__FUNCTION__ ,$e);
			return false;
		}
		
		$this->stmt->setFetchMode($mode);

		switch($return) {
			case self::ONE:
				$return = $this->stmt->fetch();
				break;
			case self::ALL:
				$return = $this->stmt->fetchAll();
				break;
			case self::STMT:
				$return = $this->stmt;
				break;
			case self::NONE:
			default:
				if($result){
					$return = $this->stmt->rowCount();
				}else{
					$return = $result;
				}
				break;
		}
		return $return;
	}
	
	/**
	 * 日志记录
	 * @access private
	 * @return void
	 */
	private function logErr($func,$e,$lines = "\n")
	{
		if(!defined('__LOG__')){
			define('__LOG__', dirname(__FILE__) . '/log');
		}
		$file = __LOG__ . '/pdo' . date('Ymd') . '.log';
		$inPutTime = time();
		if(!is_string($e)){
			$string = '<====' .date('Ymd H:i:s',$inPutTime) . 'function(' . $func . ")Error====>\n" . $e->getMessage() . print_r($e, true);
		}else{
			$string = '<====' .date('Ymd H:i:s',$inPutTime) . 'function(' . $func . ")Error====>\n" . $e;
		}
		if ($fp = fopen($file, 'a'))
		{
			$startTime = microtime(true);
			do
			{
				$canWrite = flock($fp, LOCK_EX);
				if (!$canWrite)
				{
					usleep(1);
				}
			}
			while ((!$canWrite) && ((microtime(true) - $startTime) < 1000));
			if ($canWrite)
			{
				fwrite($fp, $string . $lines);
				flock($fp, LOCK_UN);
			}
			fclose($fp);
		}
	}
}