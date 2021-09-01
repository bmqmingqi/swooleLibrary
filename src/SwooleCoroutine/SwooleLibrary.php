<?php
/**
 * @filename SwooleLibrary.php
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

namespace swooleLibrary\SwooleCoroutine;

use Closure;
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
class SwooleLibrary
{
    static $checkTime = 5;//检查间隔时间
    static $maxProcess = 1;//最大进程数据量

    static $coroutineNum = 30;//协程并发数量
    static $table;
    static $startTime;
    static $startMemory;
    static $init = false;//是否已初始化
    /**
     * @param Closure $closure
     * @param $obj
     */
    static public function master(Closure $closure, $obj)
    {
        self::init();
        while (true) {
            while (!$obj->count()) {
                echo "无数据开始休眠:" . self::$checkTime . "秒钟！！\r\n";
                sleep(self::$checkTime);
            }
            if (self::$maxProcess > self::get()) {
                self::startProcess($closure);
                self::incr();
            } else {
                echo "当前进程数量:" . self::get() . "\r\n";
                sleep(self::$checkTime);
                self::$init = true;
            }
            self::wait();
        }
    }

    /**
     * @param Closure $closure
     * 动态进程创建及管理方法
     * @param $obj
     */
    static public function DynamicMaster(Closure $closure, $obj)
    {
        self::init();
        while (true) {
            echo "当前进程数量:" . self::get() . "\r\n";
            if (self::$maxProcess > self::get()) {
                $queues = self::getAllQueues();
                foreach ($queues as $item) {
                    if (!self::exist($item)) {
                        if (self::$maxProcess > self::get()) {
                            self::startProcess($closure, $item);
                            self::incr();
                        }
                    }
                }
            } else {
                echo "当前进程数量:" . self::get() . "\r\n";
                sleep(self::$checkTime);
                self::$init = true;
            }
            self::wait();
        }
    }

    /**
     * @param Closure $closure
     * @param $item
     */
    static function startProcess(Closure $closure, $item = '')
    {
        self::before();
        $process = new Process(function () use ($closure, $item) {
            echo PHP_EOL . "启动进程:" . " Parent:" . getmypid() . " nowTime:" . date("Y-m-d H:i:s") . PHP_EOL;
            Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL | SWOOLE_HOOK_CURL]);
            run(
                function () use ($closure, $item) {
                    go(function () use ($closure, $item) {
                        self::set($item, ['name' => getmypid()]);
                        try {
                            $closure();
                        } catch (\Throwable $e) {
                            echo "error-catch:" . time() . ":message:" . $e->getMessage() . " Parent:" . getmypid() . " file:" . $e->getFile() . " line:" . $e->getLine() . "\r\n";
                            sleep(1);//异常终止，进行休眠
                        }
                        defer(function () use ($item) {
                            self::decr();
                            self::del($item);
                            self::after();
                        });
                    });
                }
            );
        });
        $process->start();
    }

    /**
     * @param Closure $closure
     */
    static public function process(Closure $closure)
    {
        parallel(self::$coroutineNum, function () use ($closure) {
            self::dealQueue($closure);
        });
    }

    /**
     * @param Closure $closure
     */
    static public function dealQueue(Closure $closure)
    {
        try {
            $closure();
        } catch (\Throwable $e) {
            echo "error-catch:" . time() . ":message:" . $e->getMessage() . " Parent:" . getmypid() . " file:" . $e->getFile() . " line:" . $e->getLine() . "\r\n";
            sleep(1);//异常终止，进行休眠
        }
    }

    /**
     * 跨进程高速共享缓存
     */
    static public function init()
    {
        self::$table = new Table(1024);
        self::$table->column('coroutineNum', Table::TYPE_INT);
        self::$table->column('name', Table::TYPE_STRING, 64);
        self::$table->create();
    }

    static public function incr($key = 'pf', $column = 'coroutineNum')
    {
        self::$table->incr($key, $column, 1);
    }

    static public function decr($key = 'pf', $column = 'coroutineNum')
    {
        self::$table->decr($key, $column, 1);
    }

    static public function get($key = 'pf', $column = 'coroutineNum')
    {
        return self::$table->get($key, $column);
    }

    static public function set($key, array $value)
    {
        return self::$table->set($key, $value);
    }

    static public function exist($key)
    {
        return self::$table->exist($key);
    }

    static public function del($key)
    {
        return self::$table->del($key);
    }

    /**
     * @return int[]
     */
    static public function getAllQueues()
    {
//        todo  写相关业务
        return [1, 2, 3, 4, 5];
    }

    static public function before()
    {
        self::$startTime = microtime(true);
        self::$startMemory = memory_get_usage(true);
    }

    static public function after()
    {
        $currentMemory = memory_get_usage(true);
        $usedMemory = self::memoryConvert($currentMemory - self::$startMemory);
        echo PHP_EOL . "收尾工作:FINISH TIME:" . (microtime(true) - self::$startTime) . ' Used Memory: ' . $usedMemory . ' Current Memory: ' . self::memoryConvert($currentMemory - 0) . " Parent:" . getmypid() . " nowTime:" . date("Y-m-d H:i:s") . PHP_EOL;
    }

    /**
     * @param $size
     * @return int|string
     */
    static public function memoryConvert($size)
    {
        $unit = array('b', 'kb', 'mb', 'gb', 'tb', 'pb');
        return $size ? @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . ((isset($unit[$i])) ? $unit[$i] : '?') : 0;
    }

    /**
     * 防止死进程
     */
    static public function wait()
    {
        if (self::$init) {
            while ($res = Process::wait()) {
            }
        }
    }

    /**
     * @param Closure $closure
     * 多协程处理方法
     */
    static public function Group(Closure $closure)
    {
        $wg = new WaitGroup();
        $wg->add();
        $closure();
        $wg->wait();
    }

    /**
     * @param Closure $closure
     * 多协程处理方法
     */
    static public function RunGroup(Closure $closure)
    {
        Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL | SWOOLE_HOOK_CURL]);
        run(
            function () use ($closure) {
                $wg = new WaitGroup();
                $wg->add();
                $closure();
                $wg->wait();
            }
        );
    }

    /**
     * @param Closure $closure
     * swoole go方法做了异常捕获处理
     */
    static public function go(Closure $closure)
    {
        try {
            go(function () use ($closure) {
                $closure();
            });
        } catch (\Throwable $e) {
            echo "error-catch:" . time() . ":message:" . $e->getMessage() . " Parent:" . getmypid() . " file:" . $e->getFile() . " line:" . $e->getLine() . "\r\n";
        }
    }

}