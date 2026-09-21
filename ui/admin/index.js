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
	{ name: 'overview', title: __( 'Overview', 'ai-parseable' ), Component: Overview },
	{ name: 'crawlers', title: __( 'Crawlers', 'ai-parseable' ), Component: Crawlers },
	{ name: 'schema', title: __( 'Schema', 'ai-parseable' ), Component: SchemaTab },
	{ name: 'llms', title: __( 'llms.txt', 'ai-parseable' ), Component: LlmsTab },
	{ name: 'settings', title: __( 'Settings', 'ai-parseable' ), Component: SettingsTab },
];

function App() {
	const initial = ( window.location.hash || '#overview' ).slice( 1 );
	const [ tab, setTab ] = useState( TABS.some( ( t ) => t.name === initial ) ? initial : 'overview' );
	return (
		<div className="aip-app">
			<TabPanel
				className="aip-tabs"
				tabs={ TABS }
				initialTabName={ tab }
				onSelect={ ( name ) => {
					setTab( name );
					window.location.hash = name;
				} }
			>
				{ ( t ) => {
					const Component = TABS.find( ( x ) => x.name === t.name ).Component;
					return <div className="aip-main"><Component /></div>;
				} }
			</TabPanel>
			<div className="aip-brand" aria-hidden="true">
				<img className="aip-brand-mark" src={ window.aiParseAble ? window.aiParseAble.logo : '' } alt="" width="28" height="28" />
				<span className="aip-brand-name">{ __( 'AI ParseAble', 'ai-parseable' ) }</span>
				<span className="aip-version">v{ window.aiParseAble ? window.aiParseAble.version : '' }</span>
			</div>
		</div>
	);
}

const mount = document.getElementById( 'ai-parseable-app' );
if ( mount ) {
	createRoot( mount ).render( <App /> );
}
