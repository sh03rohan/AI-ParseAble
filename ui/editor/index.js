import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { runChecks } from './checks';
import './editor.scss';

const ICON = (
	<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 5c-5 0-9 4.5-10 7 1 2.5 5 7 10 7s9-4.5 10-7c-1-2.5-5-7-10-7zm0 11.5a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9zm0-7a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z" /></svg>
);

function Panel() {
	const blocks = useSelect( ( select ) => select( 'core/block-editor' ).getBlocks(), [] );
	const { results, score } = runChecks( blocks );
	return (
		<PanelBody title={ __( 'AI clarity checks', 'ai-parseable' ) } initialOpen>
			<p className="aip-ed-score"><strong>{ score }%</strong> { __( 'of checks pass', 'ai-parseable' ) }</p>
			<ul className="aip-ed-list">
				{ results.map( ( r ) => (
					<li key={ r.id } className={ `aip-ed-${ r.level }` }>{ r.label }</li>
				) ) }
			</ul>
			<p className="aip-ed-note">{ __( 'Checks run in the editor as you type; nothing is sent anywhere.', 'ai-parseable' ) }</p>
		</PanelBody>
	);
}

registerPlugin( 'ai-parseable', {
	icon: ICON,
	render: () => (
		<>
			<PluginSidebarMoreMenuItem target="ai-parseable">{ __( 'AI ParseAble', 'ai-parseable' ) }</PluginSidebarMoreMenuItem>
			<PluginSidebar name="ai-parseable" title={ __( 'AI ParseAble', 'ai-parseable' ) }>
				<Panel />
			</PluginSidebar>
		</>
	),
} );
