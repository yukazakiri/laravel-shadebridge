import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { stripTypeScriptTypes } from 'node:module';
import test from 'node:test';
import vm from 'node:vm';

for (const [stack, path] of [
  ['React', '../../stubs/react/resources/js/hooks/use-color-theme.tsx'],
  ['Vue', '../../stubs/vue/resources/js/composables/useColorTheme.ts'],
]) {
  test(`${stack}: default, saved selection, removed selection and stale classes`, () => {
    const storage = new Map();
    const classes = new Set(['dark', 'theme-deleted', 'theme-ocean']);
    classes.remove = (name) => classes.delete(name);
    const context = vm.createContext({
      themes: [{ id: 'default' }, { id: 'ocean' }, { id: 'rose' }],
      defaultColorTheme: 'ocean',
      window: {},
      document: { documentElement: { classList: classes }, cookie: '' },
      localStorage: {
        getItem: (key) => storage.get(key) ?? null,
        setItem: (key, value) => storage.set(key, value),
        removeItem: (key) => storage.delete(key),
      },
      ref: (value) => ({ value }),
      useCallback: (callback) => callback,
      useSyncExternalStore: (_subscribe, snapshot) => snapshot(),
    });
    const source = readFileSync(new URL(path, import.meta.url), 'utf8')
      .replace(/^import .*;\n/gm, '')
      .replace(/^export /gm, '');
    vm.runInContext(stripTypeScriptTypes(source), context);
    vm.runInContext('initializeColorTheme()', context);
    assert.equal(vm.runInContext('getStoredTheme()', context), 'ocean');
    assert.equal(storage.has('color-theme'), false, 'fallback must not become saved choice');
    assert.deepEqual([...classes], ['dark', 'theme-ocean']);
    storage.set('color-theme', 'rose');
    assert.equal(vm.runInContext('getStoredTheme()', context), 'rose');
    storage.set('color-theme', 'deleted');
    assert.equal(vm.runInContext('getStoredTheme()', context), 'ocean');
    assert.equal(storage.has('color-theme'), false);
    vm.runInContext('delete window', context);
    assert.equal(vm.runInContext('getStoredTheme()', context), 'ocean');
  });
}
