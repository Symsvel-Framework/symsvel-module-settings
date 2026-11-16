/** BO navigation belongs to the module; authorization remains in its PHP controller. */
export const boNavigation = {
  path: '/settings',
  label: { fr: 'Paramètres', en: 'Settings' },
  resource: 'settings',
  action: 'list',
  probe: '/apibo/settings'
} as const;
