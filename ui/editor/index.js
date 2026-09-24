import { registerPlugin } from '@wordpress/plugins';
import * as editor from '@wordpress/editor';
import * as editPost from '@wordpress/edit-post';
import { useSelect } from '@wordpress/data';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { runChecks } from './checks';
import './editor.scss';

// PluginSidebar moved from @wordpress/edit-post to @wordpress/editor in WordPress 6.6.
const { PluginSidebar, PluginSidebarMoreMenuItem } = editor.PluginSidebar ? editor : editPost;

const ICON = (
	<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 5c-5 0-9 4.5-10 7 1 2.5 5 7 10 7s9-4.5 10-7c-1-2.5-5-7-10-7zm0 11.5a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9zm0-7a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z" /></svg>
);

function Panel() {
	const blocks = useSelect( ( select ) => select( 'core/block-editor' ).getBlocks(), [] );
	const { results, score } = runChecks( blocks );
	return (
		<PanelBody title={ __( 'AI clarity checks', 'crawlledger-ai-crawler-log' ) } initialOpen>
			<p className="clg-ed-score"><strong>{ score }%</strong> { __( 'of checks pass', 'crawlledger-ai-crawler-log' ) }</p>
			<ul className="clg-ed-list">
				{ results.map( ( r ) => (
					<li key={ r.id } className={ `clg-ed-${ r.level }` }>{ r.label }</li>
				) ) }
			</ul>
			<p className="clg-ed-note">{ __( 'Checks run in the editor as you type; nothing is sent anywhere.', 'crawlledger-ai-crawler-log' ) }</p>
		</PanelBody>
	);
}

registerPlugin( 'crawlledger', {
	icon: ICON,
	render: () => (
		<>
			<PluginSidebarMoreMenuItem target="crawlledger">{ __( 'CrawlLedger', 'crawlledger-ai-crawler-log' ) }</PluginSidebarMoreMenuItem>
			<PluginSidebar name="crawlledger" title={ __( 'CrawlLedger', 'crawlledger-ai-crawler-log' ) }>
				<Panel />
			</PluginSidebar>
		</>
	),
} );
