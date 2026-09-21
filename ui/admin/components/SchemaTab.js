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
	none: { name: __( 'None detected', 'ai-parseable' ), hook: 'wp_head (own minimal graph)' },
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
		return <div className="aip-loading"><Spinner /></div>;
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
			setError( e.message || __( 'Preview failed. The site may block loopback requests.', 'ai-parseable' ) );
		} finally {
			setBusy( false );
		}
	};

	const d = preview ? diff( preview.before, preview.after ) : null;

	return (
		<div>
			<Panel title={ __( 'Schema gap filling', 'ai-parseable' ) } aside={ sprintf(
				/* translators: %s: provider name */
				__( 'provider: %s', 'ai-parseable' ), provider.name ) }>
				<div className="aip-split">
					<div>
						<p className="aip-blurb">{ __( 'Merges into the graph your SEO plugin already outputs and fills only properties that are genuinely absent — price, currency, stock, dates, author. Values are read live from the source of truth at render time. It never emits a second Product or Article node.', 'ai-parseable' ) }</p>
						<dl className="aip-facts">
							<dt>{ __( 'Detected provider', 'ai-parseable' ) }</dt><dd>{ provider.name }</dd>
							<dt>{ __( 'Hook used', 'ai-parseable' ) }</dt><dd><code className="aip-token">{ provider.hook }</code></dd>
						</dl>
					</div>
					<div className="aip-split-side">
						<ToggleControl label={ __( 'Enable schema augmentation', 'ai-parseable' ) } checked={ settings.schema_enabled } onChange={ toggle } __nextHasNoMarginBottom />
					</div>
				</div>
			</Panel>

			<Panel title={ __( 'Before / after on a real page', 'ai-parseable' ) } aside={ __( 'fetched from your site, not a generic example', 'ai-parseable' ) }>
				<PagePicker onPick={ run } placeholder={ __( 'Search a page, post or product…', 'ai-parseable' ) } />
				{ busy && <div className="aip-loading"><Spinner /></div> }
				{ error && <Callout tone="error">{ error }</Callout> }
				{ preview && d && (
					<div className="aip-diff">
						<p className="aip-diff-url"><a href={ preview.url } target="_blank" rel="noreferrer">{ preview.url } ↗</a></p>
						<div className="aip-diff-summary">
							<span className="aip-diff-stat"><strong>{ preview.before.length }</strong> { __( 'JSON-LD block(s) before', 'ai-parseable' ) }</span>
							<span className="aip-diff-stat"><strong>{ preview.after.length }</strong> { __( 'after', 'ai-parseable' ) }</span>
							<span className="aip-diff-stat"><strong>{ d.added.length }</strong> { __( 'properties added', 'ai-parseable' ) }</span>
							<span className="aip-diff-stat"><strong>{ countType( preview.after, 'Product' ) }</strong> { __( 'Product node', 'ai-parseable' ) }</span>
						</div>
						{ d.added.length === 0 && d.changed.length === 0 && <Callout tone="ok">{ __( 'Nothing to add on this page — the existing markup already carries everything the plugin knows how to fill.', 'ai-parseable' ) }</Callout> }
						{ d.added.length > 0 && (
							<table className="aip-table aip-table--compact aip-diff-table">
								<thead><tr><th>{ __( 'Added property', 'ai-parseable' ) }</th><th>{ __( 'Value', 'ai-parseable' ) }</th></tr></thead>
								<tbody>{ d.added.map( ( k ) => <tr key={ k }><td><code className="aip-token">{ k }</code></td><td className="aip-url">{ String( d.b[ k ] ) }</td></tr> ) }</tbody>
							</table>
						) }
						{ d.changed.length > 0 && <Callout tone="warn">{ sprintf(
							/* translators: %s: property list */
							__( 'Existing values differ between the two renders (dynamic content?): %s', 'ai-parseable' ), d.changed.join( ', ' ) ) }</Callout> }
						<details className="aip-details">
							<summary>{ __( 'Raw JSON-LD, before and after', 'ai-parseable' ) }</summary>
							<div className="aip-grid-2">
								<div><h3 className="aip-h3">{ __( 'Before', 'ai-parseable' ) }</h3><Code max={ 420 }>{ JSON.stringify( preview.before, null, 2 ) }</Code></div>
								<div><h3 className="aip-h3">{ __( 'After', 'ai-parseable' ) }</h3><Code max={ 420 }>{ JSON.stringify( preview.after, null, 2 ) }</Code></div>
							</div>
						</details>
					</div>
				) }
			</Panel>
		</div>
	);
}
