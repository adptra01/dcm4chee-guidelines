<script lang="ts">
  import { page } from '$app/state';

  const API = 'http://localhost:8100';
  let id = $state(page.url.searchParams.get('study_id') ?? '');
  let src = $state('');
  let err = $state('');
  let busy = $state(false);

  async function show() {
    if (!id.trim()) return;
    busy = true;
    err = '';
    src = '';
    try {
      const r = await fetch(`${API}/studies/${id.trim()}/preview`);
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      const blob = await r.blob();
      src = URL.createObjectURL(blob);
    } catch (e) {
      err = `Gagal memuat preview: ${e}`;
    } finally {
      busy = false;
    }
  }
</script>

<h1>Viewer</h1>
<p>Preview PNG (window/level dari tag DICOM) via OMC API.</p>

<label>Study ID: <input bind:value={id} placeholder="mis. 2d22dbfc296b" /></label>
<button onclick={show} disabled={busy}>{busy ? 'memuat…' : 'Tampilkan'}</button>

{#if err}
  <p style="color: #d33">{err}</p>
{/if}
{#if src}
  <img src={src} alt="preview DICOM" style="max-width: 100%; border: 1px solid #888" />
{/if}

<nav><a href="/queue">← Queue</a></nav>
