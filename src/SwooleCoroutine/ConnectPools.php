<?php
/**
 * @filename ConnectPools.php
 * @encoding UTF-8
 * @author sky
 * @email bmqmingqi@qq.com
 * @project jms-jstracking-backend-api
 * @copyright Copyright © 2021 JINGsocial®
 * @datetime 2021-08-31  10:03
 * @version 1.0
 * @Description
 */

namespace swooleLibrary\SwooleCoroutine;

use Exception;
use Swoole\Coroutine\Channel;

trait ConnectPools
{
    static $connects = [];
    static $maxNum = 50;//最大连接数
    static $originConnect = [];

    /**
     * @param $name
     * @param $connect
     * @param int $num
     * 创建连接池
     */
    static public function setConnectsPool($name, $connect, int $num = 10)
    {

        $num = (self::$maxNum < $num) ? self::$maxNum : $num;
        if (!isset(self::$connects[$name])) {
            self::$originConnect[$name] = $connect;
            self::$connects[$name] = new Channel($num + 1);
            self::$connects[$name]->push($connect);
            while ($num > 1) {
                $num--;
                self::$connects[$name]->push(clone $connect);
            }
        }
    }

    /**
     * @param $name
     * @param string $alive
     * @param int $timeOut
     * @return mixed
     * @throws Exception
     */
    static public function borrow($name, string $alive='alive',int $timeOut = 5)
    {
        if (isset(self::$connects[$name])) {
            $connect = self::$connects[$name]->pop($timeOut);
            if (is_object($connect)) {
                if(method_exists($connect,$alive)){//如果需要自动保活,请实现alive方法检测链接是否可用
                    if(!$connect->$alive()){
                        $connect=clone self::$originConnect[$name];
                    }
                }
                return $connect;
            }
        }
        throw new Exception('Please initialize the connection pool first');
    }

    /**
     * @param $name
     * @param $connect
     * 归还链接
     */
    static public function revert($name, $connect)
    {
        self::$connects[$name]->push($connect);
    }
}