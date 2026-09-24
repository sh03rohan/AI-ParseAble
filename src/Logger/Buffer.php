<?php
/**
 * In-memory hit buffer for the PHP-level path.
 *
 * @package CrawlLedger
 */

namespace CrawlLedger\Logger;

/**
 * Holds records in memory and flushes them at shutdown,
 * after the response has been handed to the web server where fastcgi_finish_request() exists.
 */
final class Buffer {

	/**
	 * Queue writer.
	 *
	 * @var Queue
	 */
	private $queue;

	/**
	 * Pending records (one closure per hit so the status code is read at shutdown).
	 *
	 * @var callable[]
	 */
	private $pending = array();

	/**
	 * Whether the shutdown hook is registered.
	 *
	 * @var bool
	 */
	private $armed = false;

	/**
	 * Constructor.
	 *
	 * @param Queue $queue Queue writer.
	 */
	public function __construct( Queue $queue ) {
		$this->queue = $queue;
	}

	/**
	 * Add a record producer. The producer returns the line to append or '' to skip.
	 *
	 * @param callable $producer Callable returning string.
	 * @return void
	 */
	public function add( callable $producer ): void {
		$this->pending[] = $producer;
		if ( ! $this->armed ) {
			$this->armed = true;
			add_action( 'shutdown', array( $this, 'flush' ), PHP_INT_MAX );
		}
	}

	/**
	 * Flush at shutdown.
	 *
	 * @return void
	 */
	public function flush(): void {
		if ( ! $this->pending ) {
			return;
		}
		/**
		 * Whether to release the response before writing. True by default on FastCGI servers.
		 *
		 * @param bool $finish Release the connection first.
		 */
		if ( apply_filters( 'crawlledger_finish_request', true ) && function_exists( 'fastcgi_finish_request' ) && ! wp_doing_cron() && PHP_SAPI !== 'cli' ) {
			fastcgi_finish_request();
		}
		foreach ( $this->pending as $producer ) {
			$line = (string) call_user_func( $producer );
			if ( '' !== $line ) {
				$this->queue->append( $line );
			}
		}
		$this->pending = array();
	}
}
