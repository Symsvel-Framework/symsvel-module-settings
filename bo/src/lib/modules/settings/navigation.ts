/** BO navigation belongs to the module; authorization remains in its PHP controller. */
export const boNavigation = {
  path: '/settings',
  label: { fr: 'Paramètres', en: 'Settings' },
  resource: 'settings',
  action: 'list',
  probe: '/apibo/settings'
} as const;

/** Optional, read-only alert provider; the host never interprets Settings rules. */
export async function boAlerts(
  check: <T>(path: string) => Promise<T>
): Promise<{ id: string; path: string; message: { fr: string; en: string } }[]> {
  const response = await check<{ data: { key: string; label: string; required: boolean; configured: boolean }[] }>('/apibo/settings');
  return (response.data ?? [])
    .filter((setting) => setting.required && !setting.configured)
    .map((setting) => ({
      id: 'settings:' + setting.key,
      path: '/settings',
      message: {
        fr: 'Configuration requise : ' + setting.label,
        en: 'Required setting: ' + setting.label
      }
    }));
}
