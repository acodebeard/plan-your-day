<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Google;

defined( 'ABSPATH' ) || exit;

final class GoogleApiResult {
	private bool $success;
	private array $data;
	private string $error_code;
	private string $message;
	private int $status_code;
	private bool $retryable;

	private function __construct(
		bool $success,
		array $data,
		string $error_code,
		string $message,
		int $status_code,
		bool $retryable
	) {
		$this->success     = $success;
		$this->data        = $data;
		$this->error_code  = $error_code;
		$this->message     = $message;
		$this->status_code = $status_code;
		$this->retryable   = $retryable;
	}

	public static function success( array $data, int $status_code = 200 ): self {
		return new self( true, $data, '', '', $status_code, false );
	}

	public static function failure(
		string $error_code,
		string $message,
		int $status_code = 0,
		bool $retryable = true
	): self {
		return new self( false, [], $error_code, $message, $status_code, $retryable );
	}

	public function is_success(): bool {
		return $this->success;
	}

	public function data(): array {
		return $this->data;
	}

	public function error_code(): string {
		return $this->error_code;
	}

	public function message(): string {
		return $this->message;
	}

	public function status_code(): int {
		return $this->status_code;
	}

	public function is_retryable(): bool {
		return $this->retryable;
	}

	public function to_array(): array {
		if ( $this->success ) {
			return [
				'success' => true,
				'data'    => $this->data,
			];
		}

		return [
			'success' => false,
			'data'    => [],
			'error'   => [
				'code'        => $this->error_code,
				'message'     => $this->message,
				'status_code' => $this->status_code,
				'retryable'   => $this->retryable,
			],
		];
	}
}
