/**
 * Custom webpack config that extends @wordpress/scripts's default and adds
 * monaco-editor-webpack-plugin for the lagoon-viewer block build.
 *
 * The plugin properly initializes Monaco's theme service, registers all the
 * language tokenizers, sets up web workers, and emits the `monaco-colors`
 * style tag with `.mtk*` token-color rules — none of which happens when you
 * `import 'monaco-editor'` directly with a raw webpack setup.
 *
 * wp-scripts loads this file for every `npm run build:scripts:*` invocation.
 * The plugin is a no-op for builds that don't import monaco-editor (theme,
 * features), so it's safe to apply globally.
 */

// eslint-disable-next-line no-console
console.log( '[codelag] webpack.config.js LOADED' );

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const MonacoWebpackPlugin = require( 'monaco-editor-webpack-plugin' );

const monacoPlugin = new MonacoWebpackPlugin( {
	// Only the languages we actually expose in the editor's language picker.
	// Each one bundles its own tokenizer; trimming the list shrinks the build.
	languages: [
		'javascript',
		'typescript',
		'php',
		'sql',
		'shell',
		'python',
		'go',
		'rust',
		'css',
		'scss',
		'html',
		'json',
		'yaml',
		'markdown',
		'xml',
	],
	// Drop a few heavyweight features we don't need for snippet editing.
	features: [
		'!gotoSymbol',
		'!quickOutline',
		'!documentSymbols',
		'!codelens',
		'!inspectTokens',
	],
} );

const addMonacoPlugin = ( config ) => ( {
	...config,
	plugins: [ ...( config.plugins || [] ), monacoPlugin ],
} );

// wp-scripts may export an object or an array of configs depending on version.
module.exports = Array.isArray( defaultConfig )
	? defaultConfig.map( addMonacoPlugin )
	: addMonacoPlugin( defaultConfig );
