import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { api, formatNumber, timeAgo } from '../api';
import { Panel, Segmented, Badge, Callout, SaveBar, Code } from './ui';

const RULES = [
	{ value: 'none', label: __( 'No rule', 'ai-parseable' ), tone: 'neutral' },
	{ value: 'allow', label: __( 'Allow', 'ai-parseable' ), tone: 'ok' },
	{ value: 'block', label: __( 'Block', 'ai-parseable' ), tone: 'err' },
];

const GROUPS = [
	{
		key: 'answer',
		types: [ 'answer', 'search' ],
		title: __( 'Answer & search crawlers', 'ai-parseable' ),
		blurb: __( 'Fetch pages to build answers or search results in real time. Blocking one removes your site from that product.', 'ai-parseable' ),
	},
	{
		key: 'training',
		types: [ 'training' ],
		title: __( 'Training crawlers', 'ai-parseable' ),
		blurb: __( 'Collect content for model training. Blocking one does not change whether AI products cite or link you.', 'ai-parseable' ),
	},
];

function Group( { group, bots, rules, seen, onRule } ) {
	const setAll = ( v ) => bots.forEach( ( b ) => onRule( b.id, v ) );
	return (
		<Panel title={ group.title } aside={ <span className="aip-group-actions">{ __( 'Set all:', 'ai-parseable' ) } <button type="button" className="aip-link" onClick={ () => setAll( 'allow' ) }>{ __( 'allow', 'ai-parseable' ) }</button> · <button type="button" className="aip-link" onClick={ () => setAll( 'block' ) }>{ __( 'block', 'ai-parseable' ) }</button> · <button type="button" className="aip-link" onClick={ () => setAll( 'none' ) }>{ __( 'clear', 'ai-parseable' ) }</button></span> }>
			<p className="aip-blurb">{ group.blurb }</p>
			<table className="aip-table aip-rules">
				<thead><tr><th>{ __( 'Crawler', 'ai-parseable' ) }</th><th>{ __( 'Type', 'ai-parseable' ) }</th><th>{ __( 'robots.txt token', 'ai-parseable' ) }</th><th>{ __( 'Seen (7 days)', 'ai-parseable' ) }</th><th>{ __( 'Rule', 'ai-parseable' ) }</th></tr></thead>
				<tbody>
					{ bots.map( ( b ) => {
						const s = seen[ b.id ];
						return (
							<tr key={ b.id }>
								<td><strong>{ b.name }</strong><span className="aip-muted"> { b.vendor }</span>{ b.docs && <> <a className="aip-muted" href={ b.docs } target="_blank" rel="noreferrer">{ __( 'docs ↗', 'ai-parseable' ) }</a></> }</td>
								<td><Badge type={ b.type }>{ { answer: __( 'Answer', 'ai-parseable' ), search: __( 'Search', 'ai-parseable' ), training: __( 'Training', 'ai-parseable' ) }[ b.type ] }</Badge></td>
								<td><code className="aip-token">{ b.robots }</code>{ ! b.has_ua && <span className="aip-muted" title={ __( 'A robots.txt-only token: this vendor sends no distinct user agent, so visits appear under its main crawler.', 'ai-parseable' ) }> { __( 'token only', 'ai-parseable' ) }</span> }</td>
								<td>{ s && s.total > 0 ? <span>{ formatNumber( s.total ) } <span className="aip-muted">· { timeAgo( s.last_seen ) }</span></span> : <span className="aip-muted">{ __( 'not seen', 'ai-parseable' ) }</span> }</td>
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
		return <div className="aip-loading"><Spinner /></div>;
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
			setMessage( __( 'Rules saved and robots.txt updated.', 'ai-parseable' ) );
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
			setMessage( __( 'robots.txt file updated.', 'ai-parseable' ) );
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
		<div className="aip-crawlers">
			<Callout tone="info">
				<strong>{ __( 'Two different decisions.', 'ai-parseable' ) }</strong>{ ' ' }
				{ __( 'Blocking a training crawler does not reduce your presence in AI answers. Blocking an answer or search crawler removes you from them. Every other tool presents these as one switch; they are not.', 'ai-parseable' ) }
			</Callout>

			{ robots.subdirectory_site && (
				<Callout tone="warn">
					{ __( 'This site lives in a subdirectory of a network. robots.txt is served per host, so crawlers only ever read the main site\'s file — rules set here are stored but cannot take effect. Set them on the main site instead:', 'ai-parseable' ) }{ ' ' }
					<a href={ robots.main_site_url }>{ robots.main_site_url }</a>
				</Callout>
			) }
			{ ! robots.public && (
				<Callout tone="warn">{ __( '"Discourage search engines" is on under Settings → Reading, so WordPress serves a blanket Disallow and these rules are not emitted.', 'ai-parseable' ) }</Callout>
			) }
			{ conflict && (
				<Callout tone="error">
					<strong>{ __( 'Another plugin rewrites robots.txt after this one.', 'ai-parseable' ) }</strong>{ ' ' }
					{ __( 'The rules that came out are not the rules that went in. Filters on robots_txt:', 'ai-parseable' ) }{ ' ' }
					{ robots.other_filters.map( ( f ) => `${ f.name } (priority ${ f.priority })` ).join( ', ' ) || __( 'unknown', 'ai-parseable' ) }
				</Callout>
			) }

			{ robots.physical && (
				<Panel tone="warn" title={ __( 'A physical robots.txt exists', 'ai-parseable' ) } aside={ robots.physical_managed ? __( 'managed block present', 'ai-parseable' ) : __( 'not yet managed', 'ai-parseable' ) }>
					<p>{ __( 'WordPress only serves its virtual robots.txt when no file exists at the web root. Because one does, the rules below are ignored unless they are written into that file. The plugin only ever rewrites the block between its own markers and never touches a line outside them.', 'ai-parseable' ) }</p>
					<Code max={ 220 }>{ robots.physical_content }</Code>
					<div className="aip-actions">
						<Button variant="primary" disabled={ ! robots.physical_writable || saving || dirty } onClick={ () => physical( 'append' ) }>{ robots.physical_managed ? __( 'Update managed block in file', 'ai-parseable' ) : __( 'Write managed block into file', 'ai-parseable' ) }</Button>
						{ robots.physical_managed && <Button variant="tertiary" disabled={ saving } onClick={ () => physical( 'remove' ) }>{ __( 'Remove managed block', 'ai-parseable' ) }</Button> }
						{ dirty && <span className="aip-muted">{ __( 'Save the rules first.', 'ai-parseable' ) }</span> }
						{ ! robots.physical_writable && <span className="aip-muted">{ __( 'The file is not writable by the web server; copy the block below into it by hand.', 'ai-parseable' ) }</span> }
					</div>
				</Panel>
			) }

			{ GROUPS.map( ( g ) => (
				<Group key={ g.key } group={ g } bots={ settings.bots.filter( ( b ) => g.types.includes( b.type ) ).sort( ( a, b ) => ( ( seen[ b.id ] || {} ).total || 0 ) - ( ( seen[ a.id ] || {} ).total || 0 ) || a.name.localeCompare( b.name ) ) } rules={ rules } seen={ seen } onRule={ onRule } />
			) ) }

			<Panel title={ __( 'Resulting robots.txt block', 'ai-parseable' ) } aside={ <a href={ robots.url } target="_blank" rel="noreferrer">{ __( 'View live robots.txt ↗', 'ai-parseable' ) }</a> }>
				<p className="aip-blurb">{ sprintf(
					/* translators: 1: allow count, 2: block count */
					__( 'Saved rules: %1$d allowed, %2$d blocked. Crawlers with no rule fall under your site\'s default (User-agent: *).', 'ai-parseable' ), allowCount, blockCount ) }</p>
				<Code>{ robots.rules ? `# BEGIN AI ParseAble\n${ robots.rules }# END AI ParseAble` : __( '(no rules set — nothing is emitted)', 'ai-parseable' ) }</Code>
				<p className="aip-muted">{ __( 'CDN and firewall controls (Cloudflare\'s AI-scraper toggle, for example) act above WordPress and are not affected by robots.txt at all. If the log shows a crawler you have allowed getting blocked, look there.', 'ai-parseable' ) }</p>
			</Panel>

			<SaveBar dirty={ dirty } saving={ saving } message={ message } onSave={ save } onDiscard={ () => setRules( saved ) } label={ __( 'Save rules', 'ai-parseable' ) } />
		</div>
	);
}
