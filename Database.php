<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * @throws Exception
     */
    public function buildQuery(string $query, array $args = []): string
    {
        if ($query === '') {
            throw new Exception('Query can not be empty string.');
        }

        preg_match_all("/(?:\?\s|\?d|\?f|\?a|\?#)/", $query, $matches);
        $matches = $matches[0];
        for ($i = 0; $i < sizeof($args); $i++) {
            if (is_object($args[$i])) {
                $query = preg_replace("/\{(.*?)\}/", '', $query, 1);
                unset($args[$i]);
            }
        }
        for ($i = 0; $i < sizeof($matches); $i++) {
            $arg = array_shift($args);
            $this->replaceQuery($query, $arg, $matches[$i]);
        }
        return preg_replace(['/{/','/}/'], '', $query);
    }

    /**
     * @throws Exception
     */
    public function replaceQuery(&$query, $arg, $item)
    {
        $pattern = [
            '? ' => true,
            '?d' => true,
            '?f' => true,
            '?a' => false,
            '?#' => false,
        ];
        if ($arg === NULL && !$pattern[$item]) {
            throw new Exception('Argument cannot be a null');
        }
        if (is_array($arg)) {
            $keys = array_keys($arg);
            if ($keys !== array_keys($keys)) {
                $items = [];
                foreach ($arg as $key => $val) {
                    if (is_string($val)) {
                        $items[]    =   '`'.$key.'` = \''.$val.'\'';
                    } elseif (is_null($val)) {
                        $items[]    =   '`'.$key.'` = NULL';
                    } elseif (is_bool($arg)) {
                        $items[]    =   '`'.$key.'` = '. ($arg ? 1 : 0);
                    } else {
                        $items[]    =   '`'.$key.'` = '.$val;
                    }
                }
                $str = join(', ', $items);
            } else {
                $arg = array_map(function($n) {
                    if (is_int($n)) {
                        return $n;
                    }
                    return '`'.$n.'`';
                }, $arg);
                $str = join(', ', $arg);
            }
            $query = preg_replace('/\\'.$item.'/' , $str, $query, 1);
        } elseif (is_bool($arg)) {
            $query = preg_replace('/\\'.$item.'/', ($arg?1:0), $query, 1);
        } elseif (is_integer($arg)) {
            $query = preg_replace('/\\'.$item.'/', (int) $arg, $query, 1);
        } elseif (is_string($arg)) {
            $query = preg_replace('/\\'.$item.'/', ($item === '?#'?'`'.$arg.'`':'\''.$arg.'\' '), $query, 1);
        } elseif (is_null($arg)) {
            $query = preg_replace('/\\'.$item.'/', 'NULL', $query, 1);
        }
    }

    /**
     * @throws Exception
     */
    public function skip(): object
    {
        try {
            return (object) [];
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage());
        }
    }
}
