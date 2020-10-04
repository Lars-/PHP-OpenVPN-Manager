<?php

namespace LJPc;

use ConnectionModel;
use Exception;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use SplObjectStorage;

class WebsocketServer implements MessageComponentInterface {
	protected $clients;

	public function __construct() {
		$this->clients = new SplObjectStorage;
	}

	public function onOpen( ConnectionInterface $conn ) {
		$this->clients->attach( $conn, new ConnectionModel() );
	}

	public function onMessage( ConnectionInterface $from, $msg ) {
		try {
			$data = json_decode( $msg, true, 512, JSON_THROW_ON_ERROR );
		} catch ( Exception $e ) {
			return;
		}
		if ( ! isset( $data['id'] ) ) {
			return;
		}
		if ( $data['id'] === '*' ) {
			foreach ( $this->clients as $client ) {
				$client->send( $msg );
			}

			return;
		}

		/** @var ConnectionModel $connectionModel */
		$connectionModel = $this->clients->offsetGet( $from );
		$connectionModel->setId( $data['id'] );

//		echo 'Message from ' . $connectionModel->getId() . "\n";

		if ( $data['command'] === 'status' ) {
			$connectionModel->setStatus( $data['data'] );
		}

		if ( $data['command'] === 'response' ) {
			echo $data['data'];
		}
	}

	public function onClose( ConnectionInterface $conn ) {
		$this->clients->detach( $conn );
	}

	public function onError( ConnectionInterface $conn, Exception $e ) {
		echo "An error has occurred: {$e->getMessage()}\n";

		$conn->close();
	}
}