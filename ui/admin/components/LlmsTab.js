import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';
import { Spinner, TextareaControl, ToggleControl } from '@wordpress/components';
import { api } from '../api';
import { Panel, Callout, SaveBar, Code } from './ui';
import PagePicker from './PagePicker';

export default function LlmsTab() {
	const [ saved, setSaved ] = useState( null );
	const [ form, setForm ] = useState( null );
	const [ pages, setPages ] = useState( [] );
	const [ preview, setPreview ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	const timer = useRef( null );

	const snapshot = ( s, p ) => ( { llms_enabled: s.llms_enabled, llms_summary: s.llms_summary, llms_pages: p.map( ( x ) => x.id ), llms_include_posts: s.llms_include_posts } );

	useEffect( () => {
		api.get( '/settings' ).then( async ( s ) => {
			let picked = [];
			if ( s.llms_pages.length ) {
				const all = await api.get( '/pages' );
				picked = s.llms_pages.map( ( id ) => all.find( ( p ) => p.id === id ) || { id, title: `#${ id }` } );
			}
			setPages( picked );
			setForm( { llms_enabled: s.llms_enabled, llms_summary: s.llms_summary, llms_include_posts: s.llms_include_posts, llms_url: s.llms_url } );
			setSaved( snapshot( s, picked ) );
		} );
	}, [] );

	// Debounced live preview of the rendered file from the unsaved values.
	useEffect( () => {
		if ( ! form ) {
			return;
		}
		clearTimeout( timer.current );
		timer.current = setTimeout( () => {
			api.post( '/llms/preview', { llms_summary: form.llms_summary, llms_pages: pages.map( ( p ) => p.id ), llms_include_posts: form.llms_include_posts } ).then( setPreview ).catch( () => null );
		}, 350 );
		return () => clearTimeout( timer.current );
	}, [ form, pages ] );

	if ( ! form || ! saved ) {
		return <div className="clg-loading"><Spinner /></div>;
	}
	const current = snapshot( form, pages );
	const dirty = JSON.stringify( current ) !== JSON.stringify( saved );
	const ready = form.llms_enabled && form.llms_summary.trim() !== '';
	const words = ( form.llms_summary.match( /\S+/g ) || [] ).length;

	const save = async () => {
		setSaving( true );
		setMessage( '' );
		try {
			const s = await api.post( '/settings', current );
			setSaved( snapshot( s, pages ) );
			setMessage( ready ? sprintf(
				/* translators: %s: URL */
				__( 'Saved. Serving at %s', 'crawlledger-ai-crawler-log' ), s.llms_url ) : __( 'Saved. The file is not served until a summary is written.', 'crawlledger-ai-crawler-log' ) );
		} catch ( e ) {
			setMessage( e.message );
		} finally {
			setSaving( false );
		}
	};

	return (
		<div>
			<Callout tone="info">
				<strong>{ __( 'Honest position:', 'crawlledger-ai-crawler-log' ) }</strong>{ ' ' }
				{ __( 'answer crawlers rarely request llms.txt, Google has said it does not use it, and coding agents do read it. Low weight, low cost. The file is served only once you have written a summary in your own words — the plugin will not generate boilerplate, because roughly 40% of llms.txt files in the wild are plugin stubs and that helps nobody.', 'crawlledger-ai-crawler-log' ) }
			</Callout>

			<div className="clg-grid-2 clg-grid-2--wide">
				<div>
					<Panel title={ __( 'Content', 'crawlledger-ai-crawler-log' ) } aside={ ready ? <span className="clg-ok">{ __( '● served', 'crawlledger-ai-crawler-log' ) }</span> : <span className="clg-muted">{ __( '○ not served yet', 'crawlledger-ai-crawler-log' ) }</span> }>
						<ToggleControl label={ __( 'Enable llms.txt', 'crawlledger-ai-crawler-log' ) } checked={ form.llms_enabled } onChange={ ( v ) => setForm( { ...form, llms_enabled: v } ) } __nextHasNoMarginBottom />
						<div className="clg-field">
							<TextareaControl label={ __( 'Summary', 'crawlledger-ai-crawler-log' ) } help={ __( 'One or two short paragraphs of facts: who you are, what you sell or publish, where you operate. Write it as you would explain it to a stranger.', 'crawlledger-ai-crawler-log' ) } value={ form.llms_summary } onChange={ ( v ) => setForm( { ...form, llms_summary: v } ) } rows={ 6 } __nextHasNoMarginBottom />
							<span className="clg-muted clg-count">{ sprintf(
								/* translators: %d: word count */
								__( '%d words', 'crawlledger-ai-crawler-log' ), words ) }</span>
						</div>
						<h3 className="clg-h3">{ __( 'Key pages', 'crawlledger-ai-crawler-log' ) }</h3>
						<p className="clg-muted">{ __( 'The handful of pages an agent should read first. Each is listed with its title and excerpt.', 'crawlledger-ai-crawler-log' ) }</p>
						<ul className="clg-chips">
							{ pages.map( ( p ) => (
								<li key={ p.id }><span>{ p.title }</span> <button type="button" className="clg-chip-x" onClick={ () => setPages( pages.filter( ( x ) => x.id !== p.id ) ) } aria-label={ __( 'Remove', 'crawlledger-ai-crawler-log' ) }>×</button></li>
							) ) }
							{ pages.length === 0 && <li className="clg-chip-empty">{ __( 'none selected', 'crawlledger-ai-crawler-log' ) }</li> }
						</ul>
						<PagePicker onPick={ ( p ) => {
							if ( ! pages.find( ( x ) => x.id === p.id ) ) {
								setPages( [ ...pages, p ] );
							}
						} } placeholder={ __( 'Add a page…', 'crawlledger-ai-crawler-log' ) } />
						<ToggleControl label={ __( 'Also list the 20 most recent posts', 'crawlledger-ai-crawler-log' ) } checked={ form.llms_include_posts } onChange={ ( v ) => setForm( { ...form, llms_include_posts: v } ) } __nextHasNoMarginBottom />
					</Panel>
				</div>
				<div>
					<Panel title={ __( 'Preview', 'crawlledger-ai-crawler-log' ) } aside={ ready ? <a href={ form.llms_url } target="_blank" rel="noreferrer">{ form.llms_url } ↗</a> : <span className="clg-muted">{ form.llms_url }</span> }>
						{ ! preview && <div className="clg-loading"><Spinner /></div> }
						{ preview && ! preview.ready && <p className="clg-muted">{ __( 'Write a summary to see the file.', 'crawlledger-ai-crawler-log' ) }</p> }
						{ preview && preview.ready && <Code max={ 520 }>{ preview.text }</Code> }
						{ preview && preview.ready && dirty && <p className="clg-muted">{ __( 'Preview reflects unsaved changes.', 'crawlledger-ai-crawler-log' ) }</p> }
					</Panel>
				</div>
			</div>

			<SaveBar dirty={ dirty } saving={ saving } message={ message } onSave={ save } onDiscard={ () => {
				setForm( { ...form, ...saved } );
			} } />
		</div>
	);
}
