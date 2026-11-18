import { generatedTranslation } from '../../i18n/generated-translations';

export type BoSetting = {
  key: string;
  label: string;
  group: string;
  type: 'string' | 'boolean' | 'email' | 'url' | 'choice';
  choices?: Record<string, string>;
  editable?: boolean;
  required: boolean;
  secret: boolean;
  configured: boolean;
  value: string | null;
  description: string | null;
};
export type BoSettingsResponse = { data: BoSetting[] };

const texts = {
  fr: {
    title: 'Paramètres', heading: 'Configuration des modules', dashboard: 'Tableau de bord',
    intro: 'Seules les valeurs sont modifiables. Les définitions appartiennent aux modules.',
    load: 'Actualiser', loading: 'Chargement…', save: 'Enregistrer', saving: 'Enregistrement…',
    saved: 'Paramètre enregistré.', empty: 'Aucun paramètre déclaré.',
    enabled: 'Activé', disabled: 'Désactivé', notSet: 'Non renseigné',
    secret: 'Valeur confidentielle masquée', configured: 'Déjà configuré',
    required: 'Obligatoire', optional: 'Facultatif',
    invalid: 'Valeur invalide.', forbidden: 'Action interdite par les ACL.',
    session: 'Session expirée. Reconnectez-vous.', failure: 'Impossible de charger ou enregistrer.',
    verifying: 'Vérification de la session…', readOnly: 'Défini à l’installation · Lecture seule'
  },
  en: {
    title: 'Settings', heading: 'Module configuration', dashboard: 'Dashboard',
    intro: 'Only values can be edited. Modules own their definitions.',
    load: 'Refresh', loading: 'Loading…', save: 'Save', saving: 'Saving…',
    saved: 'Setting updated.', empty: 'No settings are defined.',
    enabled: 'Enabled', disabled: 'Disabled', notSet: 'Not configured',
    secret: 'Secret value hidden', configured: 'Already configured',
    required: 'Required', optional: 'Optional',
    invalid: 'Invalid value.', forbidden: 'Permission denied.',
    session: 'Session expired. Please sign in.', failure: 'Cannot load or save settings.',
    verifying: 'Checking session…', readOnly: 'Set during installation · Read-only'
  }
};
export type SettingsCopy = (typeof texts)['fr'];
export function settingsCopy(language: string | null): SettingsCopy {
  const base = language?.toLowerCase().split('-')[0] === 'en' ? texts.en : texts.fr;
  return Object.fromEntries(Object.entries(base).map(([key, value]) => [
    key, generatedTranslation(language, 'bo.settings.' + key) ?? value
  ])) as SettingsCopy;
}
export function groupSettings(data: BoSetting[]): Record<string, BoSetting[]> {
  return [...data]
    .sort((a, b) => a.group.localeCompare(b.group) || a.label.localeCompare(b.label))
    .reduce<Record<string, BoSetting[]>>((groups, item) => {
      (groups[item.group] ??= []).push(item);
      return groups;
    }, {});
}


/** Use the qualified setting owner, not its arbitrary presentation subgroup. */
export function settingsByModule(data: BoSetting[]): Record<string, BoSetting[]> {
  return [...data]
    .sort((a, b) => a.key.split('.')[0].localeCompare(b.key.split('.')[0]) ||
      a.group.localeCompare(b.group) || a.label.localeCompare(b.label))
    .reduce<Record<string, BoSetting[]>>((modules, item) => {
      const owner = item.key.split('.')[0];
      (modules[owner] ??= []).push(item);
      return modules;
    }, {});
}
