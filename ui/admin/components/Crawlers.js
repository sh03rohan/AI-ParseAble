import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { api, formatNumber, timeAgo } from '../api';
import { Panel, Segmented, Badge, Callout, SaveBar, Code } from './ui';

const RULES = [
	{ value: 'none', label: __( 'No rule', 'crawlledger-ai-crawler-log' ), tone: 'neutral' },
	{ value: 'allow', label: __( 'Allow', 'crawlledger-ai-crawler-log' ), tone: 'ok' },
	{ value: 'block', label: __( 'Block', 'crawlledger-ai-crawler-log' ), tone: 'err' },
];

const GROUPS = [
	{
		key: 'answer',
		types: [ 'answer', 'search' ],
		title: __( 'Answer & search crawlers', 'crawlledger-ai-crawler-log' ),
		blurb: __( 'Fetch pages to build answers or search results in real time. Blocking one removes your site from that product.', 'crawlledger-ai-crawler-log' ),
	},
	{
		key: 'training',
		types: [ 'training' ],
		title: __( 'Training crawlers', 'crawlledger-ai-crawler-log' ),
		blurb: __( 'Collect content for model training. Blocking one does not change whether AI products cite or link you.', 'crawlledger-ai-crawler-log' ),
	},
];

function Group( { group, bots, rules, seen, onRule } ) {
	const setAll = ( v ) => bots.forEach( ( b ) => onRule( b.id, v ) );
	return (
		<Panel title={ group.title } aside={ <span className="clg-group-actions">{ __( 'Set all:', 'crawlledger-ai-crawler-log' ) } <button type="button" className="clg-link" onClick={ () => setAll( 'allow' ) }>{ __( 'allow', 'crawlledger-ai-crawler-log' ) }</button> · <button type="button" className="clg-link" onClick={ () => setAll( 'block' ) }>{ __( 'block', 'crawlledger-ai-crawler-log' ) }</button> · <button type="button" className="clg-link" onClick={ () => setAll( 'none' ) }>{ __( 'clear', 'crawlledger-ai-crawler-log' ) }</button></span> }>
			<p className="clg-blurb">{ group.blurb }</p>
			<table className="clg-table clg-rules">
				<thead><tr><th>{ __( 'Crawler', 'crawlledger-ai-crawler-log' ) }</th><th>{ __( 'Type', 'crawlledger-ai-crawler-log' ) }</th><th>{ __( 'robots.txt token', 'crawlledger-ai-crawler-log' ) }</th><th>{ __( 'Seen (7 days)', 'crawlledger-ai-crawler-log' ) }</th><th>{ __( 'Rule', 'crawlledger-ai-crawler-log' ) }</th></tr></thead>
				<tbody>
					{ bots.map( ( b ) => {
						const s = seen[ b.id ];
						return (
							<tr key={ b.id }>
								<td><strong>{ b.name }</strong><span className="clg-muted"> { b.vendor }</span>{ b.docs && <> <a className="clg-muted" href={ b.docs } target="_blank" rel="noreferrer">{ __( 'docs ↗', 'crawlledger-ai-crawler-log' ) }</a></> }</td>
								<td><Badge type={ b.type }>{ { answer: __( 'Answer', 'crawlledger-ai-crawler-log' ), search: __( 'Search', 'crawlledger-ai-crawler-log' ), training: __( 'Training', 'crawlledger-ai-crawler-log' ) }[ b.type ] }</Badge></td>
								<td><code className="clg-token">{ b.robots }</code>{ ! b.has_ua && <span className="clg-muted" title={ __( 'A robots.txt-only token: this vendor sends no distinct user agent, so visits appear under its main crawler.', 'crawlledger-ai-crawler-log' ) }> { __( 'token only', 'crawlledger-ai-crawler-log' ) }</span> }</td>
								<td>{ s && s.total > 0 ? <span>{ formatNumber( s.total ) } <span className="clg-muted">· { timeAgo( s.last_seen ) }</span></span> : <span className="clg-muted">{ __( 'not seen', 'crawlledger-ai-crawler-log' ) }</span> }</td>
								<td><Segmented size="small" value={ rules[ b.id ] || 'none' } options={ RULES } onChange={ ( v ) => onRule( b.id, v ) } label={ b.name } /></td>
							</tr>
						);
					} ) }
				</tbody>
			</table>
		</Panel>
	);
}

