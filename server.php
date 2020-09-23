<?php

use LJPc\WebsocketServer;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/WebsocketServer.php';

$loop = React\EventLoop\Factory::create();

$server = new React\Http\Server( $loop, static function ( Psr\Http\Message\ServerRequestInterface $request ) {
	return new React\Http\Message\Response(
		200,
		array(
			'Content-Type' => 'text/plain'
		),
		"Hello World!\n"
	);
} );


$socket = new React\Socket\Server( '0.0.0.0:8080', $loop );
$server->listen( $socket );

$websocket       = new React\Socket\Server( '0.0.0.0:8081', $loop );
$websocketServer = new Ratchet\Server\IoServer( new WebsocketServer(), $websocket, $loop );

echo "Server running at http://127.0.0.1:8080\n";

$loop->run();