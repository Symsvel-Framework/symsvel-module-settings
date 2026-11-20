<script lang="ts">
  import { onMount } from 'svelte';
  import Button from '../../../../components/Button.svelte';
  import Alert from '../../../../components/Alert.svelte';
  import Dropdown from '../../../../components/Dropdown.svelte';
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import { ApiError } from '../../../../api/client';
  import { uiLanguage } from '../../../../api/locales';
  import { boApi } from '../../../../api/session';
  import { settingsCopy, settingsByModule, applySavedSetting, type BoSetting, type BoSettingsResponse } from '../../presentation';

  let loading = $state(false);
  let saving = $state<string | null>(null);
  let settings = $state<BoSetting[]>([]);
  let drafts = $state<Record<string, string>>({});
  let expandedGroups = $state<Record<string, boolean>>({});
  let error = $state('');
  let message = $state('');
  const language = $derived($uiLanguage ?? (typeof navigator === 'undefined' ? 'en' : navigator.language));
  const t = $derived(settingsCopy(language));
  const groups = $derived(settingsByModule(settings));

  function explain(cause: unknown): string {
    if (cause instanceof ApiError) {
      if (cause.status === 401) {
        void goto(resolve('/login'), { replaceState: true });
        return t.session;
      }
      if (cause.status === 403) return t.forbidden;
      if (cause.status === 400) return t.invalid;
    }
    return t.failure;
  }

  async function reload(): Promise<void> {
    loading = true;
    error = '';
    try {
      const response = await boApi<BoSettingsResponse>('/apibo/settings');
      settings = Array.isArray(response.data) ? response.data : [];
      const groupNames = Object.keys(settingsByModule(settings));
      expandedGroups = Object.fromEntries(groupNames.map((name, index) => [
        name, expandedGroups[name] ?? index === 0
      ]));
      drafts = Object.fromEntries(settings.map((item) => [
        item.key, item.secret ? '' : (item.value ?? '')
      ]));
    } catch (cause) {
      error = explain(cause);
    } finally {
      loading = false;
    }
  }

  onMount(() => {
    void reload();
  });

  async function save(item: BoSetting): Promise<void> {
    if (saving !== null) return;
    saving = item.key;
    error = '';
    message = '';
    const draft = drafts[item.key] ?? '';
    try {
      await boApi<{ status: string }>('/apibo/settings/' + encodeURIComponent(item.key), {
        method: 'PUT',
        body: JSON.stringify({ value: item.type === 'boolean' ? draft === '1' : (draft === '' ? null : draft) })
      });
      settings = settings.map((current) => current.key === item.key
        ? applySavedSetting(current, draft)
        : current);
      if (item.secret) drafts = { ...drafts, [item.key]: '' };
      message = t.saved;
      window.dispatchEvent(new Event('symsvel:alerts-changed'));
      if (item.key === 'languages.multilingual') {
        window.dispatchEvent(new Event('symsvel:navigation-changed'));
      }
    } catch (cause) {
      error = explain(cause);
    } finally {
      saving = null;
    }
  }
</script>

