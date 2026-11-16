import { describe, expect, it } from 'vitest';
import { moduleId } from './index';
import { groupSettings, settingsByModule, settingsCopy } from './presentation';

describe('settings BO assembled', () => {
  it('keeps the module identifier', () => expect(moduleId).toBe('settings'));
  it('localizes messages without embedding strings in Svelte', () => {
    expect(settingsCopy('fr-FR').save).toBe('Enregistrer');
    expect(settingsCopy('en-US').save).toBe('Save');
  });
  it('groups by declared group and retains null values', () => {
    const grouped = groupSettings([
      { key: 'mails.sender_name', group: 'mail', label: 'Sender', type: 'string', required: false, secret: false, configured: false, value: null, description: null },
      { key: 'auth.bo_reset_url', group: 'authentication', label: 'Reset', type: 'url', required: false, secret: false, configured: true, value: 'https://bo.test', description: null }
    ]);
    expect(Object.keys(grouped)).toEqual(['authentication', 'mail']);
    expect(grouped.mail[0].value).toBeNull();
  });
  it('groups the accordion by owning module instead of presentation category', () => {
    const grouped = settingsByModule([
      { key: 'auth.a', group: 'general', label: 'A', type: 'string', required: false, secret: false, configured: false, value: null, description: null },
      { key: 'auth.b', group: 'advanced', label: 'B', type: 'string', required: false, secret: false, configured: false, value: null, description: null },
      { key: 'mails.sender', group: 'general', label: 'Sender', type: 'string', required: false, secret: false, configured: false, value: null, description: null }
    ]);
    expect(Object.keys(grouped)).toEqual(['auth', 'mails']);
    expect(grouped.auth.map((s) => s.key).sort()).toEqual(['auth.a', 'auth.b']);
    expect(grouped.mails.map((s) => s.key)).toEqual(['mails.sender']);
  });
});
