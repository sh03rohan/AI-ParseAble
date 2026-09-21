/**
 * Deterministic clarity checks over the block list. Pure functions, no WordPress calls, unit-testable.
 * These mirror the checker website's rules so the editor and the scan agree.
 */

function text( html ) {
	return String( html || '' ).replace( /<[^>]+>/g, ' ' ).replace( /&nbsp;/g, ' ' ).replace( /\s+/g, ' ' ).trim();
}

export function flatten( blocks ) {
	const out = [];
	( blocks || [] ).forEach( ( b ) => {
		out.push( b );
		if ( b.innerBlocks && b.innerBlocks.length ) {
			out.push( ...flatten( b.innerBlocks ) );
		}
	} );
	return out;
}

function h1Label( count ) {
	if ( count === 0 ) {
		return 'No H1 in content (the post title is the H1)';
	}
	return count === 1 ? 'One H1 in content' : `${ count } H1 headings — keep one`;
}

function openingResult( first, factual, firstBlock ) {
	if ( ! first ) {
		return { level: 'error', label: 'No opening paragraph' };
	}
	if ( factual ) {
		return { level: 'ok', label: 'Opening paragraph states a fact' };
	}
	const notParagraph = firstBlock && firstBlock.name !== 'core/paragraph';
	return { level: 'warning', label: notParagraph ? 'Content does not open with a paragraph' : 'Opening paragraph should state a concrete fact, not a hook or a question' };
}

function altLabel( total, missing ) {
	if ( total === 0 ) {
		return 'No images';
	}
	return missing === 0 ? `All ${ total } image(s) have alt text` : `${ missing } of ${ total } image(s) missing alt text`;
}

export function runChecks( blocks, options = {} ) {
	const minWords = options.minWords || 300;
	const all = flatten( blocks );
	const headings = all.filter( ( b ) => b.name === 'core/heading' ).map( ( b ) => ( { level: b.attributes.level || 2, text: text( b.attributes.content ) } ) );
	const paragraphs = all.filter( ( b ) => b.name === 'core/paragraph' ).map( ( b ) => text( b.attributes.content ) ).filter( Boolean );
	const images = all.filter( ( b ) => b.name === 'core/image' );
	const words = all.reduce( ( n, b ) => {
		const c = b.attributes && ( b.attributes.content || b.attributes.values || b.attributes.citation || '' );
		return n + ( text( c ).match( /\S+/g ) || [] ).length;
	}, 0 );

	const results = [];

	// 1. Single h1 inside the content. Themes render the title as the h1, so content h1s are usually a mistake.
	const h1s = headings.filter( ( h ) => h.level === 1 ).length;
	results.push( {
		id: 'h1',
		ok: h1s <= 1,
		level: h1s > 1 ? 'error' : 'ok',
		label: h1Label( h1s ),
	} );

	// 2. No skipped heading levels.
	let skipped = null;
	let prev = 1;
	headings.forEach( ( h ) => {
		if ( h.level > prev + 1 && ! skipped ) {
			skipped = `H${ prev } → H${ h.level } ("${ h.text.slice( 0, 40 ) }")`;
		}
		prev = h.level;
	} );
	results.push( { id: 'levels', ok: ! skipped, level: skipped ? 'warning' : 'ok', label: skipped ? `Skipped heading level: ${ skipped }` : 'Heading levels are sequential' } );

	// 3. Opening paragraph states a fact.
	const first = paragraphs[ 0 ] || '';
	const firstBlock = all.find( ( b ) => [ 'core/paragraph', 'core/heading', 'core/image', 'core/cover', 'core/group' ].includes( b.name ) );
	const vague = /^(welcome|hello|hi|today|in this (post|article|guide)|have you ever|imagine|we all know|it'?s no secret)/i.test( first );
	const question = /\?\s*$/.test( first );
	const factual = first.length >= 60 && ! vague && ! question && ( /\d/.test( first ) || /\b(is|are|was|were|has|have|provides|offers|makes|sells|helps|means)\b/i.test( first ) );
	results.push( { id: 'opening', ok: factual, ...openingResult( first, factual, firstBlock ) } );

	// 4. Alt coverage.
	const missingAlt = images.filter( ( b ) => ! text( b.attributes.alt ) ).length;
	results.push( {
		id: 'alt',
		ok: missingAlt === 0,
		level: missingAlt ? 'warning' : 'ok',
		label: altLabel( images.length, missingAlt ),
	} );

	// 5. Minimum length.
	results.push( { id: 'length', ok: words >= minWords, level: words >= minWords ? 'ok' : 'warning', label: `${ words } words (minimum ${ minWords })` } );

	return { results, score: Math.round( ( results.filter( ( r ) => r.ok ).length / results.length ) * 100 ) };
}
