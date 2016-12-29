<?php
/**
 * 一个简单的模型类,可以实现对数据表的增删改查功能。主要用于处理以下简单场景:1、在数据库中插入一条记录;2、从数据库中查询1条记录,再进行修改;3、从数据库中查询1条记录,进行删除;4、从数据表中查询多条记录;5、从数据表中查询1条记录。
 * Created by PhpStorm.
 * User: haifengkong
 * Date: 2016/10/24
 * Time: 下午2:07
 * @author Haifeng Kong<konghaifeng@cmcm.com>
 */

namespace Redknox;

//孔海峰开发的所有公共应用工具都放在redknox命名空间下，如果为了配合自动加载,可以在应用中建立redknox目录,将孔海峰建立的公共文件放在该目录下
class Model
{
    private $column;
    private $config = array(    //数据库配置,可以在这里写,也可以读取配置文件,配置文件需要与入口文件放在同一层目录
        'DATABASE' => 'mysql',
        'DBNAME' => '',
        'HOST' => '',
        'PORT' => '',
        'USER' => '',
        'PASSWD' => '',
        'PREFIX' => ''
    );

    private $pdo;
    private $pdoStatment;
    private $tableName;
    private $fields = "*";
    private $where = "";
    private $order = "";
    private $limit = "";
    private $errMsg = '';
    private $errNo = 0;
    private $sql;
    private $lastAffectedRowCount = 0;
    private $lastInsertId = 0;

    /**
     * 根据配置文件或者类文件的配置,连接数据库,成功连接后,将传入表名中的字段名写入$this->column数组
     * Model constructor.
     * @param $tableName string 待处理的表名
     * @throws \Exception 如果连接是句酷失败,则抛出错误
     */
    function __construct($tableName)
    {
        if (!isset($tableName)) {
            throw new \Exception("未输入表名。", 10001);
        } else {
            if (is_file(__DIR__ . '/database.php')) {    //合并配置文件
                $this->config = array_merge($this->config, require('database.php'));
            }
            try {
                $this->pdo = new \PDO($this->config['DATABASE'] . ":dbname=" . $this->config['DBNAME'] . ";host=" . $this->config['HOST'] . ";port=" . $this->config['PORT'], $this->config['USER'], $this->config['PASSWD']);
            } catch (\PDOException $e) {
                throw new \Exception('连接数据库失败,请检查数据库配置信息是否正确!', 10000);
            }
            $this->tableName = $this->config['PREFIX'] . $tableName;
            $this->sql = "DESCRIBE " . $this->tableName;
            if ($this->pdoStatment = $this->pdo->prepare($this->sql)) {
                if ($this->pdoStatment->execute()) {
                    $this->column = array_flip($this->pdoStatment->fetchAll(\PDO::FETCH_COLUMN));
                    $this->modelInit();
                } else {
                    $this->syncError($this->pdoStatment);
                    throw new \mysqli_sql_exception('输入的表名不合法!', 10002);
                }
            } else {
                $this->syncError($this->pdo);
            }
        }
    }

    /**
     * 初始化column,将所有字段设置为空字符串
     */
    function modelInit()
    {
        foreach ($this->column as $key => $value) {
            $this->column[$key] = '';
        }
        $this->where = '';
        $this->fields = '*';
        $this->order = '';
        $this->limit = '';
    }

    /**
     * 同步PDO/PDOstatment的错误信息到类错误信息
     * @param $e mixed PDO或者PDOstatment 对象,将其中的错误消息同步到类错误消息中
     */
    private function syncError($e)
    {
        $this->error($e->errorInfo(), $e->errorCode());
    }

    /**
     * 构造错误信息
     * @param $errMsg string 错误信息
     * @param  $errNo int 错误代码
     */
    private function error($errMsg, $errNo = 1)
    {
        $this->errNo = $errNo;
        $this->errMsg = $errMsg;
    }

    /**
     * 析构函数,释放PDO
     */
    function __destruct()
    {
        $this->pdo = null;
    }

