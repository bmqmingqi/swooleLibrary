<?php
/**
 * @filename SwooleLibaryTest.php
 * @encoding UTF-8
 * @author sky
 * @email bmqmingqi@qq.com
 * @project jms-jstracking-backend-api
 * @copyright Copyright © 2021 JINGsocial®
 * @datetime 2021-08-17  13:13
 * @version 1.0
 * @Description
 * 多进程，并行多协程处理框架
 */


use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use phpDocumentor\Reflection\DocBlock\Serializer;
use swooleLibrary\SwooleCoroutine\SwooleLibrary;
use function Swoole\Coroutine\run;
use function Swoole\Coroutine\parallel;
use Swoole\Process;
use Swoole\Table;
use Swoole\Coroutine\WaitGroup;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\DatabaseManager;
use GuzzleHttp\Promise;
use GuzzleHttp\Client;
class SwooleLibaryTest
{
    static $checkTime = 5;//检查间隔时间
    static $maxProcess = 1;//最大进程数据量

    static $coroutineNum = 30;//协程并发数量
    static $table;
    static $startTime;
    static $startMemory;
    static $init = false;//是否已初始化




    /**
     * @throws \Throwable
     * api接调用示例
     */
    static public function test()
    {
        /** GuzzleHttp requestAsync  异步调用示例 */
        self::requestAsync();
        /** Swoole 异步调用示例 */
        self::swooleCoroutineTest();

    }

    /**
     *基于laravel ORM 注入的链接池示例
     */
    public function ConnectionsPoolTest()
    {
        /**
         * 基础队列处理
         */
        $i = 1;
        while ($i < 20000) {
            $i++;
            self::RunGroup(function () {
                $app = app('app');
                $db = $mydb = new DatabaseManager($app, $app['db.factory']);
                self::setConnectsPool('mysql', $mydb, 30);
                $i = 1;
                while ($i < 15) {
                    $i++;
                    self::go(function () use ($i, $db) {
                        $j = 1;
                        $wdb = self::borrow('mysql', 'alive');
                        $rdb = self::borrow('mysql', 'alive');
                        $tt = app(Tt::class);
                        while ($j < 20) {
                            $tt->setConnectionResolver($rdb);
                            $rs = $tt->where(['jing_uuid' => $i])->first();
                            $tt->setConnectionResolver($wdb);
                            $data = self::getData();
                            $tt->save($data);
                        }
                        defer(function () use ($rdb, $wdb) {
                            self::revert('mysql', $rdb);
                            self::revert('mysql', $wdb);
                        });
                    });
                }
            });
        }
    }

    /**
     * 用来测试网络请求的方法
     */
    static function curl()
    {
        $url = 'https://github.com';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        var_dump(false);
    }

    /**
     * @return PDO|void
     *
     */
    static function connect()
    {
        $mysql_conf = array(
            'host' => '172.19.0.2',
            'db' => 'default',
            'db_user' => 'sky',
            'db_pwd' => '123456',
        );
        try {
            $pdo = new PDO("mysql:host=" . $mysql_conf['host'] . ";dbname=" . $mysql_conf['db'], $mysql_conf['db_user'], $mysql_conf['db_pwd']);//创建一个pdo对象
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);    // 设置sql语句查询如果出现问题 就会抛出异常
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        } catch (PDOException $e) {
            die("connect error:" . $e->getMessage());
        }
        return $pdo;
    }

    static function getSql()
    {
        $cid = getmypid();//协程id
        $content = str_random(60);
        $time = time();
        $sql = "insert into jing_tt (cid,content,time)values('$cid','$content','$time')";
//        $sql="select * from tt where id=4";
        echo "$sql\r\n";
        return $sql;
    }

    static function getData()
    {
        $cid = getmypid();//协程id
        $content = str_random(60);
        $time = time();
        $sql = "insert into jing_tt (cid,content,time)values('$cid','$content','$time')";
        return ['cid' => $cid, 'content' => $content, 'time' => $time];
    }

    /**
     * @throws \Throwable
     * GuzzleHttp  批量请求网络接口示例
     */
    static public function requestAsync()
    {
        echo "requetAsync:\r\n";
        SwooleLibrary::before();
        $client = new Client([
            //frontend 20s timeout
            'timeout' => 200.0
        ]);
        for ($i = 1; $i < 300; $i++) {
            $url = 'https://baidu.com';
            $promises[] = $client->requestAsync('POST', $url, ['body' => \json_encode([])]);
        }

        if ($promises) {
            $results = Promise\unwrap($promises);
            foreach ($results as $request) {
                $data[] = $request;
            }
        }
        var_dump("requetAsync:",$data);
        SwooleLibrary::after();
    }

    /**
     * 协程请求网络接口示例
     */
    static public function swooleCoroutineTest()
    {
        echo "协程";
        SwooleLibrary::before();
        $data = [];
        SwooleLibrary::RunGroup(function () use (&$data) {
            $client = new Client([
                'timeout' => 200.0
            ]);
            for ($i = 1; $i < 300; $i++) {
                $url = 'https://baidu.com';
                SwooleLibrary::go(function () use ($client, $url, &$data) {
                    $data[] = $client->request('POST', $url, ['body' => \json_encode([])]);
                });
            }
        });
        var_dump("swooleCoroutineTest",$data);
        SwooleLibrary::after();
    }

}
require '../vendor/autoload.php';
SwooleLibaryTest::test();