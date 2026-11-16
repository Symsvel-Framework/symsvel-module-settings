import { describe, expect, it } from 'vitest';
import { moduleId } from '../src/lib/modules/settings';
import { groupSettings, settingsCopy } from '../src/lib/modules/settings/presentation';

describe('settings BO', () => {
  it('keeps the module identifier', () => expect(moduleId).toBe('settings'));
  it('localizes messages', () => {
    expect(settingsCopy('fr-FR').save).toBe('Enregistrer');
    expect(settingsCopy('en-US').save).toBe('Save');
  });
  it('groups by declared group while preserving null values', () => {
    const grouped = groupSettings([
      { key: 'mails.sender_name', group: 'mail', label: 'Sender', type: 'string', required: false, secret: false, configured: false, value: null, description: null },
      { key: 'auth.bo_reset_url', group: 'authentication', label: 'Reset', type: 'url', required: false, secret: false, configured: true, value: 'https://bo.test', description: null }
    ]);
    expect(Object.keys(grouped)).toEqual(['authentication', 'mail']);
    expect(grouped.mail[0].value).toBeNull();
  });
});

import { boNavigation } from '../src/lib/modules/settings/navigation';

describe('settings BO navigation declaration', () => {
  it('links to the ACL-protected read-only endpoint', () => {
    expect(boNavigation.path).toBe('/settings');
    expect(boNavigation.probe).toBe('/apibo/settings');
    expect(boNavigation.action).toBe('list');
    expect(boNavigation.resource).toBe('settings');
  });
});
