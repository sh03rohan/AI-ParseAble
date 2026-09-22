import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { Spinner, TextControl, ToggleControl } from '@wordpress/components';
import { api } from '../api';
import { Panel, Segmented, SaveBar } from './ui';

function Choice( { name, value, current, title, desc, onChange } ) {
	const id = `aip-${ name }-${ value }`;
	return (
		<label htmlFor={ id } className={ `aip-choice ${ current === value ? 'is-selected' : '' }` }>
			<input id={ id } type="radio" name={ name } value={ value } checked={ current === value } onChange={ () => onChange( value ) } />
			<strong>{ title }</strong>
			<span className="aip-muted">{ desc }</span>
		</label>
	);
}

export default function SettingsTab() {
	const [ saved, setSaved ] = useState( null );
	const [ form, setForm ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ message, setMessage ] = useState( '' );

	const pick = ( s ) => ( { retention_days: s.retention_days, ip_mode: s.ip_mode, rate_cap_per_minute: s.rate_cap_per_minute, keep_data_on_uninstall: s.keep_data_on_uninstall } );
	useEffect( () => {
		api.get( '/settings' ).then( ( s ) => {
			setSaved( { ...pick( s ), retention_choices: s.retention_choices } ); setForm( pick( s ) );
		} );
	}, [] );
	if ( ! form || ! saved ) {
		return <div className="aip-loading"><Spinner /></div>;
	}
	const dirty = JSON.stringify( form ) !== JSON.stringify( pick( saved ) );

	const save = async () => {
		setSaving( true );
		setMessage( '' );
		try {
			const s = await api.post( '/settings', form );
			setSaved( { ...pick( s ), retention_choices: s.retention_choices } );
			setForm( pick( s ) );
			setMessage( __( 'Settings saved.', 'ai-parseable' ) );
		} catch ( e ) {
			setMessage( e.message );
		} finally {
			setSaving( false );
		}
	};

	const retention = [ 7, 30, 90 ].map( ( d ) => ( {
		value: d,
		label: sprintf(
			/* translators: %d: days */
			__( '%d days', 'ai-parseable' ), d ) + ( saved.retention_choices.includes( d ) ? '' : ' 🔒' ),
		disabled: ! saved.retention_choices.includes( d ),
		title: saved.retention_choices.includes( d ) ? '' : __( 'Longer retention is part of the add-on', 'ai-parseable' ),
	} ) );

	return (
		<div>
			<Panel title={ __( 'Logging', 'ai-parseable' ) }>
				<div className="aip-field">
					<span className="aip-label">{ __( 'Keep individual visits for', 'ai-parseable' ) }</span>
					<Segmented value={ form.retention_days } options={ retention } onChange={ ( v ) => setForm( { ...form, retention_days: v } ) } label={ __( 'Retention', 'ai-parseable' ) } />
					<span className="aip-muted">{ __( 'Daily totals per crawler are kept indefinitely regardless; only the per-request rows expire.', 'ai-parseable' ) }</span>
				</div>
				<div className="aip-field">
					<span className="aip-label">{ __( 'IP address storage', 'ai-parseable' ) }</span>
					<div className="aip-choices">
						<Choice name="ip" value="truncated" current={ form.ip_mode } onChange={ ( v ) => setForm( { ...form, ip_mode: v } ) } title={ __( 'Truncated', 'ai-parseable' ) } desc={ __( 'Keeps /24 (IPv4) or /48 (IPv6). Default; enough to tell networks apart.', 'ai-parseable' ) } />
						<Choice name="ip" value="hashed" current={ form.ip_mode } onChange={ ( v ) => setForm( { ...form, ip_mode: v } ) } title={ __( 'Salted hash', 'ai-parseable' ) } desc={ __( 'One-way, per-site salt. Repeat visits still correlate; the address cannot be recovered.', 'ai-parseable' ) } />
						<Choice name="ip" value="full" current={ form.ip_mode } onChange={ ( v ) => setForm( { ...form, ip_mode: v } ) } title={ __( 'Full address', 'ai-parseable' ) } desc={ __( 'Personal data under GDPR — mention it in your privacy policy.', 'ai-parseable' ) } />
					</div>
					<span className="aip-muted">{ __( 'Verification always runs on the real address before it is reduced for storage.', 'ai-parseable' ) }</span>
				</div>
				<div className="aip-field aip-field--inline">
					<TextControl type="number" min={ 10 } max={ 100000 } label={ __( 'Per-crawler ceiling, visits per minute', 'ai-parseable' ) } value={ form.rate_cap_per_minute } onChange={ ( v ) => setForm( { ...form, rate_cap_per_minute: parseInt( v || '0', 10 ) } ) } __nextHasNoMarginBottom />
					<span className="aip-muted">{ __( 'Above this, visits are still counted in daily totals but per-request rows are not stored, so a flood cannot turn the log into an amplifier.', 'ai-parseable' ) }</span>
				</div>
			</Panel>

			<Panel title={ __( 'Uninstall', 'ai-parseable' ) }>
				<ToggleControl label={ __( 'Keep my data when the plugin is deleted', 'ai-parseable' ) } help={ form.keep_data_on_uninstall ? __( 'Tables, settings and the log directory survive deletion; only the cron events are removed.', 'ai-parseable' ) : __( 'Deleting the plugin removes its tables, settings, cron events and the log directory. This cannot be undone.', 'ai-parseable' ) } checked={ form.keep_data_on_uninstall } onChange={ ( v ) => setForm( { ...form, keep_data_on_uninstall: v } ) } __nextHasNoMarginBottom />
			</Panel>

			<SaveBar dirty={ dirty } saving={ saving } message={ message } onSave={ save } onDiscard={ () => {
				setForm( pick( saved ) );
			} } />
		</div>
	);
}
