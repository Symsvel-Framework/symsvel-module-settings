<script lang="ts">
  import { onMount } from 'svelte';
  import Button from '../../../../components/Button.svelte';
  import Alert from '../../../../components/Alert.svelte';
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import { ApiError } from '../../../../api/client';
  import { uiLanguage } from '../../../../api/locales';
  import { boApi } from '../../../../api/session';
  import { settingsCopy, groupSettings, type BoSetting, type BoSettingsResponse } from '../../presentation';

  let loading = $state(false);
  let saving = $state<string | null>(null);
  let settings = $state<BoSetting[]>([]);
  let drafts = $state<Record<string, string>>({});
  let error = $state('');
  let message = $state('');
  const language = $derived($uiLanguage ?? (typeof navigator === 'undefined' ? 'en' : navigator.language));
  const t = $derived(settingsCopy(language));
  const groups = $derived(groupSettings(settings));

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
      message = t.saved;
      await reload();
      window.dispatchEvent(new Event('symsvel:alerts-changed'));
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
          {#each Object.entries(groups) as [group, items], index (group)}
            <details class="module-group" name="symsvel-settings-groups" open={index === 0}>
              <summary><span>{group}</span><small>{items.length}</small></summary>
              <div class="module-group-fields">
                {#each items as item (item.key)}
                  <form class="setting" onsubmit={(event) => { event.preventDefault(); void save(item); }}>
                <div>
                  <label for={'setting-' + item.key}>{item.label}</label>
                  <small>{item.key} · {item.required ? t.required : t.optional}</small>
                  {#if item.description}<p>{item.description}</p>{/if}
                  {#if item.secret && item.configured}<small>{t.secret} · {t.configured}</small>{/if}
                </div>
                {#if item.type === 'boolean'}
                  <select id={'setting-' + item.key} bind:value={drafts[item.key]}>
                    <option value="1">{t.enabled}</option><option value="0">{t.disabled}</option>
                  </select>
                {:else}
                  <input id={'setting-' + item.key}
                    type={item.secret ? 'password' : (item.type === 'email' ? 'email' : (item.type === 'url' ? 'url' : 'text'))}
                    autocomplete="off" bind:value={drafts[item.key]}
                    placeholder={item.secret && item.configured ? t.secret : t.notSet}
                    required={item.required} />
                {/if}
                <Button disabled={saving !== null || loading || (item.secret && item.configured && (drafts[item.key] ?? '') === '')} type="submit">
                  {saving === item.key ? t.saving : t.save}
                </Button>
                  </form>
                {/each}
              </div>
            </details>
          {/each}
        </section>
      </div>

<style>
  .settings { max-width: 1200px; margin: 0 auto; }
  .panel { padding: 28px; background: white; border: 1px solid #e2e7ee; border-radius: 14px; }
  .heading { display: flex; align-items: start; justify-content: space-between; gap: 24px; }
  h1 { margin: 0; } h2 { margin: 28px 0 12px; text-transform: capitalize; }
  p, small { color: #667085; }
  .module-group { border: 1px solid #e2e7ee; border-radius: 10px; margin: 12px 0; overflow: hidden; }
  .module-group summary { display: flex; align-items: center; justify-content: space-between; cursor: pointer;
    padding: 16px 18px; font-size: 1.05rem; font-weight: 700; text-transform: capitalize; background: #f8fafc; }
  .module-group summary:hover, .module-group summary:focus-visible { background: #eef3fb; }
  .module-group summary small { font-size: .85rem; font-weight: 600; color: #667085; }
  .module-group[open] summary { border-bottom: 1px solid #e2e7ee; }
  .module-group-fields { padding: 0 18px; }
  .setting { display: grid; grid-template-columns: minmax(200px, 1fr) minmax(190px, 1fr) auto;
    gap: 18px; align-items: center; padding: 18px 0; border-top: 1px solid #edf0f4; }
  .setting label { display: block; font-weight: 700; }
  .setting small { display: block; font-size: .78rem; margin-top: 5px; }
  .setting p { font-size: .86rem; margin: 5px 0; }
  input, select { padding: 12px; border: 1px solid #ccd4df; border-radius: 8px; font: inherit; min-width: 0; }
  @media (max-width: 800px) { .setting { grid-template-columns: 1fr; } .panel { padding: 18px; } }
</style>
