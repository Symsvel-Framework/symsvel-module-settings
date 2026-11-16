import { describe, expect, it } from 'vitest';
import { moduleId } from './index';
import { groupSettings, settingsCopy } from './presentation';

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
});
