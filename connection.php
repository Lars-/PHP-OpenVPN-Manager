<?php
require_once __DIR__ . '/vendor/autoload.php';

$loop      = React\EventLoop\Factory::create();
$connector = new React\Socket\Connector( $loop );

$connector->connect( '127.0.0.1:8081' )->then( function ( React\Socket\ConnectionInterface $connection ) {
	$connection->write( '...' );
	$connection->on( 'data', function ( $data ) use ( $connection ) {
		echo $data;
//		$connection->close();
	} );
} );

$loop->run();