<?php
namespace common\services\network;

use yii\base\Component;

/**
 * Tcp服务
 */
class TcpService extends Component
{
    /**
     * 实例对象
     * @var $instance self
     */
    private static $instance;

    /**
     * IP或主机地址
     * @var $host string
     */
    private string $host;

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @param string $host
     */
    public function setHost(string $host): void
    {
        $this->host = $host;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @param int $port
     */
    public function setPort(int $port): void
    {
        $this->port = $port;
    }

    /**
     * 端口号
     * @var $port int
     */
    private int $port;

    /**
     * @return TcpService|self
     */
    public static function getInstance($host = '127.0.0.1', $port = '9999')
    {
        if (empty(self::$instance)) {
            self::$instance = new self($host, $port);
        }
        return self::$instance;
    }

    /**
     * 构造函数
     */
    private function __construct($host, $port)
    {
        parent::__construct();
        $this->setHost($host);
        $this->setPort($port);
    }

    public function server(\Closure $callback = null)
    {
        error_reporting(E_ALL);
        set_time_limit(0);
        ob_implicit_flush();

        if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
            echo "socket_create() error: " . socket_strerror(socket_last_error()) . "\n";
            exit();
        }

        if (socket_bind($sock, $this->getHost(), $this->getPort()) === false) {
            echo "socket_bind() error: " . socket_strerror(socket_last_error($sock)) . "\n";
            exit();
        }

        if (socket_listen($sock, 5) === false) {
            echo "socket_listen() error: " . socket_strerror(socket_last_error($sock)) . "\n";
            exit();
        }

        //客户端 array
        $clients = [];

        do {
            // socket资源ID
            // $socketId = spl_object_id($sock);
            // echo '当前socketID: '.$socketId.PHP_EOL;

            $read = [];
            $read[] = $sock;

            $read = array_merge($read, $clients);
            $readOut = print_r($read, true);
            // echo '当前连接列表：'.$readOut.PHP_EOL;

            // 使用 select IO复用，切换当前连接资源（有待读取数据的client）
            // get a list of all the clients that have data to be read from
            // 从所有客户端中寻找有待读取数据的客户端列表
            // if there are no clients with data, go to next iteration
            // 如果没有一个客户端有消息，则继续阻塞
            if(socket_select($read,$write, $except, $tv_sec = 5) < 1) {
                continue;
            }
            //echo '切换到连接：'.print_r(spl_object_id($read[array_keys($read)[0]]), true);

            // 当有客户端产生消息时，$read 将包含这些客户端连接
            // 当有新连接时，$read将包含当前新连接，此处只会执行一次
            if (in_array($sock, $read)) {
                if (($msgsock = socket_accept($sock)) === false) {
                    echo "socket_accept() 错误: " . socket_strerror(socket_last_error($sock)) . PHP_EOL;;
                    break;
                }
                $clients[] = $msgsock;
                $key = array_keys($clients, $msgsock);
                $msg = "\n新连接：{$key[0]}\n";
                socket_write($msgsock, $msg, strlen($msg));
            }

            // 监听和处理客户端输入
            foreach ($clients as $key => $client) {
                if (in_array($client, $read)) {
                    if (false === ($buf = socket_read($client, 2048, PHP_NORMAL_READ))) {
                        echo "socket_read() error: " . socket_strerror(socket_last_error($client)) . PHP_EOL;
                        break 2;
                    }
                    if (!$buf = trim($buf)) {
                        continue;
                    }
                    if ($buf == 'quit') {
                        unset($clients[$key]);
                        socket_close($client);
                        break;
                    }
                    if ($buf == 'shutdown') {
                        socket_close($client);
                        break 2;
                    }
                    $talkback = "Client {$key}: say: '$buf'". PHP_EOL;
                    if (!empty($callback) && is_callable($callback)) {
                        $talkback = $callback($buf) . PHP_EOL;
                    }
                    socket_write($client, $talkback, strlen($talkback));
                    echo $buf. PHP_EOL;
                }

            }
        } while (true);

        socket_close($sock);
    }

    public function client()
    {
        if(($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === FALSE) {
            exit('初始化socket资源错误: ' . socket_strerror(socket_last_error($sock)));
        }

        if(socket_connect($sock, $this->getHost(), $this->getPort()) === FALSE) {
            exit('连接socket失败: ' . socket_strerror(socket_last_error($sock)));
        }

        $msg = '客户端1消息';
        if(socket_write($sock, $msg) === FALSE) {
            exit('发送数据失败: ' . socket_strerror(socket_last_error($sock)));
        }

        $data = '';
        // 循环读取指定长度的服务器响应数据
        while($response = socket_read($sock, 4)) {
            $data .= $response;
            echo $response. PHP_EOL;
        }
        echo $data . PHP_EOL;

        socket_close($sock);
    }
}