<svelte:head><title>{t.title} | SymSvel BO</title></svelte:head>
<div class="settings">
        <section class="panel">
          <div class="heading"><div><h1>{t.heading}</h1><p>{t.intro}</p></div>
            <Button type="button" variant="secondary" onclick={reload} disabled={loading}>{t.load}</Button>
          </div>
          {#if error}<Alert kind="error">{error}</Alert>{/if}
          {#if message}<Alert kind="success">{message}</Alert>{/if}
          {#if loading}<p role="status">{t.loading}</p>{/if}
          {#if !loading && settings.length === 0 && !error}<p>{t.empty}</p>{/if}
          {#each Object.entries(groups) as [group, items] (group)}
            <details class="module-group" name="symsvel-settings-groups" bind:open={expandedGroups[group]}>
              <summary><span>{group}</span><small>{items.length}</small></summary>
              <div class="module-group-fields">
                {#each items as item (item.key)}
                  <form class="setting" onsubmit={(event) => { event.preventDefault(); void save(item); }}>
                <div>
                  {#if item.type === 'boolean' || item.type === 'choice'}
                    <span class="field-label">{item.label}</span>
                  {:else}
                    <label for={'setting-' + item.key}>{item.label}</label>
                  {/if}
                  <small>{item.key} · {item.required ? t.required : t.optional}</small>
                  {#if item.description}<p>{item.description}</p>{/if}
                  {#if item.secret && item.configured}<small>{t.secret} · {t.configured}</small>{/if}
                  {#if item.editable === false}<small>{t.readOnly}</small>{/if}
                </div>
                {#if item.type === 'boolean'}
                  <Dropdown value={drafts[item.key] ?? ''}
                    options={[{ value: '1', label: t.enabled }, { value: '0', label: t.disabled }]}
                    label={item.label} placeholder={t.notSet}
                    onChange={(value) => { drafts = { ...drafts, [item.key]: value }; }}
                    disabled={item.editable === false || saving !== null || loading} />
                {:else if item.type === 'choice'}
                  <Dropdown value={drafts[item.key] ?? ''}
                    options={[
                      { value: '', label: t.notSet },
                      ...Object.entries(item.choices ?? {}).map(([value, label]) => ({ value, label }))
                    ]}
                    label={item.label}
                    onChange={(value) => { drafts = { ...drafts, [item.key]: value }; }}
                    disabled={item.editable === false || Object.keys(item.choices ?? {}).length === 0 || saving !== null || loading} />
                {:else}
                  <input id={'setting-' + item.key}
                    type={item.secret ? 'password' : (item.type === 'email' ? 'email' : (item.type === 'url' ? 'url' : 'text'))}
                    autocomplete="off" bind:value={drafts[item.key]}
                    placeholder={item.secret && item.configured ? t.secret : t.notSet}
                    required={item.required} disabled={item.editable === false} />
                {/if}
                {#if item.editable !== false}
                  <Button variant="success" disabled={saving !== null || loading || (item.type === 'choice' && Object.keys(item.choices ?? {}).length === 0) || (item.secret && item.configured && (drafts[item.key] ?? '') === '') || (item.required && item.type === 'choice' && (drafts[item.key] ?? '') === '')} type="submit">
                    {saving === item.key ? t.saving : t.save}
                  </Button>
                {/if}
                  </form>
                {/each}
              </div>
            </details>
          {/each}
        </section>
      </div>

<style>
  .settings { max-width: 1200px; margin: 0 auto; }
  .panel { padding: 28px; background: var(--bo-surface); color: var(--bo-text); border: 1px solid var(--bo-border); border-radius: 14px; }
  .heading { display: flex; align-items: start; justify-content: space-between; gap: 24px; }
  h1 { margin: 0; } h2 { margin: 28px 0 12px; text-transform: capitalize; }
  p, small { color: var(--bo-muted); }
  .module-group { position: relative; border: 1px solid var(--bo-border); border-radius: 10px; margin: 12px 0; overflow: visible; }
  .module-group:focus-within { z-index: 1; }
  .module-group summary { display: flex; align-items: center; justify-content: space-between; cursor: pointer;
    padding: 16px 18px; font-size: 1.05rem; font-weight: 700; text-transform: capitalize; background: var(--bo-surface-muted); border-radius: 9px; }
  .module-group summary:hover { background: var(--bo-surface); }
  .module-group summary:focus-visible { background: var(--bo-surface-muted); outline: 2px solid var(--bo-focus); outline-offset: -2px; }
  .module-group summary small { font-size: .85rem; font-weight: 600; color: var(--bo-muted); }
  .module-group[open] summary { border-bottom: 1px solid var(--bo-border); border-radius: 9px 9px 0 0; }
  .module-group-fields { padding: 0 18px; }
  .setting { display: grid; grid-template-columns: minmax(200px, 1fr) minmax(190px, 1fr) auto;
    gap: 18px; align-items: center; padding: 18px 0; border-top: 1px solid var(--bo-border); }
  .setting label, .setting .field-label { display: block; font-weight: 700; }
  .setting small { display: block; font-size: .78rem; margin-top: 5px; }
  .setting p { font-size: .86rem; margin: 5px 0; }
  .setting input { width: 100%; padding: 12px; border: 1px solid var(--bo-border); border-radius: 8px; font: inherit; min-width: 0; background: var(--bo-input); color: var(--bo-text); }
  .setting input:focus-visible { outline: 2px solid var(--bo-focus); outline-offset: 2px; }
  .setting :global(.bo-dropdown) { width: 100%; }
  @media (max-width: 800px) { .setting { grid-template-columns: 1fr; } .panel { padding: 18px; } }
</style>
