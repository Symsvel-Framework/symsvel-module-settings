import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

const source = readFileSync(new URL('../src/routes/settings/+page.svelte', import.meta.url), 'utf8');

describe('Settings accordion and dropdown integration contract', () => {
  it('binds each group open state instead of reopening the first group after saving', () => {
    expect(source).toContain('bind:open={expandedGroups[group]}');
    expect(source).toContain('expandedGroups[name] ?? index === 0');
    expect(source).not.toContain('open={index === 0}');
  });

  it('does not clip a dropdown or place later accordions above the active one', () => {
    expect(source).toMatch(/\.module-group \{[^}]*overflow: visible;/s);
    expect(source).toContain('.module-group:focus-within { z-index: 1; }');
    expect(source).toContain('name="symsvel-settings-groups"');
  });
});
