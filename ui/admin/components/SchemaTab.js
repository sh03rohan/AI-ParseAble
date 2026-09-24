import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { Spinner, ToggleControl } from '@wordpress/components';
import { api } from '../api';
import { Panel, Callout, Code } from './ui';
import PagePicker from './PagePicker';

const PROVIDER = {
	yoast: { name: 'Yoast SEO', hook: 'wpseo_schema_graph' },
	rankmath: { name: 'Rank Math', hook: 'rank_math/json_ld' },
	woocommerce: { name: 'WooCommerce', hook: 'woocommerce_structured_data_product' },
	none: { name: __( 'None detected', 'crawlledger-ai-crawler-log' ), hook: 'wp_head (own minimal graph)' },
};

/** Flatten JSON-LD into "Type.path" => value so two renders can be compared property by property. */
function flatten( blocks ) {
	const out = {};
	const walk = ( node, prefix ) => {
		if ( Array.isArray( node ) ) {
			node.forEach( ( n ) => walk( n, prefix ) );
			return;
		}
		if ( ! node || typeof node !== 'object' ) {
			return;
		}
		const rawType = node[ '@type' ];
		let type = null;
		if ( rawType ) {
			type = Array.isArray( rawType ) ? rawType[ 0 ] : rawType;
		}
		const base = type ? `${ type }` : prefix;
		Object.entries( node ).forEach( ( [ k, v ] ) => {
			if ( k === '@graph' ) {
				walk( v, base );
			} else if ( v && typeof v === 'object' ) {
				walk( v, `${ base }.${ k }` );
			} else if ( k !== '@type' && k !== '@context' ) {
				out[ `${ base }.${ k }` ] = v;
			}
		} );
	};
	walk( blocks, '' );
	return out;
}

function diff( before, after ) {
	const a = flatten( before );
	const b = flatten( after );
	const added = Object.keys( b ).filter( ( k ) => ! ( k in a ) );
	const changed = Object.keys( b ).filter( ( k ) => k in a && a[ k ] !== b[ k ] );
	return { added, changed, b };
}

function countType( blocks, type ) {
	return Object.keys( flatten( blocks ) ).filter( ( k ) => k.startsWith( `${ type }.` ) ).length > 0 ? 1 : 0;
}

