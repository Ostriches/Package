<?php
/**
 * PDO封装类
 * 获取DB资源   : $db = DbPdo::getInstance($config),其中$config是数据库配置信息
 * $res = $db->add($table, $column); //插入单条记录
 * $res = $db->query($sql, $params); //查询多条记录
 * $res = $db->one($sql, $params); //查询单条记录
 * $db->startTrans();   //支持事务，注意:事务对数据表使用的引擎有关系，使用的时候请检查表的引擎
 * $res = $db->exec();//支持直接执行语句/返回PDOStatement对象
 */
class DbPdo {
    /**
     * 单件方法实现
     * @var instance
     */
    private static $_instance = null;
    private $_config = null;
    private $_db = null;//连接资源
    private $_stmt = null;

    const NONE = null;
    const ONE = 'one';
    const ALL = 'all';
    const STMT = 'stmt';

    private function __construct($config) 
    {
        if ( !class_exists('PDO') ) 
        {
            //pdo扩展
            return false;
        }
        $this->_config = $config;
        //服务器
        $type = isset($config['type']) ? $config['type'] : 'mysql';
        $dsn  = isset($config['dsn']) ? $config['dsn'] : 'mysql:host=127.0.0.1;port=3306;dbname=mysql';
        $user = isset($config['username']) ? $config['username'] : 'root';
        $pass = isset($config['password']) ? $config['password'] : '123456';
        $opts = isset($config['opts']) ? $config['opts']->toArray() : array();
        try 
        {
            if ($type == 'oracle')
            {
                putenv('ORACLE_HOME=/usr/lib/oracle/11.1/client64/lib');
            }
            $this->_db = new PDO($dsn, $user, $pass, $opts);
            $this->_db->setAttribute(PDO::ATTR_EMULATE_PREPARES, FALSE);//是否在本地进行参数转义，默认为true
            $this->_db->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
            $this->_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);//错误报告;PDO::ERRMODE_EXCEPTION抛出 exceptions 异常
        } 
        catch (PDOException $e) 
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
            if($this->_stmt)
            {
                $this->_stmt->closeCursor();
            }
            $this->_db = null;
        }
        catch(PDOException $e)
        {
            //pdo关闭失败
            throw new Yaf_Exception('The Close to PDO id failed!［Message:' . $e->getMessage() . ']' ,YAF_ERR_MYPDOINI_FAILED);
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
     * 启动事务
     * @access public
     * @return void
     */
    public function startTrans() 
    {
        try
        {
            if($this->_db)
            {
                $this->_db->setAttribute(PDO::ATTR_AUTOCOMMIT, 0);
                $this->_db->beginTransaction();
            }
        }catch(PDOException $e){
        }
        return ;
    }

    /**
     * 提交事务
     */
    public function commit() 
    {
        try
        {
            if($this->_db)
            {
                $this->_db->commit();
            }
        }
        catch(PDOException $e)
        {
        }
    }

    /**
     * 回滚事务
     */
    public function rollback()
    {
        try
        {
            if($this->_db)
            {
                $this->_db->rollback();
            }
        }
        catch(PDOException $e)
        {
        }
    }

    /**
     * 筛选记录 --绑定变量方法
     * @access public
     * @param mixed $sql
     * @param array $params 变量数组
     * return mixed
     */
    public function query($sql, $params=array()) 
    {
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

    /**
     * 变量对应
     * @param ele 
     */
    private function _mapColumn($ele) 
    {
        return ':' . $ele;
    }

    /**
     * 插入记录
     * @param string $table
     * @param array $column
     */
    public function add($table, $columns) 
    {
        if(!$this->_db)
        {
            return false;
        }
        $keys = array_keys($columns);
        $sql = 'INSERT INTO `' . $table . '` (`' .implode('`, `', $keys) . '`) VALUES (' . implode(', ', array_map(array($this, '_mapColumn'), $keys)) . ')';
        return $this->exec($sql, $columns, self::NONE);
    }

    /**
     * 导入记录 ---暂只支持mysql
     * @param string $table  //表名
     * @param array $column //对应字段名称
     * @param string $infile //对应文件路径
     */
    public function import($table, $infile,$columns = array(),$character = 'utf8',$fields = ',',$lines = '\n')
    {
        if(!$this->_db)
        {
            return false;
        }
        $sql = 'LOAD DATA LOCAL INFILE "' . $infile . '" INTO TABLE ' . $table . 
               ' character set ' . $character . ' fields terminated by "' . $fields .
               '"  lines terminated by "' . $lines . '"';
        if(is_array($columns) && count($columns))
        {
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
    public function exec($sql, $params = array(), $return = DbPdo::NONE, $mode = PDO::FETCH_ASSOC)
    {
        if(!$this->_db || !is_array($params)) 
        {
            return false;
        }
        try 
        {
        	list($sql,$params) = $this->_parseParams($sql,$params);
            //预处理SQL
            $this->_stmt = $this->_db->prepare($sql);
            if(!$this->_stmt) 
            {
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
                        $this->_stmt->bindValue($keyParams, $valueParams, $binType);
                    }
                    $result = $this->_stmt->execute();
                }
                else
                {
                    $result = $this->_stmt->execute($params);
                }
            }
            else
            {
                $result = $this->_stmt->execute();
            }
            if($result === false) 
            {  
                return false;
            }
        } 
        catch (PDOException $e) 
        {
        	return false;
        }
        $this->_stmt->setFetchMode($mode);
        switch($return) 
        {
            case self::ONE:
                $return = $this->_stmt->fetch();
                break;
            case self::ALL:
                $return = $this->_stmt->fetchAll();
                break;
            case self::STMT:
                $return = $this->_stmt;
                break;
            case self::NONE:
            default:
                if($result)
                {
                    $return = $this->_stmt->rowCount();
                }
                else
                {
                    $return = $result;
                }
                break;
        }
        return $return;
    }

    /**
     * params分析
     * @access private
     * @param string $sql
     * @param mixed $params
     * @return array
     */
    private function _parseParams($sql,$params = array())
    {
        if(is_array($params) && count($params))
        {
            foreach ($params as $key => $value) {
                if(is_array($value)){
            		$bindValue = array();
                    if(count($value) === 0)
                    {
                    	//sql参数错误
                    	$error = array(
                    		'query' => $sql,
                    		'param' => $params
                    	);
            			throw new Yaf_Exception('The sql bindparam error!［Message:' . $error . ']' ,YAF_ERR_MYPDOINI_FAILED);
                    }
                    foreach ($value as $k => $val) {
                        $bindKey = $key . '_' . $k;
                        $bindValue[] = ':' . $bindKey;
                        $params[$bindKey] = $val;
                    }
                    unset($params[$key]);
                    $sql = preg_replace("/\(\s{0,}+:$key+\s{0,}\)/",'(' . implode(',', $bindValue) . ')',$sql);
                }

            }
        }
        return array($sql,$params);
    }
}