<?php

namespace console\controllers;

use common\services\network\TcpService;
use yii\console\Controller;

class JjbController extends Controller
{
    public function actionTcpServer()
    {
        TcpService::getInstance()->server(function($msg = ''){
            if (empty($msg)) {
                return 'unknown cmd.';
            }
            if (strpos($msg, 'mul') !== false) {
                $msgList = explode(' ', $msg);
                if (!is_numeric($msgList[1]) || !is_numeric($msgList[2])) {
                    return 'mul param is invalid.';
                }
                return bcmul($msgList[1], $msgList[2]);
            }
            if (strpos($msg, 'incr') !== false) {
                $msgList = explode(' ', $msg);
                if (!is_numeric($msgList[1])) {
                    return 'incr param is invalid.';
                }
                return bcadd($msgList[1],1);
            }
            if (strpos($msg, 'div') !== false) {
                $msgList = explode(' ', $msg);
                if (!is_numeric($msgList[1]) || !is_numeric($msgList[2])) {
                    return 'div param is invalid.';
                }
                return bcdiv($msgList[1], $msgList[2], 2);
            }
            // 层级数组转化
            if (strpos($msg, 'conv_tree') !== false) {
                $json2Arr = json_decode($this->json, true);
                $treeData = [];
                foreach ($json2Arr as $data) {
                    $dataId = $data['id'];
                    $namePath = $data['namePath'];
                    $namePathArr = explode(',', $namePath);
                    if (empty($namePathArr)) {
                        continue;
                    }
                    foreach ($namePathArr as $k => $nameExplode) {
                        // 数组内不能有相同菜品名称
                        $nameList = array_column($treeData, 'name');
                        if (in_array($nameExplode, $nameList)) {
                            continue;
                        }
                        $randId = $this->generateShortId(hash('md5', uniqid().microtime(true).$dataId))[0];
                        $parentId = '0';
                        if ($k > 0) {
                            $parentName = $namePathArr[$k-1];
                            foreach ($treeData as $tData) {
                                if ($parentName == $tData['name']) {
                                    $parentId = $tData['id'];
                                }
                            }
                        }
                        $treeData[] = [
                            'id' => $randId,
                            'id_path' => '',
                            'is_leaf' => ($k+1) >= count($namePathArr) ? 1 : 2,
                            'level' => $k+1,
                            'name' => $nameExplode,
                            'name_path' => implode(',', array_slice($namePathArr, 0, $k+1)),
                            'parent_id' => $parentId,
                            'children' => [],
                        ];
                    }
                }
                $treeList = $this->array2Tree($treeData,'0');
                return print_r($treeList, true);
            }
            return "Client say: '$msg'". PHP_EOL;
        });
    }

    public function actionTcpClient()
    {
        TcpService::getInstance()->client();
    }

    private function generateShortId($randStr = '')
    {
        $charset = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
        $key = 'WOOD-HEAD'; //加盐
        $urlHash = md5($key . $randStr);
        $len = strlen($urlHash);

        //将加密后的串分成4段，每段4字节，对每段进行计算，一共可以生成四组短连接
        $shortIdList = [];
        for ($i = 0; $i < 4; $i++) {
            $urlHashPiece = substr($urlHash, $i * $len / 4, $len / 4);
            //将分段的位与0x3fffffff做位与，0x3fffffff表示二进制数的30个1，即30位以后的加密串都归零
            //此处需要用到hexdec()将16进制字符串转为10进制数值型，否则运算会不正常
            $hex = hexdec($urlHashPiece) & 0x3fffffff;
            //域名根据需求填写
            $shortId = '';
            //生成6位短网址
            for ($j = 0; $j < 10; $j++) {
                //将得到的值与0x0000003d,3d为61，即charset的坐标最大值
                $shortId .= $charset[$hex & 0x0000003d];
                //循环完以后将hex右移5位
                $hex = $hex >> 5;
            }
            $shortIdList[] = $shortId;
        }
        return $shortIdList;
    }

    private function array2Tree($dataList = [], $parentId = '', $pidList = [])
    {
        $treeList = [];
        foreach ($dataList as $key => $data) {
            if ($data['parent_id'] == $parentId) {
                unset($dataList[$key]);
                $pidList[] = $parentId;
                $data['children'] = $this->array2Tree($dataList, $data['id'], $pidList);
                $pidList = array_slice(array_unique($pidList), 1);
                $data['id_path'] = empty($pidList) ? ','.$data['id'].',' : ','.implode(',', array_slice(array_unique($pidList), 1)).','.$data['id'].',';
                $treeList[] = $data;
            }
        }
        return $treeList;
    }

    private string $json = '[
        {
            "id": 200002538,
            "name": "空心菜类",
            "level": 3,
            "namePath": "蔬菜/豆制品,叶菜类,空心菜类"
        },
        {
            "id": 200002537,
            "name": "香菜类",
            "level": 3,
            "namePath": "蔬菜/豆制品,葱姜蒜椒/调味菜,香菜类"
        },
        {
            "id": 200002536,
            "name": "紫苏/苏子叶",
            "level": 3,
            "namePath": "蔬菜/豆制品,叶菜类,紫苏/苏子叶"
        },
        {
            "id": 200002543,
            "name": "乌塌菜/塌菜/乌菜",
            "level": 3,
            "namePath": "蔬菜/豆制品,叶菜类,乌塌菜/塌菜/乌菜"
        },
        {
            "id": 200002542,
            "name": "菜心/菜苔类",
            "level": 3,
            "namePath": "蔬菜/豆制品,叶菜类,菜心/菜苔类"
        },
        {
            "id": 200002540,
            "name": "马兰头/马兰/红梗菜",
            "level": 3,
            "namePath": "蔬菜/豆制品,叶菜类,马兰头/马兰/红梗菜"
        }
    ]';
}