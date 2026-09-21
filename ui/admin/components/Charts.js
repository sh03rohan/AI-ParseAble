/**
 * Hand-written SVG charts: an area chart with a hover crosshair, a sparkline and a proportion bar.
 * Still well under what a charting dependency would cost.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { formatNumber } from '../api';

function niceMax( max ) {
	if ( max <= 10 ) {
		return 10;
	}
	const exp = Math.pow( 10, Math.floor( Math.log10( max ) ) );
	const n = max / exp;
	const steps = [ 1, 2, 5, 10 ];
	const step = steps.find( ( candidate ) => n <= candidate );
	return step * exp;
}

function anchorFor( i, count ) {
	if ( i === count - 1 ) {
		return 'end';
	}
	return i === 0 ? 'start' : 'middle';
}

function shortDay( day ) {
	const d = new Date( `${ day }T00:00:00Z` );
	return d.toLocaleDateString( undefined, { month: 'short', day: 'numeric', timeZone: 'UTC' } );
}

/** Track the rendered width so the SVG is drawn 1:1 — no scaled-up type or strokes on wide screens. */
function useWidth( fallback ) {
	const ref = useRef( null );
	const [ width, setWidth ] = useState( fallback );
	useEffect( () => {
		if ( ! ref.current || typeof ResizeObserver === 'undefined' ) {
			return;
		}
		const ro = new ResizeObserver( ( entries ) => {
			const w = Math.round( entries[ 0 ].contentRect.width );
			if ( w > 0 ) {
				setWidth( w );
			}
		} );
		ro.observe( ref.current );
		return () => ro.disconnect();
	}, [] );
	return [ ref, width ];
}

export function AreaChart( { series, height = 220 } ) {
	const [ hover, setHover ] = useState( null );
	const [ wrapRef, width ] = useWidth( 800 );
	const pad = { top: 12, right: 12, bottom: 26, left: 44 };
	const innerW = width - pad.left - pad.right;
	const innerH = height - pad.top - pad.bottom;
	if ( ! series || ! series.length ) {
		return null;
	}
	const max = niceMax( Math.max( 1, ...series.map( ( p ) => p.hits ) ) );
	const y = ( v ) => pad.top + innerH - ( ( v / max ) * innerH );
	const step = series.length > 1 ? innerW / ( series.length - 1 ) : innerW;
	const x = ( i ) => pad.left + ( i * step );
	const line = ( key ) => series.map( ( p, i ) => `${ i === 0 ? 'M' : 'L' }${ x( i ) },${ y( p[ key ] ) }` ).join( ' ' );
	const area = ( key ) => `${ line( key ) } L${ x( series.length - 1 ) },${ pad.top + innerH } L${ x( 0 ) },${ pad.top + innerH } Z`;
	const ticks = [ 0, 0.25, 0.5, 0.75, 1 ].map( ( f ) => Math.round( max * f ) );
	const labelEvery = Math.max( 1, Math.ceil( series.length / Math.max( 4, Math.floor( innerW / 90 ) ) ) );

	const onMove = ( e ) => {
		const rect = e.currentTarget.getBoundingClientRect();
		const px = ( ( e.clientX - rect.left ) / rect.width ) * width;
		const i = Math.max( 0, Math.min( series.length - 1, Math.round( ( px - pad.left ) / step ) ) );
		setHover( i );
	};
	const h = hover === null ? null : series[ hover ];

	return (
		<div className="aip-chart-wrap" ref={ wrapRef }>
			<svg viewBox={ `0 0 ${ width } ${ height }` } width={ width } height={ height } className="aip-chart" role="img" aria-label="Crawler hits per day" onMouseMove={ onMove } onMouseLeave={ () => setHover( null ) }>
				<defs>
					<linearGradient id="aip-g-hits" x1="0" x2="0" y1="0" y2="1"><stop offset="0" className="aip-g-hits-a" /><stop offset="1" className="aip-g-hits-b" /></linearGradient>
					<linearGradient id="aip-g-err" x1="0" x2="0" y1="0" y2="1"><stop offset="0" className="aip-g-err-a" /><stop offset="1" className="aip-g-err-b" /></linearGradient>
				</defs>
				{ ticks.map( ( t ) => (
					<g key={ t }>
						<line x1={ pad.left } x2={ width - pad.right } y1={ y( t ) } y2={ y( t ) } className="aip-grid" />
						<text x={ pad.left - 8 } y={ y( t ) + 4 } textAnchor="end" className="aip-tick">{ formatNumber( t ) }</text>
					</g>
				) ) }
				<path d={ area( 'hits' ) } className="aip-area" />
				<path d={ area( 'errors' ) } className="aip-area-errors" />
				<path d={ line( 'hits' ) } className="aip-line" />
				<path d={ line( 'errors' ) } className="aip-line-errors" />
				{ series.map( ( p, i ) =>
					i % labelEvery === 0 || i === series.length - 1 ? (
						<text key={ p.day } x={ x( i ) } y={ height - 8 } textAnchor={ anchorFor( i, series.length ) } className="aip-tick">{ shortDay( p.day ) }</text>
					) : null
				) }
				{ h && (
					<g>
						<line x1={ x( hover ) } x2={ x( hover ) } y1={ pad.top } y2={ pad.top + innerH } className="aip-crosshair" />
						<circle cx={ x( hover ) } cy={ y( h.hits ) } r="4.5" className="aip-dot" />
						{ h.errors > 0 && <circle cx={ x( hover ) } cy={ y( h.errors ) } r="4.5" className="aip-dot-errors" /> }
					</g>
				) }
			</svg>
			{ h && (
				<div className="aip-tooltip" style={ { left: `${ ( x( hover ) / width ) * 100 }%` } }>
					<strong>{ shortDay( h.day ) }</strong>
					<span><i className="aip-swatch aip-swatch-hits" />{ formatNumber( h.hits ) } hits</span>
					<span><i className="aip-swatch aip-swatch-errors" />{ formatNumber( h.errors ) } errors</span>
				</div>
			) }
			<div className="aip-legend">
				<span><i className="aip-swatch aip-swatch-hits" />Hits</span>
				<span><i className="aip-swatch aip-swatch-errors" />4xx / 5xx</span>
			</div>
		</div>
	);
}

export function Sparkline( { values, width = 110, height = 26 } ) {
	if ( ! values || ! values.length ) {
		return null;
	}
	const max = Math.max( 1, ...values );
	const step = values.length > 1 ? width / ( values.length - 1 ) : width;
	const pts = values.map( ( v, i ) => `${ i * step },${ 2 + ( height - 4 ) - ( ( v / max ) * ( height - 4 ) ) }` );
	const d = pts.map( ( p, i ) => `${ i === 0 ? 'M' : 'L' }${ p }` ).join( ' ' );
	return (
		<svg viewBox={ `0 0 ${ width } ${ height }` } width={ width } height={ height } className="aip-spark" aria-hidden="true">
			<path d={ `${ d } L${ width },${ height } L0,${ height } Z` } className="aip-spark-area" />
			<path d={ d } className="aip-spark-line" />
		</svg>
	);
}

export function Proportion( { value, total, title } ) {
	const pct = total > 0 ? Math.round( ( value / total ) * 100 ) : 0;
	return (
		<span className="aip-proportion" title={ title }>
			<span className="aip-proportion-track"><span className="aip-proportion-fill" style={ { width: `${ pct }%` } } /></span>
			<span className="aip-proportion-label">{ pct }%</span>
		</span>
	);
}
