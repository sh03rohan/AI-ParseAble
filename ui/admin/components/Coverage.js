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
	const { mode, complete, misses, page_cache: pageCache, queue, last_ingest: lastIngest, ingest_stale: stale, cron_backend: cron, mu_writable: muWritable, queue_readable: queueReadable } = coverage;

	const problems = [];
	if ( stale ) {
		problems.push( __( 'Last ingest ran over an hour ago — WP-Cron may not be firing. A system cron or Action Scheduler (bundled with WooCommerce) is more reliable.', 'ai-parseable' ) );
	}
	if ( cron === 'wp-cron-disabled' ) {
		problems.push( __( 'DISABLE_WP_CRON is set and Action Scheduler is not present: point a system cron at wp-cron.php or the ingest will never run.', 'ai-parseable' ) );
	}
	if ( queueReadable === true ) {
		problems.push( __( 'The crawler log directory is readable over HTTP — the .htaccess deny rule is not honoured (Nginx?). Deny access to uploads/ai-parseable/ in the server config.', 'ai-parseable' ) );
	}

	let level = 'warn';
	if ( problems.length ) {
		level = 'error';
	} else if ( complete ) {
		level = 'ok';
	}
	let title = __( 'PHP-level logging only — page-cache hits are missed', 'ai-parseable' );
	if ( mode === 'drop-in' ) {
		title = complete ? __( 'Full coverage · drop-in active', 'ai-parseable' ) : __( 'Drop-in active, with gaps', 'ai-parseable' );
	}
	const cronLabel = { 'action-scheduler': 'Action Scheduler', 'wp-cron': 'WP-Cron', 'wp-cron-disabled': __( 'cron disabled', 'ai-parseable' ) }[ cron ] || cron;

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
		<div className={ `aip-status aip-status--${ level }` }>
			<div className="aip-status-line">
				<span className="aip-status-dot" aria-hidden="true" />
				<strong>{ title }</strong>
				<span className="aip-status-facts">
					{ sprintf(
						/* translators: 1: time ago, 2: cron backend name, 3: pending file count */
						__( 'Last ingest %1$s · via %2$s · %3$d file(s) queued', 'ai-parseable' ),
						lastIngest ? timeAgo( lastIngest ) : __( 'never', 'ai-parseable' ),
						cronLabel,
						queue.files
					) }
					{ queue.bytes > 0 ? ` (${ formatBytes( queue.bytes ) })` : '' }
				</span>
				<button type="button" className="aip-link aip-status-toggle" aria-expanded={ open } onClick={ () => setOpen( ! open ) }>{ open ? __( 'Hide details', 'ai-parseable' ) : __( 'Details', 'ai-parseable' ) }</button>
			</div>
			{ problems.map( ( p ) => <p key={ p } className="aip-status-problem">{ p }</p> ) }
			{ ( open || misses.length > 0 ) && (
				<div className="aip-status-body">
					{ misses.length > 0 && (
						<div>
							<span className="aip-status-label">{ __( 'Not captured by this setup', 'ai-parseable' ) }</span>
							<ul>{ misses.map( ( m ) => <li key={ m }>{ m }</li> ) }</ul>
						</div>
					) }
					{ open && (
						<>
							{ pageCache.plugins.length > 0 && <p className="aip-muted">{ sprintf(
								/* translators: %s: plugin names */
								__( 'Cache plugins detected: %s', 'ai-parseable' ), pageCache.plugins.join( ', ' ) ) }</p> }
							<p className="aip-muted">{ sprintf(
								/* translators: %s: file path */
								__( 'Drop-in path: %s', 'ai-parseable' ), coverage.drop_in_path ) }</p>
							<div className="aip-actions">
								{ mode !== 'drop-in' && muWritable && <Button variant="secondary" isBusy={ busy } onClick={ () => act( () => api.post( '/drop-in', { action: 'install' } ) ) }>{ __( 'Install drop-in', 'ai-parseable' ) }</Button> }
								{ mode === 'drop-in' && <Button variant="tertiary" isBusy={ busy } onClick={ () => act( () => api.post( '/drop-in', { action: 'remove' } ) ) }>{ __( 'Remove drop-in', 'ai-parseable' ) }</Button> }
								<Button variant="secondary" isBusy={ busy } onClick={ () => act( () => api.post( '/ingest' ) ) }>{ __( 'Ingest queue now', 'ai-parseable' ) }</Button>
							</div>
						</>
					) }
				</div>
			) }
		</div>
	);
}
