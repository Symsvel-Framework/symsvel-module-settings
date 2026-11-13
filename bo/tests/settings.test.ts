import { describe, expect, it } from 'vitest';
import { moduleId } from '../src/lib/modules/settings';

describe('settings module', () => {
  it('exports its identifier', () => {
    expect(moduleId).toBe('settings');
  });
});
