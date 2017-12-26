<?php
$listenHost = "0.0.0.0";
$listenPort = "9988";
$serv = new Swoole\Server($listenHost, $listenPort);
$serv->set([
    'worker_num' => 16,     //工作进程数量，决定最大并发量，一个进程可以hold n个连接
    'daemonize' => false,  //是否以守护进程(后台)方式运行
    // 'open_http_protocol' => true    //开启http协议处理
]);
$serv->on('start', function ($serv) use ($listenHost, $listenPort) {
    echo "Server start on $listenHost:$listenPort ...";
});
$serv->on('connect', function ($serv, $fd) {
    //do nothing
});
$serv->on('close', function ($serv, $fd) {
    //do nothing
});
$serv->on('receive', function ($serv, $fd, $from_id, $data) {
    $firstLine = explode("\r\n", $data, 2)[0];
    $addr = explode(' ', $firstLine);
    $http = $addr[0];

    if (preg_match('/CONNECT/i', $http)) {
        $urlData = explode(':', $addr[1]);
        $host = $urlData[0];
        $port = isset($urlData[1]) ? $urlData[1] : '443';
        $client = new swoole_client(SWOOLE_TCP | SWOOLE_SSL, SWOOLE_SOCK_ASYNC);
        echo "{$data}\n";
    } else {
        $urlData = parse_url($addr[1]);
        $host = isset($urlData['host']) ? $urlData['host'] : false;
        $port = isset($urlData['port']) ? $urlData['port'] : '80';
        $client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
    }

    if ($host) {
        $client->on("connect", function ($cli) use ($data) {
            $cli->send($data);
        });
        $client->on("receive", function ($cli, $data) use ($serv, $fd) {
            $serv->send($fd, $data);
        });
        $client->on("error", function ($cli) {
            echo "Connect failed\n";
        });
        $client->on("close", function ($cli) {
            //do nothing
        });
        swoole_async_dns_lookup($host, function ($host, $ip) use ($client, $port) {
            if (preg_match("/[\\d]{2,3}\\.[\\d]{1,3}\\.[\\d]{1,3}\\.[\\d]{1,3}/", $host)) $ip = $host;
            $client->connect($ip, $port);
        });
    } else return;

});
$serv->start();