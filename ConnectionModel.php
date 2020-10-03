<?php

class ConnectionModel {
	private string $id = '';
	private string $file = '';
	private string $status = '';

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
	}

	/**
	 * @return string
	 */
	public function getFile(): string {
		return $this->file;
	}

	/**
	 * @param string $file
	 */
	public function setFile( string $file ): void {
		$this->file = $file;
	}

	/**
	 * @return string
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * @param string $id
	 */
	public function setId( string $id ): void {
		$this->id = $id;
	}
}