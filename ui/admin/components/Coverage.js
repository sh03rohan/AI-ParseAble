import { __, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { api, formatBytes, timeAgo } from '../api';

/**
 * One-line status strip. It states the logging mode and what it misses; actions live behind "Details"
 * so a healthy install is quiet and a broken one is loud.
 */
export default function Coverage( { coverage, onChange } ) {
	const [ open, setOpen ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	if ( ! coverage ) {
		return null;
	}
	const { complete, misses, page_cache: pageCache, queue, last_ingest: lastIngest, ingest_stale: stale, cron_backend: cron, queue_readable: queueReadable } = coverage;

	const problems = [];
	if ( stale ) {
		problems.push( __( 'Last ingest ran over an hour ago — WP-Cron may not be firing. A system cron or Action Scheduler (bundled with WooCommerce) is more reliable.', 'crawlledger-ai-crawler-log' ) );
	}
	if ( cron === 'wp-cron-disabled' ) {
		problems.push( __( 'DISABLE_WP_CRON is set and Action Scheduler is not present: point a system cron at wp-cron.php or the ingest will never run.', 'crawlledger-ai-crawler-log' ) );
	}
	if ( queueReadable === true ) {
		problems.push( __( 'The crawler log directory is readable over HTTP — the .htaccess deny rule is not honoured (Nginx?). Deny access to uploads/crawlledger/ in the server config.', 'crawlledger-ai-crawler-log' ) );
	}

	let level = 'warn';
	if ( problems.length ) {
		level = 'error';
	} else if ( complete ) {
		level = 'ok';
	}
	const title = complete ? __( 'Full coverage · logging active', 'crawlledger-ai-crawler-log' ) : __( 'Logging active, with gaps', 'crawlledger-ai-crawler-log' );
	const cronLabel = { 'action-scheduler': 'Action Scheduler', 'wp-cron': 'WP-Cron', 'wp-cron-disabled': __( 'cron disabled', 'crawlledger-ai-crawler-log' ) }[ cron ] || cron;

	const act = async ( fn ) => {
		setBusy( true );
		try {
			await fn();
			onChange();
		} finally {
			setBusy( false );
		}
	};

	return (
		<div className={ `clg-status clg-status--${ level }` }>
			<div className="clg-status-line">
				<span className="clg-status-dot" aria-hidden="true" />
				<strong>{ title }</strong>
				<span className="clg-status-facts">
					{ sprintf(
						/* translators: 1: time ago, 2: cron backend name, 3: pending file count */
						__( 'Last ingest %1$s · via %2$s · %3$d file(s) queued', 'crawlledger-ai-crawler-log' ),
						lastIngest ? timeAgo( lastIngest ) : __( 'never', 'crawlledger-ai-crawler-log' ),
						cronLabel,
						queue.files
					) }
					{ queue.bytes > 0 ? ` (${ formatBytes( queue.bytes ) })` : '' }
				</span>
				<button type="button" className="clg-link clg-status-toggle" aria-expanded={ open } onClick={ () => setOpen( ! open ) }>{ open ? __( 'Hide details', 'crawlledger-ai-crawler-log' ) : __( 'Details', 'crawlledger-ai-crawler-log' ) }</button>
			</div>
			{ problems.map( ( p ) => <p key={ p } className="clg-status-problem">{ p }</p> ) }
			{ ( open || misses.length > 0 ) && (
				<div className="clg-status-body">
					{ misses.length > 0 && (
						<div>
							<span className="clg-status-label">{ __( 'Not captured by this setup', 'crawlledger-ai-crawler-log' ) }</span>
							<ul>{ misses.map( ( m ) => <li key={ m }>{ m }</li> ) }</ul>
						</div>
					) }
					{ open && (
						<>
							{ pageCache.plugins.length > 0 && <p className="clg-muted">{ sprintf(
								/* translators: %s: plugin names */
								__( 'Cache plugins detected: %s', 'crawlledger-ai-crawler-log' ), pageCache.plugins.join( ', ' ) ) }</p> }
							<p className="clg-muted">{ __( 'Crawler visits are captured when the plugin loads, before any hook runs, and queued to a file at shutdown. Only responses served before WordPress runs at all are invisible.', 'crawlledger-ai-crawler-log' ) }</p>
							<div className="clg-actions">
								<Button variant="secondary" isBusy={ busy } onClick={ () => act( () => api.post( '/ingest' ) ) }>{ __( 'Ingest queue now', 'crawlledger-ai-crawler-log' ) }</Button>
							</div>
						</>
					) }
				</div>
			) }
		</div>
	);
}
