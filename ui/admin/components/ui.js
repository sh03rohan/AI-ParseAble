/**
 * Small shared primitives so every tab speaks the same visual language.
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

export function Panel( { title, aside, tone, children, className = '' } ) {
	return (
		<section className={ `aip-panel ${ tone ? `aip-panel--${ tone }` : '' } ${ className }` }>
			{ ( title || aside ) && (
				<header className="aip-panel-head">
					{ title && <h2>{ title }</h2> }
					{ aside && <span className="aip-muted">{ aside }</span> }
				</header>
			) }
			{ children }
		</section>
	);
}

export function Segmented( { value, options, onChange, size = 'normal', label } ) {
	return (
		<div className={ `aip-segmented aip-segmented--${ size }` } role="group" aria-label={ label }>
			{ options.map( ( o ) => (
				<button key={ o.value } type="button" className={ o.value === value ? `is-active ${ o.tone ? `is-${ o.tone }` : '' }` : '' } disabled={ o.disabled } title={ o.title } onClick={ () => onChange( o.value ) }>{ o.label }</button>
			) ) }
		</div>
	);
}

export function Badge( { type, children, title } ) {
	return <span className={ `aip-badge aip-badge--${ type }` } title={ title }>{ children }</span>;
}

export function Callout( { tone = 'info', children } ) {
	return <div className={ `aip-callout aip-callout--${ tone }` }>{ children }</div>;
}

/**
 * Sticky bar that appears only while there are unsaved changes.
 */
export function SaveBar( { dirty, saving, message, onSave, onDiscard, label } ) {
	if ( ! dirty && ! message ) {
		return null;
	}
	return (
		<div className={ `aip-savebar ${ dirty ? 'is-dirty' : '' }` }>
			<span>{ dirty ? __( 'You have unsaved changes.', 'ai-parseable' ) : message }</span>
			{ dirty && (
				<span className="aip-savebar-actions">
					{ onDiscard && <Button variant="tertiary" onClick={ onDiscard } disabled={ saving }>{ __( 'Discard', 'ai-parseable' ) }</Button> }
					<Button variant="primary" isBusy={ saving } onClick={ onSave }>{ label || __( 'Save changes', 'ai-parseable' ) }</Button>
				</span>
			) }
		</div>
	);
}

export function Code( { children, max } ) {
	return <pre className="aip-pre" style={ max ? { maxHeight: max } : undefined }>{ children }</pre>;
}
