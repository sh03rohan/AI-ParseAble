import { __, _n, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { Spinner, ToggleControl } from '@wordpress/components';
import { api, formatBytes, formatNumber, timeAgo } from '../api';
import { AreaChart, Sparkline, Proportion } from './Charts';
import Coverage from './Coverage';

const TYPE = {
	answer: { label: __( 'Answer', 'ai-parseable' ), hint: __( 'Fetches pages to build answers for users in real time.', 'ai-parseable' ) },
	search: { label: __( 'Search', 'ai-parseable' ), hint: __( 'Indexes for a search product that also feeds AI answers.', 'ai-parseable' ) },
	training: { label: __( 'Training', 'ai-parseable' ), hint: __( 'Collects content for model training.', 'ai-parseable' ) },
};

function Delta( { now, before, invert = false } ) {
	if ( ! before && ! now ) {
		return <span className="aip-delta aip-delta--flat">—</span>;
	}
	if ( ! before ) {
		return <span className="aip-delta aip-delta--flat">{ __( 'new', 'ai-parseable' ) }</span>;
	}
	const pct = Math.round( ( ( now - before ) / before ) * 100 );
	const good = invert ? pct <= 0 : pct >= 0;
	let tone = 'flat';
	let arrow = '±';
	if ( pct !== 0 ) {
		tone = good ? 'up' : 'down';
		arrow = pct > 0 ? '▲' : '▼';
	}
	return (
		<span className={ `aip-delta aip-delta--${ tone }` } title={ __( 'vs. the previous period', 'ai-parseable' ) }>
			{ arrow } { Math.abs( pct ) }%
		</span>
	);
}

function Stat( { label, value, sub, delta } ) {
	return (
		<div className="aip-stat">
			<span className="aip-stat-label">{ label }</span>
			<span className="aip-stat-row"><span className="aip-stat-value">{ value }</span>{ delta }</span>
			{ sub && <span className="aip-stat-sub">{ sub }</span> }
		</div>
	);
}

function Status( { code } ) {
	const thresholds = [ [ 500, 'err' ], [ 400, 'warn' ], [ 300, 'redir' ] ];
	const match = thresholds.find( ( [ min ] ) => code >= min );
	const cls = match ? match[ 1 ] : 'ok';
	return <span className={ `aip-code aip-code--${ cls }` }>{ code }</span>;
}

function EmptyState( { coverage } ) {
	return (
		<div className="aip-empty">
			<h2>{ __( 'No crawler visits recorded yet', 'ai-parseable' ) }</h2>
			<p>{ __( 'Hits are captured as they happen and written to the database every five minutes. Most sites see their first AI crawler within a day.', 'ai-parseable' ) }</p>
			{ coverage && coverage.queue.files > 0 && <p>{ sprintf(
				/* translators: %d: number of files */
				_n( '%d queue file is waiting for the next ingest.', '%d queue files are waiting for the next ingest.', coverage.queue.files, 'ai-parseable' ), coverage.queue.files ) }</p> }
			<p className="aip-muted">{ __( 'To test the pipeline, request any page with a crawler user agent, e.g. curl -A "GPTBot" and then use "Ingest queue now" under Details.', 'ai-parseable' ) }</p>
		</div>
	);
}

export default function Overview() {
	const [ range, setRange ] = useState( '7d' );
	const [ verified, setVerified ] = useState( true );
	const [ showQuiet, setShowQuiet ] = useState( false );
	const [ showAll, setShowAll ] = useState( { failing: false, top: false, recent: false } );
	const limit = ( key, list, n ) => ( showAll[ key ] ? list : list.slice( 0, n ) );
	const More = ( { k, list, n } ) => list.length > n ? <button type="button" className="aip-link aip-table-toggle" onClick={ () => setShowAll( { ...showAll, [ k ]: ! showAll[ k ] } ) }>{ showAll[ k ] ? __( 'Show fewer', 'ai-parseable' ) : sprintf(
		/* translators: %d: count */
		__( 'Show all %d', 'ai-parseable' ), list.length ) }</button> : null;
	const [ stats, setStats ] = useState( null );
	const [ urls, setUrls ] = useState( null );
	const [ coverage, setCoverage ] = useState( null );
	const [ error, setError ] = useState( '' );

	const load = () => {
		setError( '' );
		const fail = ( e ) => setError( e.message || String( e ) );
		// Each panel paints as its own data lands; the first is served from the page preload.
		api.get( '/stats', { range, verified: verified ? 1 : 0 } ).then( setStats ).catch( fail );
		api.get( '/urls', { verified: verified ? 1 : 0 } ).then( setUrls ).catch( fail );
		api.get( '/coverage' ).then( setCoverage ).catch( fail );
	};
	useEffect( load, [ range, verified ] );

	if ( error ) {
		return <p className="aip-error">{ error }</p>;
	}
	if ( ! stats ) {
		return <div className="aip-loading"><Spinner /></div>;
	}

	const historyDays = stats.history_days;
	const ranges = [ [ '7d', 7 ], [ '30d', 30 ], [ '90d', 90 ] ];
	const active = stats.bots.filter( ( b ) => b.total > 0 );
	const quiet = stats.bots.filter( ( b ) => b.total === 0 );
	const timing = stats.timing;
	const nothingYet = stats.totals.all === 0 && urls !== null && urls.recent.length === 0;
	const errRate = stats.totals.hits ? Math.round( ( stats.totals.errors / stats.totals.hits ) * 100 ) : 0;
	const prevErrRate = stats.previous.hits ? Math.round( ( stats.previous.errors / stats.previous.hits ) * 100 ) : 0;

	return (
		<div className="aip-overview">
			<Coverage coverage={ coverage } onChange={ load } />

			{ nothingYet ? <EmptyState coverage={ coverage } /> : (
				<>
					<div className="aip-toolbar">
						<div className="aip-segmented" role="group" aria-label={ __( 'Range', 'ai-parseable' ) }>
							{ ranges.map( ( [ key, days ] ) => (
								<button key={ key } type="button" className={ key === range ? 'is-active' : '' } disabled={ days > historyDays } title={ days > historyDays ? __( 'Longer history is part of the add-on', 'ai-parseable' ) : '' } onClick={ () => setRange( key ) }>
									{ sprintf(
										/* translators: %d: number of days */
										__( '%d days', 'ai-parseable' ), days ) }
									{ days > historyDays && <span className="aip-lock" aria-hidden="true">🔒</span> }
								</button>
							) ) }
						</div>
						<ToggleControl label={ __( 'Verified crawlers only', 'ai-parseable' ) } checked={ verified } onChange={ setVerified } __nextHasNoMarginBottom />
						<span className="aip-muted aip-toolbar-note">
							{ sprintf(
								/* translators: 1: verified count, 2: unverified count */
								__( '%1$s verified · %2$s unverified', 'ai-parseable' ), formatNumber( stats.totals.verified ), formatNumber( stats.totals.unverified ) ) }
							<span className="aip-help" title={ __( 'Unverified: the user agent claimed a crawler but the IP is not in the vendor\'s published ranges and reverse DNS did not confirm it — or the vendor publishes neither.', 'ai-parseable' ) }>?</span>
						</span>
					</div>

					<div className="aip-stats">
						<Stat label={ verified ? __( 'Verified crawler visits', 'ai-parseable' ) : __( 'Crawler visits', 'ai-parseable' ) } value={ formatNumber( stats.totals.hits ) } delta={ <Delta now={ stats.totals.hits } before={ stats.previous.hits } /> } sub={ sprintf(
							/* translators: %d: number of days */
							__( 'last %d days', 'ai-parseable' ), stats.days ) } />
						<Stat label={ __( 'Distinct crawlers', 'ai-parseable' ) } value={ formatNumber( stats.totals.bots ) } delta={ <Delta now={ stats.totals.bots } before={ stats.previous.bots } /> } sub={ sprintf(
							/* translators: %d: number of crawlers known */
							__( 'of %d known', 'ai-parseable' ), stats.bots.length ) } />
						<Stat label={ __( 'Error rate', 'ai-parseable' ) } value={ `${ errRate }%` } delta={ <Delta now={ errRate } before={ prevErrRate } invert /> } sub={ sprintf(
							/* translators: %s: count */
							__( '%s responses were 4xx/5xx', 'ai-parseable' ), formatNumber( stats.totals.errors ) ) } />
						<Stat label={ __( 'Cost per visitor request', 'ai-parseable' ) } value={ timing.non_bot_avg_us === null ? '—' : `${ ( timing.non_bot_avg_us / 1000 ).toFixed( 3 ) } ms` } sub={ timing.non_bot_avg_us === null ? __( 'no samples yet', 'ai-parseable' ) : sprintf(
							/* translators: 1: budget in ms, 2: sample count */
							__( 'budget %1$s ms · 0 queries · %2$d samples', 'ai-parseable' ), ( timing.budget_us / 1000 ).toFixed( 1 ), timing.samples ) } />
					</div>

					<section className="aip-panel">
						<header className="aip-panel-head">
							<h2>{ __( 'Visits per day', 'ai-parseable' ) }</h2>
							<span className="aip-muted">{ sprintf(
								/* translators: %s: milliseconds */
								__( 'from daily aggregates · %s ms', 'ai-parseable' ), stats.query_ms ) }</span>
						</header>
						<AreaChart series={ stats.series } />
					</section>

					<section className="aip-panel">
						<header className="aip-panel-head">
							<h2>{ __( 'Crawlers', 'ai-parseable' ) }</h2>
							<span className="aip-muted">{ sprintf(
								/* translators: 1: active count, 2: quiet count */
								__( '%1$d active · %2$d not seen', 'ai-parseable' ), active.length, quiet.length ) }</span>
						</header>
						<table className="aip-table">
							<thead>
								<tr>
									<th>{ __( 'Crawler', 'ai-parseable' ) }</th>
									<th>{ __( 'Type', 'ai-parseable' ) }</th>
									<th className="aip-num">{ __( 'Visits', 'ai-parseable' ) }</th>
									<th>{ __( 'Verified', 'ai-parseable' ) }</th>
									<th className="aip-num">{ __( 'Errors', 'ai-parseable' ) }</th>
									<th>{ __( 'Last seen', 'ai-parseable' ) }</th>
									<th>{ __( 'Trend', 'ai-parseable' ) }</th>
								</tr>
							</thead>
							<tbody>
								{ ( showQuiet ? stats.bots : active ).map( ( b ) => (
									<tr key={ b.id } className={ b.total === 0 ? 'aip-row-quiet' : '' }>
										<td><strong>{ b.name }</strong><span className="aip-muted"> { b.vendor }</span></td>
										<td><span className={ `aip-badge aip-badge--${ b.type }` } title={ TYPE[ b.type ].hint }>{ TYPE[ b.type ].label }</span></td>
										<td className="aip-num">{ formatNumber( b.total ) }</td>
										<td>
											{ b.verify === 'none'
												? <span className="aip-muted" title={ __( 'This vendor publishes no IP ranges or reverse-DNS pattern, so its hits cannot be verified.', 'ai-parseable' ) }>{ __( 'not verifiable', 'ai-parseable' ) }</span>
												: <Proportion value={ b.verified } total={ b.total } title={ sprintf(
													/* translators: 1: verified, 2: unverified */
													__( '%1$s verified, %2$s unverified', 'ai-parseable' ), formatNumber( b.verified ), formatNumber( b.unverified ) ) } /> }
										</td>
										<td className="aip-num">{ b.errors > 0 ? <span className="aip-err">{ formatNumber( b.errors ) }</span> : '0' }</td>
										<td>{ b.last_seen ? timeAgo( b.last_seen ) : <span className="aip-muted">{ __( 'never', 'ai-parseable' ) }</span> }</td>
										<td><Sparkline values={ b.spark } /></td>
									</tr>
								) ) }
							</tbody>
						</table>
						{ quiet.length > 0 && (
							<button type="button" className="aip-link aip-table-toggle" onClick={ () => setShowQuiet( ! showQuiet ) }>
								{ showQuiet ? __( 'Hide crawlers with no visits', 'ai-parseable' ) : sprintf(
									/* translators: %d: count */
									_n( 'Show %d crawler with no visits', 'Show %d crawlers with no visits', quiet.length, 'ai-parseable' ), quiet.length ) }
							</button>
						) }
					</section>

					<div className="aip-grid-2">
						<section className="aip-panel">
							<header className="aip-panel-head"><h2>{ __( 'Failing for crawlers', 'ai-parseable' ) }</h2><span className="aip-muted">{ __( '7 days', 'ai-parseable' ) }</span></header>
							{ urls && urls.failing.length === 0 && <p className="aip-quiet-msg">{ __( 'No 4xx or 5xx responses to crawlers. Good.', 'ai-parseable' ) }</p> }
							{ urls && urls.failing.length > 0 && (
								<table className="aip-table aip-table--compact">
									<thead><tr><th>{ __( 'URL', 'ai-parseable' ) }</th><th>{ __( 'Status', 'ai-parseable' ) }</th><th className="aip-num">{ __( 'Hits', 'ai-parseable' ) }</th><th className="aip-num">{ __( 'Crawlers', 'ai-parseable' ) }</th></tr></thead>
									<tbody>{ limit( 'failing', urls.failing, 10 ).map( ( u ) => <tr key={ u.url }><td className="aip-url">{ u.url }</td><td><Status code={ u.status } /></td><td className="aip-num">{ u.hits }</td><td className="aip-num">{ u.bots }</td></tr> ) }</tbody>
								</table>
							) }
							{ urls && <More k="failing" list={ urls.failing } n={ 10 } /> }
							{ coverage && coverage.page_cache && coverage.page_cache.server_level && (
								<p className="aip-muted">{ __( 'Failures from an allowed crawler may come from a CDN or server-level bot control above WordPress (e.g. Cloudflare\'s AI-scraper toggle), which this plugin cannot override.', 'ai-parseable' ) }</p>
							) }
						</section>
						<section className="aip-panel">
							<header className="aip-panel-head"><h2>{ __( 'Most crawled', 'ai-parseable' ) }</h2><span className="aip-muted">{ __( '7 days', 'ai-parseable' ) }</span></header>
							{ urls && urls.top.length === 0 && <p className="aip-quiet-msg">{ __( 'Nothing recorded in the last 7 days.', 'ai-parseable' ) }</p> }
							{ urls && urls.top.length > 0 && (
								<table className="aip-table aip-table--compact">
									<thead><tr><th>{ __( 'URL', 'ai-parseable' ) }</th><th className="aip-num">{ __( 'Hits', 'ai-parseable' ) }</th><th className="aip-num">{ __( 'Crawlers', 'ai-parseable' ) }</th></tr></thead>
									<tbody>{ limit( 'top', urls.top, 10 ).map( ( u ) => <tr key={ u.url }><td className="aip-url">{ u.url }</td><td className="aip-num">{ u.hits }</td><td className="aip-num">{ u.bots }</td></tr> ) }</tbody>
								</table>
							) }
							{ urls && <More k="top" list={ urls.top } n={ 10 } /> }
						</section>
					</div>

					<section className="aip-panel">
						<header className="aip-panel-head"><h2>{ __( 'Latest visits', 'ai-parseable' ) }</h2><span className="aip-muted">{ __( 'most recent 50 · all crawlers', 'ai-parseable' ) }</span></header>
						{ urls && urls.recent.length === 0 && <p className="aip-quiet-msg">{ __( 'Nothing in the raw log yet.', 'ai-parseable' ) }</p> }
						{ urls && urls.recent.length > 0 && (
							<table className="aip-table aip-table--compact aip-feed">
								<thead><tr><th>{ __( 'When', 'ai-parseable' ) }</th><th>{ __( 'Crawler', 'ai-parseable' ) }</th><th>{ __( 'URL', 'ai-parseable' ) }</th><th>{ __( 'Status', 'ai-parseable' ) }</th><th>{ __( 'Verified', 'ai-parseable' ) }</th></tr></thead>
								<tbody>
									{ limit( 'recent', urls.recent, 20 ).map( ( r, i ) => {
										const bot = stats.bots.find( ( b ) => b.id === r.bot_id );
										return (
											<tr key={ i }>
												<td className="aip-nowrap" title={ `${ r.hit_at } UTC` }>{ timeAgo( r.hit_at ) }</td>
												<td>{ bot ? bot.name : `#${ r.bot_id }` }</td>
												<td className="aip-url">{ r.url }</td>
												<td><Status code={ r.status } />{ r.is_cached && <span className="aip-muted" title={ __( 'Served from a page cache', 'ai-parseable' ) }> ⚡</span> }</td>
												<td>{ r.verified ? <span className="aip-ok">✓</span> : <span className="aip-muted">—</span> }</td>
											</tr>
										);
									} ) }
								</tbody>
							</table>
						) }
						{ urls && <More k="recent" list={ urls.recent } n={ 20 } /> }
					</section>

					<p className="aip-footnote">{ sprintf(
						/* translators: 1: row count, 2: size, 3: aggregate row count */
						__( 'Storage: %1$s raw rows (%2$s), %3$s daily aggregate rows kept indefinitely.', 'ai-parseable' ), formatNumber( stats.sizes.hits.rows ), formatBytes( stats.sizes.hits.bytes ), formatNumber( stats.sizes.daily.rows ) ) }</p>
				</>
			) }
		</div>
	);
}
