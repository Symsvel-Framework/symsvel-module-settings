<script lang="ts">
  import { onMount } from 'svelte';
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
            <button type="button" class="secondary" onclick={reload} disabled={loading}>{t.load}</button>
          </div>
          {#if error}<p role="alert" class="notice error">{error}</p>{/if}
          {#if message}<p role="status" class="notice success">{message}</p>{/if}
          {#if loading}<p role="status">{t.loading}</p>{/if}
          {#if !loading && settings.length === 0 && !error}<p>{t.empty}</p>{/if}
          {#each Object.entries(groups) as [group, items] (group)}
            <h2>{group}</h2>
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
                <button disabled={saving !== null || loading || (item.secret && item.configured && (drafts[item.key] ?? '') === '')} type="submit">
                  {saving === item.key ? t.saving : t.save}
                </button>
              </form>
            {/each}
          {/each}
        </section>
      </div>

<style>
  .settings { max-width: 1200px; margin: 0 auto; }
  .panel { padding: 28px; background: white; border: 1px solid #e2e7ee; border-radius: 14px; }
  .heading { display: flex; align-items: start; justify-content: space-between; gap: 24px; }
  h1 { margin: 0; } h2 { margin: 28px 0 12px; text-transform: capitalize; }
  p, small { color: #667085; }
  .setting { display: grid; grid-template-columns: minmax(200px, 1fr) minmax(190px, 1fr) auto;
    gap: 18px; align-items: center; padding: 18px 0; border-top: 1px solid #edf0f4; }
  .setting label { display: block; font-weight: 700; }
  .setting small { display: block; font-size: .78rem; margin-top: 5px; }
  .setting p { font-size: .86rem; margin: 5px 0; }
  input, select { padding: 12px; border: 1px solid #ccd4df; border-radius: 8px; font: inherit; min-width: 0; }
  button { background: #1e293b; color: white; border: 0; border-radius: 8px;
    padding: 12px 16px; font: inherit; cursor: pointer; }
  button.secondary { background: white; color: #1e293b; border: 1px solid #ccd4df; }
  button:disabled { opacity: .55; cursor: not-allowed; }
  .notice { padding: 12px 14px; border-radius: 8px; }
  .error { background: #fff1f1; color: #9d2626; }
  .success { background: #eefaf2; color: #236747; }
  @media (max-width: 800px) { .setting { grid-template-columns: 1fr; } .panel { padding: 18px; } }
</style>
