<?php


namespace LJPc\VPN;

use React\Socket\ConnectionInterface;
use Symfony\Component\Process\Process;

class Connection {
	private string $execLine;
	private Process $process;
	private string $id;
	private string $tun;
	private string $status = 'waiting';
	private string $coupledWith;
	private int $lastActive;
	private ConnectionInterface $connection;

	public function __construct( string $id, string $configFile, string $credentialsFile, ConnectionInterface $connection ) {
		$this->id         = $id;
		$connectScript    = __DIR__ . '/scripts/connect.sh ' . __DIR__ . '/storage/' . $this->id;
		$disconnectScript = __DIR__ . '/scripts/disconnect.sh ' . __DIR__ . '/storage/' . $this->id;
		$this->execLine   = "exec openvpn --config $configFile --auth-user-pass $credentialsFile --script-security 2 --pull-filter ignore redirect-gateway --up '$connectScript' --down '$disconnectScript'";
		$this->lastActive = time();
		$this->connection = $connection;
	}

	public function start(): void {
		$this->setStatus( 'connecting' );
		$this->process = Process::fromShellCommandline( $this->execLine );
		$this->process->start();
	}

	public function waitUntilConnected( int $maxSeconds = 15 ) {
		$i = 0;
		while ( $i < $maxSeconds ) {
			if ( $this->isConnected() ) {
//				Helpers::log( 'Connected to ' . $this->tun );
				break;
			}
			sleep( 1 );
			$i ++;
		}
	}

	public function isConnected(): bool {
		$file = __DIR__ . '/storage/' . $this->id . '.txt';
		if ( file_exists( $file ) ) {
			$content = trim( file_get_contents( $file ) );
			if ( $content === 'disconnected' ) {
				$this->setStatus( 'disconnected' );

				return false;
			}
			if ( $this->status === 'connecting' ) {
				if ( $this->coupledWith === '' ) {
					$this->setStatus( 'idle' );
				} else {
					$this->setStatus( 'connected' );
				}
			}
			$this->tun = $content;

			return true;
		}

		return false;
	}

	public function __destruct() {
		if ( file_exists( __DIR__ . '/storage/' . $this->id . '.txt' ) ) {
			unlink( __DIR__ . '/storage/' . $this->id . '.txt' );
		}
	}

	public function stop(): void {
//		Helpers::log( "Stopping {$this->tun}" );
		$this->process->stop( 15, SIGTERM );
		$i = 0;
		while ( $i < 15 ) {
			if ( ! $this->isConnected() ) {
				break;
			}
			sleep( 1 );
			$i ++;
		}
		$this->setStatus( 'disconnected' );
	}

	/**
	 * @return string
	 */
	public function getStatus(): string {
		return $this->status;
	}

	/**
	 * @param string $status
	 */
	public function setStatus( string $status ): void {
		$this->status = $status;
		$this->updateLastActive();
		$data = json_encode( [
			'id'      => $this->id,
			'command' => 'status',
			'data'    => $status
		], JSON_THROW_ON_ERROR );
		var_dump( $data );
		$this->connection->write( $data );

	}

	public function updateLastActive(): void {
		$this->lastActive = time();
	}

	/**
	 * @return string
	 */
	public function getCoupledWith(): string {
		return $this->coupledWith;
	}

	/**
	 * @param string $coupledWith
	 */
	public function setCoupledWith( string $coupledWith ): void {
		$this->coupledWith = $coupledWith;
	}

	/**
	 * @return string
	 */
	public function getTun(): string {
		return $this->tun;
	}

	/**
	 * @return int
	 */
	public function getLastActive(): int {
		return $this->lastActive;
	}
}