    /**
     * 保存 columns中的数据,如果ID有值,则尝试更新,如果ID没有值,则尝试插入
     * @return int/false 如果插入、更新成功,返回插入的或更新的id号,否则返回false
     */
    function save()
    {
        $k = '';
        $v = '';
        $col = $this->column;
        $id = $col['id'];
        unset($col['id']);

        if ($id == '') {    //如果$id值为空,认为是需要插入一调数据
            foreach ($col as $key => $value) {
                $k .= $key . ",";
                $v .= "'" . $value . "',";
            }
            $columns = " (" . $k . ") ";
            $values = " (" . $v . ") ";
            $columns = str_replace(",)", ")", $columns);
            $values = str_replace(",)", ")", $values);
            $this->sql = "INSERT INTO " . $this->tableName . $columns . " VALUES " . $values;
        } else {
            //在这里写插入语句
            foreach ($col as $key => $value) {
                $k .= $key . " = '" . $value . "',";
            }
            $k = str_replace($key . " = '" . $value . "',", $key . " = '" . $value . "' ", $k);

            $this->sql = "UPDATE " . $this->tableName . " SET " . $k . " WHERE id=" . $id;
        }
        if ($this->execSql()) {
            $this->error('执行成功!', 0);
            if ($id == '') {
                $id = $this->lastInsertId;
                $this->column['id'] = $id;
            }
            return $id;
        } else {
            return false;
        }
    }

    /**
     * 执行sql语句,主要用于执行insert,update,和del三类语句。返回值为是否执行成功。如果需要统计影响行数、插入行数、插入ID直接调用PDO功能。
     * @return bool 执行成功与否
     */
    function execSql()
    {
        if ($this->pdoStatment = $this->pdo->prepare($this->sql)) {
            if ($this->pdoStatment->execute()) {
                $this->lastAffectedRowCount = $this->pdoStatment->rowCount();
                $this->lastInsertId = $this->pdo->lastInsertId();
                return true;
            } else {
                $this->syncError($this->pdoStatment);
            }
        } else {
            $this->syncError($this->pdo);
        }
        return false;
    }

    /**
     * 执行传入的sql语句,如果传入的是select语句,可以返回数组格式的查询结果,如果传入的是UPDATE,INSERT,DELETE,可以到$this->lastAffectRow中取得影响行数,用来判断执行结果。select语句需要自己从返回值中判断结果。
     * 因为无法预料执行的语句是哪一类,所以本语句不影响$this->conumn中的值。
     * @param $sql string sql语句
     * @return bool 执行结果是否成功
     */
    function runSql($sql)
    {
        $this->sql = $sql;
        if ($this->execSql()) {
            return $this->pdoStatment->fetchAll();
        }
        return false;
    }

    /**
     * 删除记录,只允许用id做键值删除,如果$this->column['id']有值,也可以直接删除
     * @param int $id 待删除的键值ID
     * @return bool 操作是否成功
     */
    function erase($id = 0)
    {
        if ($id == 0) {
            $id = $this->column['id'];
        } else if (!is_numeric($id)) {  //TODO: 可以增加检测ID是表中的合法ID
            $this->errMsg = '传入的不是记录ID!';
            return false;
        }

        $this->sql = "DELETE FROM " . $this->tableName . " where id=" . $id;
        $re = $this->execSql();
        return $re;
    }

    /**
     * 用这个魔术函数取得column中字段的值,如果字段名不在数组中,则返回NULL值
     * @param $name string 查询表的字段名
     * @return mixed/null 如果传入的字段名不在数组中则返回null值
     */
    function __get($name)
    {
        if (isset($this->column[$name])) {
            return $this->column[$name];
        } else {
            if ($name == 'lastAffectedRowCount' || $name == 'lastInsertId') {
                return $this->$name;
            }
            return null;
        }
    }

    /**
     * 用于更新column中的字段值
     * @param $name string 字段名 因为ID为表唯一键值,理论上不允许直接赋值,必须由数据库中读取,或用专门的函数来处理,以判读是否合法
     * @param $value mixed 字段值
     */

    function __set($name, $value)
    {
        if ($name != 'id') {
            if (isset($this->column[$name])) {
                $this->column[$name] = $value;
            }
        }
    }

    /**
     * 设置需要查询的字段
     * @param $fields string 需要查询的字段,语法为SQL语法,字段名用逗号隔开
     * @return self 返回本身 用于链式操作
     */
    function fileds($fields)
    {
        //校验字段是否合法,任一字段名不合法都将执行*操作
        $f = explode(',', $fields);
        foreach ($f as $key) {
            if (!array_key_exists(trim($key), $this->column)) {
                $this->error('查询的列名不属于表字段:' . $key);
                return $this;
            }
        }
        $this->fields = $fields;
        return $this;
    }

    /**
     * 设置sql语句条件
     * @param $where string 查询条件,必须符合sql语法,目前没有校验
     * @return self 返回本身 用于链式操作
     * @todo 判断查询条件是否合法
     */
    function where($where)
    {
        $this->where = " WHERE " . $where;
        return $this;
    }

