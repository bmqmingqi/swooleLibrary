<?php
/**
 * @filename SwoolebufferPool.php
 * @encoding UTF-8
 * @author sky
 * @email bmqmingqi@qq.com
 * @project jms-jstracking-backend-api
 * @copyright Copyright © 2021 JINGsocial®
 * @datetime 2021-08-25  17:30
 * @version 1.0
 * @Description
 */

namespace swooleLibrary\SwooleCoroutine;
use Closure;
class SwooleBufferPool extends SwooleLibrary
{
    static $bufferTime = 4;//缓冲时间
    static $buffer;//缓冲区
    static $startTime;
    static $switch = true;//是否开启缓冲

    /**
     * 开始缓冲
     * @param array $data
     * @return string
     */
    static function startBuffer(array $data)
    {
        self::$startTime = self::$startTime ?: time();
        $count = count(self::$buffer);
        $leftTime = (self::$startTime + self::$bufferTime) - time();
        self::$buffer[] = $data;
        if (self::$switch) {
            if ($count < self::$coroutineNum && $leftTime > 0) {
                return false;
            }
        }
        return true;
    }

    /**
     * 清理缓冲区
     */
    static function clearBuffer()
    {
        self::$buffer = [];
        self::$startTime = 0;
    }

    /**
     * 多协程业务处理脚本
     * @param array $data
     * @param Closure $closure
     * @param int $coroutineNum  协程数量
     * @param int $bufferTime   缓冲时间
     * @return bool
     */
    static function handle(array $data, Closure $closure, int $coroutineNum=0, int $bufferTime=0)
    {
        self::$coroutineNum=$coroutineNum?:self::$coroutineNum;
        self::$bufferTime=$bufferTime?:self::$bufferTime;
        if (!self::startBuffer($data)) {
            return false;
        }
        if (!empty(self::$buffer)) {
            self::RunGroup(function () use ($closure) {
                foreach (self::$buffer as $key => $value) {
                    try {
                        go(function () use ($closure, $value) {
                            $closure($value);
                        });
                    } catch (\Throwable $e) {
                        echo "error-catch:" . time() . ":message:" . $e->getMessage() . " Parent:" . getmypid() . " file:" . $e->getFile() . " line:" . $e->getLine() . "\r\n";
                    }
                }
            });
        }
        self::clearBuffer();
        return true;
    }
}