export default function Crawlers() {
	const [ settings, setSettings ] = useState( null );
	const [ robots, setRobots ] = useState( null );
	const [ seen, setSeen ] = useState( {} );
	const [ rules, setRules ] = useState( {} );
	const [ saved, setSaved ] = useState( {} );
	const [ saving, setSaving ] = useState( false );
	const [ message, setMessage ] = useState( '' );

	const load = () => Promise.all( [ api.get( '/settings' ), api.get( '/robots' ), api.get( '/stats', { range: '7d', verified: 0 } ) ] ).then( ( [ s, r, st ] ) => {
		setSettings( s );
		setRobots( r );
		const map = {};
		st.bots.forEach( ( b ) => {
			map[ b.id ] = b;
		} );
		setSeen( map );
		const initial = {};
		Object.entries( s.robots ).forEach( ( [ id, v ] ) => {
			initial[ id ] = v;
		} );
		setRules( initial );
		setSaved( initial );
	} );
	useEffect( () => {
		load();
	}, [] );

	if ( ! settings || ! robots ) {
		return <div className="clg-loading"><Spinner /></div>;
	}

	const clean = ( r ) => Object.fromEntries( Object.entries( r ).filter( ( [ , v ] ) => v && v !== 'none' ) );
	const dirty = JSON.stringify( clean( rules ) ) !== JSON.stringify( clean( saved ) );
	const onRule = ( id, v ) => setRules( ( prev ) => ( { ...prev, [ id ]: v } ) );

	const save = async () => {
		setSaving( true );
		setMessage( '' );
		try {
			await api.post( '/settings', { robots: clean( rules ) } );
			await load();
			setMessage( __( 'Rules saved and robots.txt updated.', 'crawlledger-ai-crawler-log' ) );
		} catch ( e ) {
			setMessage( e.message );
		} finally {
			setSaving( false );
		}
	};
	const physical = async ( action ) => {
		setSaving( true );
		try {
			setRobots( await api.post( '/robots/physical', { action } ) );
			setMessage( __( 'robots.txt file updated.', 'crawlledger-ai-crawler-log' ) );
		} catch ( e ) {
			setMessage( e.message );
		} finally {
			setSaving( false );
		}
	};

	const blockCount = Object.values( clean( saved ) ).filter( ( v ) => v === 'block' ).length;
	const allowCount = Object.values( clean( saved ) ).filter( ( v ) => v === 'allow' ).length;
	const conflict = ! robots.intact && robots.rules;

	return (
		<div className="clg-crawlers">
			<Callout tone="info">
				<strong>{ __( 'Two different decisions.', 'crawlledger-ai-crawler-log' ) }</strong>{ ' ' }
				{ __( 'Blocking a training crawler does not reduce your presence in AI answers. Blocking an answer or search crawler removes you from them. Every other tool presents these as one switch; they are not.', 'crawlledger-ai-crawler-log' ) }
			</Callout>

			{ robots.subdirectory_site && (
				<Callout tone="warn">
					{ __( 'This site lives in a subdirectory of a network. robots.txt is served per host, so crawlers only ever read the main site\'s file — rules set here are stored but cannot take effect. Set them on the main site instead:', 'crawlledger-ai-crawler-log' ) }{ ' ' }
					<a href={ robots.main_site_url }>{ robots.main_site_url }</a>
				</Callout>
			) }
			{ ! robots.public && (
				<Callout tone="warn">{ __( '"Discourage search engines" is on under Settings → Reading, so WordPress serves a blanket Disallow and these rules are not emitted.', 'crawlledger-ai-crawler-log' ) }</Callout>
			) }
			{ conflict && (
				<Callout tone="error">
					<strong>{ __( 'Another plugin rewrites robots.txt after this one.', 'crawlledger-ai-crawler-log' ) }</strong>{ ' ' }
					{ __( 'The rules that came out are not the rules that went in. Filters on robots_txt:', 'crawlledger-ai-crawler-log' ) }{ ' ' }
					{ robots.other_filters.map( ( f ) => `${ f.name } (priority ${ f.priority })` ).join( ', ' ) || __( 'unknown', 'crawlledger-ai-crawler-log' ) }
				</Callout>
			) }

			{ robots.physical && (
				<Panel tone="warn" title={ __( 'A physical robots.txt exists', 'crawlledger-ai-crawler-log' ) } aside={ robots.physical_managed ? __( 'managed block present', 'crawlledger-ai-crawler-log' ) : __( 'not yet managed', 'crawlledger-ai-crawler-log' ) }>
					<p>{ __( 'WordPress only serves its virtual robots.txt when no file exists at the web root. Because one does, the rules below are ignored unless they are written into that file. The plugin only ever rewrites the block between its own markers and never touches a line outside them.', 'crawlledger-ai-crawler-log' ) }</p>
					<Code max={ 220 }>{ robots.physical_content }</Code>
					<div className="clg-actions">
						<Button variant="primary" disabled={ ! robots.physical_writable || saving || dirty } onClick={ () => physical( 'append' ) }>{ robots.physical_managed ? __( 'Update managed block in file', 'crawlledger-ai-crawler-log' ) : __( 'Write managed block into file', 'crawlledger-ai-crawler-log' ) }</Button>
						{ robots.physical_managed && <Button variant="tertiary" disabled={ saving } onClick={ () => physical( 'remove' ) }>{ __( 'Remove managed block', 'crawlledger-ai-crawler-log' ) }</Button> }
						{ dirty && <span className="clg-muted">{ __( 'Save the rules first.', 'crawlledger-ai-crawler-log' ) }</span> }
						{ ! robots.physical_writable && <span className="clg-muted">{ __( 'The file is not writable by the web server; copy the block below into it by hand.', 'crawlledger-ai-crawler-log' ) }</span> }
					</div>
				</Panel>
			) }

			{ GROUPS.map( ( g ) => (
				<Group key={ g.key } group={ g } bots={ settings.bots.filter( ( b ) => g.types.includes( b.type ) ).sort( ( a, b ) => ( ( seen[ b.id ] || {} ).total || 0 ) - ( ( seen[ a.id ] || {} ).total || 0 ) || a.name.localeCompare( b.name ) ) } rules={ rules } seen={ seen } onRule={ onRule } />
			) ) }

			<Panel title={ __( 'Resulting robots.txt block', 'crawlledger-ai-crawler-log' ) } aside={ <a href={ robots.url } target="_blank" rel="noreferrer">{ __( 'View live robots.txt ↗', 'crawlledger-ai-crawler-log' ) }</a> }>
				<p className="clg-blurb">{ sprintf(
					/* translators: 1: allow count, 2: block count */
					__( 'Saved rules: %1$d allowed, %2$d blocked. Crawlers with no rule fall under your site\'s default (User-agent: *).', 'crawlledger-ai-crawler-log' ), allowCount, blockCount ) }</p>
				<Code>{ robots.rules ? `# BEGIN CrawlLedger\n${ robots.rules }# END CrawlLedger` : __( '(no rules set — nothing is emitted)', 'crawlledger-ai-crawler-log' ) }</Code>
				<p className="clg-muted">{ __( 'CDN and firewall controls (Cloudflare\'s AI-scraper toggle, for example) act above WordPress and are not affected by robots.txt at all. If the log shows a crawler you have allowed getting blocked, look there.', 'crawlledger-ai-crawler-log' ) }</p>
			</Panel>

			<SaveBar dirty={ dirty } saving={ saving } message={ message } onSave={ save } onDiscard={ () => setRules( saved ) } label={ __( 'Save rules', 'crawlledger-ai-crawler-log' ) } />
		</div>
	);
}
