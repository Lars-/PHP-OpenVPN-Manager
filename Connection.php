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
	private int $lastActive;
	private ConnectionInterface $connection;
	private int $waitBeats = 0;

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
		echo $this->execLine;
		$this->process->start();
	}

	public function checkConnectionAndSendStatus( int $waitBeats = 15 ) {
		if ( $this->waitBeats < $waitBeats ) {
			if ( $this->isConnected() ) {
				$this->waitBeats = 0;
				$this->sendStatus();

				return;
			}
			$this->waitBeats ++;

			return;
		}
		$this->setStatus( 'disconnected' );
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
				$this->setStatus( 'idle' );
			}
			$this->tun = $content;

			return true;
		}

		return false;
	}

	public function sendStatus() {
		$data = json_encode( [
			'id'      => $this->id,
			'command' => 'status',
			'data'    => $this->status,
		], JSON_THROW_ON_ERROR );
		echo "Sending: $data\n";
		$this->connection->write( $data . "\n" );
	}

	public function __destruct() {
		$this->stop();
	}

	public function stop(): void {
		if ( file_exists( __DIR__ . '/storage/' . $this->id . '.txt' ) ) {
			unlink( __DIR__ . '/storage/' . $this->id . '.txt' );
		}
		if ( file_exists( __DIR__ . '/storage/' . $this->id . '_cookies' ) ) {
			unlink( __DIR__ . '/storage/' . $this->id . '_cookies' );
		}
		if ( file_exists( __DIR__ . '/storage/' . $this->id . '_response' ) ) {
			unlink( __DIR__ . '/storage/' . $this->id . '_response' );
		}
		$this->process->stop( 15, SIGTERM );
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
		$this->sendStatus();
	}

	public function updateLastActive(): void {
		$this->lastActive = time();
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