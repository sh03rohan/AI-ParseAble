import apiFetch from '@wordpress/api-fetch';

const NS = '/crawlledger/v1';

export const api = {
	get: ( route, query = {} ) => {
		// Sorted keys so the path matches the server-side preload for the same request.
		const qs = Object.keys( query ).sort().map( ( k ) => `${ encodeURIComponent( k ) }=${ encodeURIComponent( query[ k ] ) }` ).join( '&' );
		return apiFetch( { path: `${ NS }${ route }${ qs ? `?${ qs }` : '' }` } );
	},
	post: ( route, data = {} ) => apiFetch( { path: `${ NS }${ route }`, method: 'POST', data } ),
};

export function formatBytes( bytes ) {
	if ( ! bytes ) {
		return '0 B';
	}
	const units = [ 'B', 'KB', 'MB', 'GB' ];
	const i = Math.min( units.length - 1, Math.floor( Math.log( bytes ) / Math.log( 1024 ) ) );
	return `${ ( bytes / Math.pow( 1024, i ) ).toFixed( i === 0 ? 0 : 1 ) } ${ units[ i ] }`;
}

export function formatNumber( n ) {
	return new Intl.NumberFormat().format( n || 0 );
}

export function timeAgo( value ) {
	if ( ! value ) {
		return '';
	}
	const then = typeof value === 'number' ? value * 1000 : Date.parse( `${ value.replace( ' ', 'T' ) }Z` );
	if ( Number.isNaN( then ) ) {
		return value;
	}
	const s = Math.max( 0, Math.round( ( Date.now() - then ) / 1000 ) );
	if ( s < 60 ) {
		return 'just now';
	}
	if ( s < 3600 ) {
		return `${ Math.round( s / 60 ) } min ago`;
	}
	if ( s < 86400 ) {
		return `${ Math.round( s / 3600 ) } h ago`;
	}
	return `${ Math.round( s / 86400 ) } d ago`;
}
