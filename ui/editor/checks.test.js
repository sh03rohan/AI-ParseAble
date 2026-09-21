import { runChecks } from './checks';

const p = ( content ) => ( { name: 'core/paragraph', attributes: { content }, innerBlocks: [] } );
const h = ( level, content ) => ( { name: 'core/heading', attributes: { level, content }, innerBlocks: [] } );
const img = ( alt ) => ( { name: 'core/image', attributes: { alt }, innerBlocks: [] } );
const long = Array( 320 ).fill( 'word' ).join( ' ' );

describe( 'runChecks', () => {
	it( 'passes a well-formed post', () => {
		const { results, score } = runChecks( [ p( 'Acme Ltd sells 40 kinds of industrial widget from its factory in Leeds.' ), h( 2, 'A' ), h( 3, 'B' ), img( 'x' ), p( long ) ] );
		expect( score ).toBe( 100 );
		expect( results.every( ( r ) => r.ok ) ).toBe( true );
	} );
	it( 'flags multiple H1s and skipped levels', () => {
		const { results } = runChecks( [ h( 1, 'a' ), h( 1, 'b' ), h( 4, 'c' ) ] );
		expect( results.find( ( r ) => r.id === 'h1' ).ok ).toBe( false );
		expect( results.find( ( r ) => r.id === 'levels' ).ok ).toBe( false );
	} );
	it( 'flags a vague opening and missing alt', () => {
		const { results } = runChecks( [ p( 'Welcome to our blog! Have you ever wondered about widgets?' ), img( '' ) ] );
		expect( results.find( ( r ) => r.id === 'opening' ).ok ).toBe( false );
		expect( results.find( ( r ) => r.id === 'alt' ).ok ).toBe( false );
		expect( results.find( ( r ) => r.id === 'length' ).ok ).toBe( false );
	} );
	it( 'walks inner blocks', () => {
		const group = { name: 'core/group', attributes: {}, innerBlocks: [ h( 1, 'a' ), h( 1, 'b' ) ] };
		expect( runChecks( [ group ] ).results.find( ( r ) => r.id === 'h1' ).ok ).toBe( false );
	} );
} );
