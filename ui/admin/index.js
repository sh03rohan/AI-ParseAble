import { createRoot, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TabPanel } from '@wordpress/components';
import Overview from './components/Overview';
import Crawlers from './components/Crawlers';
import SchemaTab from './components/SchemaTab';
import LlmsTab from './components/LlmsTab';
import SettingsTab from './components/SettingsTab';
import './admin.scss';

const TABS = [
	{ name: 'overview', title: __( 'Overview', 'crawlledger-ai-crawler-log' ), Component: Overview },
	{ name: 'crawlers', title: __( 'Crawlers', 'crawlledger-ai-crawler-log' ), Component: Crawlers },
	{ name: 'schema', title: __( 'Schema', 'crawlledger-ai-crawler-log' ), Component: SchemaTab },
	{ name: 'llms', title: __( 'llms.txt', 'crawlledger-ai-crawler-log' ), Component: LlmsTab },
	{ name: 'settings', title: __( 'Settings', 'crawlledger-ai-crawler-log' ), Component: SettingsTab },
];

function App() {
	const initial = ( window.location.hash || '#overview' ).slice( 1 );
	const [ tab, setTab ] = useState( TABS.some( ( t ) => t.name === initial ) ? initial : 'overview' );
	return (
		<div className="clg-app">
			<TabPanel
				className="clg-tabs"
				tabs={ TABS }
				initialTabName={ tab }
				onSelect={ ( name ) => {
					setTab( name );
					window.location.hash = name;
				} }
			>
				{ ( t ) => {
					const Component = TABS.find( ( x ) => x.name === t.name ).Component;
					return <div className="clg-main"><Component /></div>;
				} }
			</TabPanel>
			<div className="clg-brand" aria-hidden="true">
				<img className="clg-brand-mark" src={ window.crawlLedger ? window.crawlLedger.logo : '' } alt="" width="28" height="28" />
				<span className="clg-brand-name">{ __( 'CrawlLedger', 'crawlledger-ai-crawler-log' ) }</span>
				<span className="clg-version">v{ window.crawlLedger ? window.crawlLedger.version : '' }</span>
			</div>
		</div>
	);
}

const mount = document.getElementById( 'crawlledger-app' );
if ( mount ) {
	createRoot( mount ).render( <App /> );
}