    /**
     * 设置排序条件
     * @param $order string 排序条件
     * @return $this 返回本身用于链式操作
     */
    function order($order)
    {
        //校验字段是否合法,任一字段名不合法都将执行*操作
        $f = explode(',', $order);
        foreach ($f as $key) {
            if (!array_key_exists(trim($key), $this->column)) {
                $this->error('排序的列名不在查询字段中!' . $key, 13);
                return $this;
            }
        }
        $this->order = " ORDER BY " . $order;
        return $this;
    }

    /**
     * 用iD读取表中唯一的一行。可以用filed()函数设置要查的字段,要查询的表必须以ID为键值
     * @param $id int 记录ID
     * @return bool|mixed 如记录的表没有ID键值,则返回空值。
     */
    function get($id)
    {
        if (isset($this->column['id'])) {
            $this->sql = "select " . $this->fields . " from " . $this->tableName . " where id=" . $id;
            $query = $this->pdo->query($this->sql);
            $result = $query->fetch(\PDO::FETCH_ASSOC);
            if ($result) {
                $this->modelInit();
                foreach ($result as $key => $value) {   //用查到的值更新column表
                    $this->column[$key] = $result[$key];
                }
                return $result;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 根据设置的条件从表中查询数据,与find()函数不同之处在于查询多行数据
     * @return array 结果集
     */

    function select()
    {
        $this->sql = "SELECT " . $this->fields . " FROM " . $this->tableName . $this->where . $this->order;
        $query = $this->pdo->query($this->sql);
        $result = $query->fetchAll(\PDO::FETCH_ASSOC);
        $this->lastAffectedRowCount = count($result);
        $this->modelInit();
        return ($result);
    }

    /**
     * 根据查询条件删除记录。注意为了安全起见,为避免误操作,目前不允许where为空值,导致清空表格的删除。需要清空表格可以加一个 where 1 的条件。
     * @return bool|integer 如果删除命令顺利执行,则返回删除的行数;如果失败,则
     */
    function delete()
    {
        if ($this->where != '') {    //安全起见暂时不允许清空表格
            $this->sql = " DELETE FROM " . $this->tableName . $this->where;
            if ($this->execSql()) {
                return $this->lastAffectedRowCount;
            } else {
                return false;
            }
        } else {
            $this->errNo = 20001;
            $this->errMsg = '必须输入查询条件!';
            return false;
        }
    }

    /**
     * 查询1条信息,将结果存入类的column表,用于更新和删除操作。
     * @return mixed 如查询到相关信息,则返回查询的列表,如没查到,则返回空值
     */

    function find()
    {
        $this->sql = "SELECT " . $this->fields . " FROM " . $this->tableName . $this->where . ' limit 1';
        $query = $this->pdo->query($this->sql);
        $result = $query->fetch(\PDO::FETCH_ASSOC);
        $this->modelInit();
        if ($result) {
            foreach ($result as $key => $value) {   //用查到的值更新column表
                $this->column[$key] = $result[$key];
            }
        }
        return ($result);
    }

    /**
     * 返回错误编码
     * @return int
     */
    function errNo()
    {
        return $this->errNo;
    }

    /**
     * 返回错误编码
     * @return string 错误说明。
     */
    function errMsg()
    {
        return $this->errMsg;
    }

    /**
     * 将column中的值以json格式输出。
     * @return string
     */
    function __toString()
    {
        $bigDiv = "\n==================================================================\n";
        $smallDiv = "\n------------------------------------------------------------------\n";
        $model = '';
        $model .= $bigDiv . "字段:" . $smallDiv;
        foreach ($this->column as $k => $v) {
            $model .= '[' . $k . ']=>' . $v . "\n";
        }
        $model .= $bigDiv . "最后执行的sql:" . $smallDiv . $this->sql;
        $model .= $bigDiv . "最后影响的行数:" . $smallDiv . $this->lastAffectedRowCount;
        $model .= $bigDiv . "最后插入的行号:" . $smallDiv . $this->lastInsertId;
        $model .= $bigDiv;
        return $model;
    }

    /**
     * 可以从$_REQUEST中直接获得字段的值,或者通过传入的数组批量给字段赋值
     * 本函数没有返回值,使用本函数可能产生不可预料的问题,请慎重使用
     * @param $list array|null 可以传入一个数组,给$this->colume赋值
     */
    function create($list = null)
    {
        $this->modelInit();     //清空数据表,避免旧数据的影响
        if ($list) {
            if (is_array($list)) {
                foreach ($list as $key => $value) {
                    if (isset($this->column[$key])) {
                        $this->column[$key] = $value;
                    }
                }
            }
        } else {
            foreach ($this->column as $k => $v) {
                if (isset($_REQUEST[$k])) {
                    $this->column[$k] = $_REQUEST[$k];
                }
            }
        }
    }
}
