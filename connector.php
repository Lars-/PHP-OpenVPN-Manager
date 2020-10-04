<?php

use Garden\Cli\Cli;
use LJPc\VPN\Connection;
use React\EventLoop\Factory;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/Connection.php';
$cli = new Cli();

$cli->description( 'Starts a single OpenVPN connection and communicates with the server' )
    ->opt( 'host:h', 'Server host' )
    ->opt( 'port:P', 'Port of the server.', true, 'integer' )
    ->opt( 'id:i', 'ID of the connection.', true )
    ->opt( 'file:f', 'Full path to OVPN file', true )
    ->opt( 'credentials:c', 'File containing the unencrypted credentials' )
    ->opt( 'logfile:l', 'File to put logs in.' )
    ->opt( 'verbose:v', 'Logging should be verbose' );

$args = $cli->parse( $argv, true );

$host        = $args->getOpt( 'host', '127.0.0.1' );
$port        = $args->getOpt( 'port' );
$id          = $args->getOpt( 'id' );
$file        = $args->getOpt( 'file' );
$credentials = $args->getOpt( 'credentials', __DIR__ . '/credentials/empty.txt' );

$loop      = Factory::create();
$connector = new Connector( $loop );
$connector->connect( "$host:$port" )->then( static function ( ConnectionInterface $connection ) use ( $id, $file, $credentials, $loop ) {
	$connection->on( 'close', function () {
		exit;
	} );

	$vpnConnection = new Connection( (string) $id, (string) $file, (string) $credentials, $connection );
	$vpnConnection->start();

	$loop->addPeriodicTimer( 1, static function () use ( $vpnConnection, $connection ) {
		$vpnConnection->checkConnectionAndSendStatus();
	} );

	$connection->on( 'data', static function ( $data ) use ( $connection, $id, $vpnConnection ) {
		try {
			$data = json_decode( $data, true, 512, JSON_THROW_ON_ERROR );
		} catch ( Exception $e ) {
			return;
		}
		if ( ! isset( $data['id'] ) ) {
			return;
		}
		if ( $data['id'] !== $id && $data['id'] !== '*' ) {
			return;
		}

		if ( $data['command'] === 'request' ) {
			$vpnConnection->setStatus( 'working' );
			$curl = curl_init();
			curl_setopt_array( $curl, $data['data'] );
			curl_setopt( $curl, CURLOPT_INTERFACE, $vpnConnection->getTun() );
			curl_setopt( $curl, CURLOPT_TIMEOUT, 120 );
			curl_setopt( $curl, CURLOPT_COOKIESESSION, true );
			curl_setopt( $curl, CURLOPT_COOKIEJAR, __DIR__ . "/storage/{$id}_cookies" );
			curl_setopt( $curl, CURLOPT_COOKIEFILE, __DIR__ . "/storage/{$id}_cookies" );

			$response = curl_exec( $curl );
			$info     = curl_getinfo( $curl );
			$err      = curl_error( $curl );

			curl_close( $curl );
			file_put_contents( __DIR__ . "/storage/{$id}_response", json_encode( [
				'response' => $response,
				'info'     => $info,
				'err'      => $err,
			], JSON_THROW_ON_ERROR ) );

			$vpnConnection->setStatus( 'used' );
		}

		if ( $data['command'] === 'kill' ) {
			$vpnConnection->stop();
			$connection->close();
		}
	} );
} );


$loop->run();