import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { SearchControl } from '@wordpress/components';
import { api } from '../api';

/**
 * Debounced search over published pages, posts and products; results appear as a compact list.
 */
export default function PagePicker( { onPick, placeholder } ) {
	const [ search, setSearch ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ open, setOpen ] = useState( false );

	useEffect( () => {
		const t = setTimeout( () => {
			api.get( '/pages', { search } ).then( setResults ).catch( () => setResults( [] ) );
		}, 250 );
		return () => clearTimeout( t );
	}, [ search ] );

	return (
		<div className="clg-picker">
			<SearchControl value={ search } onChange={ ( v ) => {
				setSearch( v ); setOpen( true );
			} } onFocus={ () => setOpen( true ) } placeholder={ placeholder || __( 'Search pages…', 'crawlledger-ai-crawler-log' ) } __nextHasNoMarginBottom />
			{ open && results.length > 0 && (
				<ul className="clg-picker-list">
					{ results.map( ( p ) => (
						<li key={ p.id }>
							<button type="button" className="clg-picker-item" onClick={ () => {
								onPick( p ); setOpen( false ); setSearch( '' );
							} }>
								<span>{ p.title || `#${ p.id }` }</span><span className="clg-muted">{ p.type }</span>
							</button>
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
}
