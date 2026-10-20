const fs = require('fs');
const path = require('path');

/*
 * Hydra: rewrites "import { Folder } from 'lucide-react'" into a default import of
 * the single icon file. The webpack config keeps optimization.sideEffects off (as
 * upstream does), which would otherwise put all ~1900 Lucide icons into the bundle.
 */
function lucideDirectImports({ types: t }) {
    const root = path.dirname(require.resolve('lucide-react/package.json'));
    const entry = fs.readFileSync(path.join(root, 'dist/esm/lucide-react.mjs'), 'utf8');
    const files = {};
    for (const [, names, file] of entry.matchAll(/export \{([^}]*)\} from '\.\/icons\/([^']+)';/g)) {
        for (const [, name] of names.matchAll(/default as (\w+)/g)) {
            files[name] = `lucide-react/dist/esm/icons/${file}`;
        }
    }

    return {
        visitor: {
            ImportDeclaration(declaration) {
                if (declaration.node.source.value !== 'lucide-react') return;

                const specifiers = declaration.node.specifiers;
                if (!specifiers.every((s) => t.isImportSpecifier(s) && files[s.imported.name])) return;

                declaration.replaceWithMultiple(
                    specifiers.map((s) =>
                        t.importDeclaration([t.importDefaultSpecifier(s.local)], t.stringLiteral(files[s.imported.name]))
                    )
                );
            },
        },
    };
}

module.exports = function (api) {
    let targets = {};
    const plugins = [
        'babel-plugin-macros',
        'styled-components',
        'react-hot-loader/babel',
        '@babel/transform-runtime',
        '@babel/transform-react-jsx',
        '@babel/proposal-class-properties',
        '@babel/proposal-object-rest-spread',
        '@babel/proposal-optional-chaining',
        '@babel/proposal-nullish-coalescing-operator',
        '@babel/syntax-dynamic-import',
    ];

    if (api.env('test')) {
        targets = { node: 'current' };
        plugins.push('@babel/transform-modules-commonjs');
    } else {
        plugins.push(lucideDirectImports);
    }

    return {
        plugins,
        presets: [
            '@babel/typescript',
            ['@babel/env', {
                modules: false,
                useBuiltIns: 'entry',
                corejs: 3,
                targets,
            }],
            '@babel/react',
        ]
    };
};
