<?php
include_once __DIR__ . "/../vendor/autoload.php";
const PKG_EOF = "\r\n\r\n";
const KEY = '#ivan_is_handsome_body#';

class FakeGameDataGenerator 
{

    private static $gameIds = [
        19 => 'zzfx',
        23 => 'ljxy'
    ];

    private static $gameTables = [
        19 => [
            'log_god_body_op',
            'log_barter'
        ],
        23 => [
            'log_copy_kunlun',
            'log_marry_star'
        ]
    ];


    private static $tableInfo = [
        'log_god_body_op' => [
            [
                'field' => 'agent_id',
                'func' => 'AgentFunc'
            ],
            [
                'field' => 'server_id',
                'func' => 'serverFunc'
            ],
            [
                'field' => 'account_name',
                'func' => 'stringFunc'
            ],
            [
                'field' => 'role_id',
                'func' => 'intFunc'
            ],
            [
                'field' => 'role_level',
                'func' => 'intFunc'
            ],
            [
                'field' => 'is_internal',
                'func' => 'intFunc'
            ],
            [
                'field' => 'platform',
                'func' => 'intFunc'
            ],
            [
                'field' => 'via',
                'func' => 'intFunc'
            ],
            [
                'field' => 'mtime',
                'func' => 'mtimeFunc'
            ],
            [
                'field' => 'op_type',
                'func' => 'intFunc'
            ]
        ],
        'log_barter' => [
            [
                'field' => 'agent_id',
                'func' => 'AgentFunc'
            ],
            [
                'field' => 'server_id',
                'func' => 'serverFunc'
            ],
            [
                'field' => 'account_name',
                'func' => 'stringFunc'
            ],
            [
                'field' => 'role_id',
                'func' => 'intFunc'
            ],
            [
                'field' => 'role_level',
                'func' => 'intFunc'
            ],
            [
                'field' => 'is_internal',
                'func' => 'intFunc'
            ],
            [
                'field' => 'platform',
                'func' => 'intFunc'
            ],
            [
                'field' => 'via',
                'func' => 'intFunc'
            ],
            [
                'field' => 'mtime',
                'func' => 'mtimeFunc'
            ],
            [
                'field' => 'op_type',
                'func' => 'intFunc'
            ],
            [
                'field' => 'barter_id',
                'func' => 'intFunc'
            ],
            [
                'field' => 'barter_goods_id',
                'func' => 'intFunc'
            ],
            [
                'field' => 'request_id',
                'func' => 'intFunc'
            ]
        ],
        'log_copy_kunlun' => [
            [
                'field' => 'agent_id',
                'func' => 'AgentFunc'
            ],
            [
                'field' => 'server_id',
                'func' => 'serverFunc'
            ],
            [
                'field' => 'upf',
                'func' => 'intFunc'
            ],
            [
                'field' => 'role_id',
                'func' => 'intFunc'
            ],
            [
                'field' => 'is_internal',
                'func' => 'intFunc'
            ],
            [
                'field' => 'mtime',
                'func' => 'mtimeFunc'
            ],
            [
                'field' => 'role_level',
                'func' => 'intFunc'
            ],
            [
                'field' => 'regrow',
                'func' => 'intFunc'
            ],
            [
                'field' => 'power',
                'func' => 'intFunc'
            ],
            [
                'field' => 'soul_lv',
                'func' => 'intFunc'
            ]
        ],
        'log_marry_star' => [
            [
                'field' => 'agent_id',
                'func' => 'AgentFunc'
            ],
            [
                'field' => 'server_id',
                'func' => 'serverFunc'
            ],
            [
                'field' => 'upf',
                'func' => 'intFunc'
            ],
            [
                'field' => 'role_id',
                'func' => 'intFunc'
            ],
            [
                'field' => 'is_internal',
                'func' => 'intFunc'
            ],
            [
                'field' => 'mtime',
                'func' => 'mtimeFunc'
            ],
            [
                'field' => 'level',
                'func' => 'intFunc'
            ],
            [
                'field' => 'regrow',
                'func' => 'intFunc'
            ],
            [
                'field' => 'marry_id',
                'func' => 'intFunc'
            ],
            [
                'field' => 'old_lv',
                'func' => 'intFunc'
            ],
            [
                'field' => 'new_lv',
                'func' => 'intFunc'
            ]
        ]
    ];

    private static function intFunc($len = 4)
    {
        $str = "123456789";
        return (int)substr(str_shuffle($str), 0, $len);
    }

    private static function stringFunc($len = 10)
    {
        $str = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        return substr(str_shuffle($str), 0, $len);
    }

    private static function AgentFunc()
    {
        return 1;
    }

    private static function serverFunc()
    {
        return 1;
    }

    private static function mtimeFunc()
    {
        return time();
    }

    public static function returnGameData($dataLen = 6) 
    {
        $gameId = array_rand(self::$gameIds);
        $maxDataTimes = rand(1, $dataLen);
        
        $records = [];
        $gameTablesById = array_flip(self::$gameTables[$gameId]);
        $time = time();
        for($i = 0; $i < $maxDataTimes; $i++) {
            foreach(self::$tableInfo as $tableName => $fieldSet) {
                if (!isset($gameTablesById[$tableName])) {
                    continue;
                }
                if (!isset($records[$tableName])) {
                    $records[$tableName] = [];
                }
                $tmp = [];
                foreach ($fieldSet as $arr) {
                    $tmp[$arr['field']] = call_user_func([__NAMESPACE__ . '\\' . __CLASS__ , $arr['func']]);
                }
                array_push($records[$tableName], $tmp);
            }
        }
        if (empty($records)) {
            echo "生成的数据为空";
            die;
        }

        $data = [
            'project_id' => $gameId,
            'records' => $records
        ];
        $data['sign'] = self::getSign($data, $time);
        $data['time'] = $time;

        return $data;
    }
    private static function getSign($data, $time) {
        $str = KEY . '#' . $time;
        foreach($data as $k => $v) {
            $v = is_array($v) ? json_encode($v) : $v;
            $str .= '#' . $k . '|' . $v;
        }
        return md5($str);
    }
}
$req = [
    'cmd'  => 'receive',
    'data' => FakeGameDataGenerator::returnGameData(50),
    'ext' => [],
];
$req = json_encode($req) . PKG_EOF;

$opt = getopt("c:n:s:f:p:l:");
$socket = new BenchMark\Socket($opt);
$socket->setSentData($req);
$socket->run();