/**
 * Map our taxonomy / stored file-language slugs to Monaco language IDs.
 *
 * File rows store a short slug (e.g. "php", "bash", "ts"). Monaco expects
 * its own identifiers. Any slug not in the map falls through to "plaintext".
 *
 * Kept in a single exported map so the editor and the language picker
 * reference the same canonical list.
 */

export const LANGUAGE_OPTIONS = [
	{ value: 'plaintext', label: 'Plain text' },
	{ value: 'php', label: 'PHP' },
	{ value: 'javascript', label: 'JavaScript' },
	{ value: 'typescript', label: 'TypeScript' },
	{ value: 'html', label: 'HTML' },
	{ value: 'css', label: 'CSS' },
	{ value: 'scss', label: 'SCSS' },
	{ value: 'json', label: 'JSON' },
	{ value: 'yaml', label: 'YAML' },
	{ value: 'markdown', label: 'Markdown' },
	{ value: 'shell', label: 'Shell / Bash' },
	{ value: 'sql', label: 'SQL' },
	{ value: 'python', label: 'Python' },
	{ value: 'go', label: 'Go' },
	{ value: 'rust', label: 'Rust' },
	{ value: 'xml', label: 'XML' },
];

const SLUG_TO_MONACO = {
	php: 'php',
	js: 'javascript',
	javascript: 'javascript',
	ts: 'typescript',
	typescript: 'typescript',
	html: 'html',
	css: 'css',
	scss: 'scss',
	sass: 'scss',
	json: 'json',
	yaml: 'yaml',
	yml: 'yaml',
	markdown: 'markdown',
	md: 'markdown',
	bash: 'shell',
	sh: 'shell',
	shell: 'shell',
	zsh: 'shell',
	sql: 'sql',
	python: 'python',
	py: 'python',
	go: 'go',
	golang: 'go',
	rust: 'rust',
	rs: 'rust',
	xml: 'xml',
	diff: 'diff',
	plaintext: 'plaintext',
};

export function slugToMonacoLanguage( slug ) {
	if ( ! slug ) {
		return 'plaintext';
	}
	const normalised = String( slug ).toLowerCase();
	return SLUG_TO_MONACO[ normalised ] || 'plaintext';
}
