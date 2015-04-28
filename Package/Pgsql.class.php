<?php

/**Postgres封装类(单例模式)
 * 1、获取资源: $pg = Pgsql::getInstance($config,$multi),其中$config是数据库配置信息
 * =========config配置信息=========
 * host:服务器
 * port: 端口
 * dbname:数据库名称
 * user:用户
 * password:密码
 * ===============================
 * 2、获取数据：$return = $pg->query($sql, $model=1);其中$sql为sql语句，$model为数据模式
 * 3、执行sql语句：$return = $pg->execute($sql);其中sql是需要执行的语句
 * 4、关闭数据连接：$return = $pg->close()
 */

class Pgsql{

	protected static $_instance = null;
	
	private  $conf;
	private  $conn;
	
	public static function getInstance($dbconfig = array()){
		if(!self::$_instance instanceof self){
			self::$_instance = new self($dbconfig);
		}else if(self::$_instance->conf!=$dbconfig){
			self::$_instance = new self($dbconfig);
		}
		return self::$_instance;
		
	}
	
	/**
     * 私有化的构造函数，根据配置信息创建了到PostGresql数据库的持久连接
     * @param str db_name
     * @return void
     */
	protected  function __construct($dbconfig){
		//加载数据库配置信息
		$this->conf = $dbconfig;
		//连接到postgresql数据库
		try{
			$this->conn = pg_connect("host=".$this->conf['host']." port=".$this->conf['port']." dbname=".$this->conf['dbname']." user=".$this->conf['username']." password=".$this->conf['password']);
			if(!$this->conn){
				throw new Exception('your master is dead!now is backup database');
			}
		}catch(Exception $ee){
			
		}
	}
	
	/**
     * 执行查询，返回关联数组
     * @param str sql
	 * sql语句
     * @return array
	 * 查询成功返回关联数组，失败返回false
     */
	public function query($sql,$model=1){
		$pst = pg_query($this->conn, $sql);
		//如果查询成功	
		if($pst){
			$data=false;
			if($model==1){
				while($row = pg_fetch_assoc($pst)){
					$data[] = $row;
				}
			}else{
				while($row = pg_fetch_array($pst)){
					$data[] = $row;
				}
			}
			return $data;
		}else {
			return false;
		}
	}
	
	/**
     * 执行sql语句，不返回结果集
     * @param str sql
	 * sql语句
     * @return array
     */
	 
	public function execute($sql){
		$r = pg_query($this->conn,$sql);
		if (!$r){
			echo "error";
		}
		return $r;
	}
	
	/**
     * 关闭数据库连接
     */
	public function close(){
		pg_close($this->conn);
	} 

    public function get_connection(){
    	return $this->conn;
    }

}