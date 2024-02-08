import { createAppConfig } from '@nextcloud/vite-config'
import entrypoints from './server.modules.mjs'

const entries = Object.entries(entrypoints).map(([prefix, modules]) => Object.fromEntries(
	Object.entries(modules).map(([name, source]) => [`${prefix}-${name}`, source]),
)).reduce((p, c) => ({ ...p, ...c }), {})

export default createAppConfig(
	entries,
	{
		inlineCSS: false,
		emptyOutputDirectory: false,
		assetFileNames: (info) => `dist/${info.name}`,
		thirdPartyLicense: 'dist/vendor.LICENSE.txt',
		config: {
			plugins: [{
				moduleParsed(info) {
					if (info.isEntry && info.id === entries['core-main']) {
						// every other module is implicitly loaded AFTER `core-main` this helps with optimization of dynamic chunks
						Object.values(entries).forEach(i => i !== info.id ? info.implicitlyLoadedBefore.push(i) : undefined)
					}
				},
			}],
			build: {
				cssCodeSplit: false,
				rollupOptions: {
					preserveEntrySignatures: 'allow-extension',
					output: {
						chunkFileNames: (info) => info.name.match(/core-common/) ? 'dist/core-common.mjs' : 'dist/chunks/[name]-[hash].mjs',
						entryFileNames: 'dist/[name].mjs',
						manualChunks: (id) => id.match(/@nextcloud\/vue(\/|$)/) ? 'core-common' : (id.match(/(mdi|vue-mater)/) ? 'icons' : null),
					},
				},
			},
		},
	})