export default function SchemaTab() {
	const [ settings, setSettings ] = useState( null );
	const [ preview, setPreview ] = useState( null );
	const [ page, setPage ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		api.get( '/settings' ).then( setSettings );
	}, [] );
	if ( ! settings ) {
		return <div className="clg-loading"><Spinner /></div>;
	}
	const provider = PROVIDER[ settings.schema_provider ] || PROVIDER.none;

	const toggle = async ( v ) => {
		setSettings( { ...settings, schema_enabled: v } );
		await api.post( '/settings', { schema_enabled: v } );
		if ( page ) {
			run( page );
		}
	};
	const run = async ( p ) => {
		setPage( p );
		setBusy( true );
		setError( '' );
		setPreview( null );
		try {
			setPreview( await api.get( '/schema/preview', { post: p.id } ) );
		} catch ( e ) {
			setError( e.message || __( 'Preview failed. The site may block loopback requests.', 'crawlledger-ai-crawler-log' ) );
		} finally {
			setBusy( false );
		}
	};

	const d = preview ? diff( preview.before, preview.after ) : null;

	return (
		<div>
			<Panel title={ __( 'Schema gap filling', 'crawlledger-ai-crawler-log' ) } aside={ sprintf(
				/* translators: %s: provider name */
				__( 'provider: %s', 'crawlledger-ai-crawler-log' ), provider.name ) }>
				<div className="clg-split">
					<div>
						<p className="clg-blurb">{ __( 'Merges into the graph your SEO plugin already outputs and fills only properties that are genuinely absent — price, currency, stock, dates, author. Values are read live from the source of truth at render time. It never emits a second Product or Article node.', 'crawlledger-ai-crawler-log' ) }</p>
						<dl className="clg-facts">
							<dt>{ __( 'Detected provider', 'crawlledger-ai-crawler-log' ) }</dt><dd>{ provider.name }</dd>
							<dt>{ __( 'Hook used', 'crawlledger-ai-crawler-log' ) }</dt><dd><code className="clg-token">{ provider.hook }</code></dd>
						</dl>
					</div>
					<div className="clg-split-side">
						<ToggleControl label={ __( 'Enable schema augmentation', 'crawlledger-ai-crawler-log' ) } checked={ settings.schema_enabled } onChange={ toggle } __nextHasNoMarginBottom />
					</div>
				</div>
			</Panel>

			<Panel title={ __( 'Before / after on a real page', 'crawlledger-ai-crawler-log' ) } aside={ __( 'fetched from your site, not a generic example', 'crawlledger-ai-crawler-log' ) }>
				<PagePicker onPick={ run } placeholder={ __( 'Search a page, post or product…', 'crawlledger-ai-crawler-log' ) } />
				{ busy && <div className="clg-loading"><Spinner /></div> }
				{ error && <Callout tone="error">{ error }</Callout> }
				{ preview && d && (
					<div className="clg-diff">
						<p className="clg-diff-url"><a href={ preview.url } target="_blank" rel="noreferrer">{ preview.url } ↗</a></p>
						<div className="clg-diff-summary">
							<span className="clg-diff-stat"><strong>{ preview.before.length }</strong> { __( 'JSON-LD block(s) before', 'crawlledger-ai-crawler-log' ) }</span>
							<span className="clg-diff-stat"><strong>{ preview.after.length }</strong> { __( 'after', 'crawlledger-ai-crawler-log' ) }</span>
							<span className="clg-diff-stat"><strong>{ d.added.length }</strong> { __( 'properties added', 'crawlledger-ai-crawler-log' ) }</span>
							<span className="clg-diff-stat"><strong>{ countType( preview.after, 'Product' ) }</strong> { __( 'Product node', 'crawlledger-ai-crawler-log' ) }</span>
						</div>
						{ d.added.length === 0 && d.changed.length === 0 && <Callout tone="ok">{ __( 'Nothing to add on this page — the existing markup already carries everything the plugin knows how to fill.', 'crawlledger-ai-crawler-log' ) }</Callout> }
						{ d.added.length > 0 && (
							<table className="clg-table clg-table--compact clg-diff-table">
								<thead><tr><th>{ __( 'Added property', 'crawlledger-ai-crawler-log' ) }</th><th>{ __( 'Value', 'crawlledger-ai-crawler-log' ) }</th></tr></thead>
								<tbody>{ d.added.map( ( k ) => <tr key={ k }><td><code className="clg-token">{ k }</code></td><td className="clg-url">{ String( d.b[ k ] ) }</td></tr> ) }</tbody>
							</table>
						) }
						{ d.changed.length > 0 && <Callout tone="warn">{ sprintf(
							/* translators: %s: property list */
							__( 'Existing values differ between the two renders (dynamic content?): %s', 'crawlledger-ai-crawler-log' ), d.changed.join( ', ' ) ) }</Callout> }
						<details className="clg-details">
							<summary>{ __( 'Raw JSON-LD, before and after', 'crawlledger-ai-crawler-log' ) }</summary>
							<div className="clg-grid-2">
								<div><h3 className="clg-h3">{ __( 'Before', 'crawlledger-ai-crawler-log' ) }</h3><Code max={ 420 }>{ JSON.stringify( preview.before, null, 2 ) }</Code></div>
								<div><h3 className="clg-h3">{ __( 'After', 'crawlledger-ai-crawler-log' ) }</h3><Code max={ 420 }>{ JSON.stringify( preview.after, null, 2 ) }</Code></div>
							</div>
						</details>
					</div>
				) }
			</Panel>
		</div>
	);
}
