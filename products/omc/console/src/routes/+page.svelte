<script lang="ts">
  import { onMount } from 'svelte';

  const API = 'http://localhost:8100';
  let count = $state(0);
  let stored = $state(0);
  let error = $state('');

  onMount(async () => {
    try {
      const r = await fetch(`${API}/studies`);
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      const data = await r.json();
      count = data.count;
      stored = data.studies.filter((s: { stored: boolean }) => s.stored).length;
    } catch (e) {
      error = `OMC API tidak terjangkau: ${e}`;
    }
  });
</script>

<h1>OMC Console</h1>
<p>Open Modality Console — workstation modality (pengganti PZDR).</p>

{#if error}
  <p style="color: #d33">{error}</p>
{:else}
  <h2>Dashboard</h2>
  <ul>
    <li>Antrean (studi di-import): <strong>{count}</strong></li>
    <li>Sudah di-store ke Orthanc: <strong>{stored}</strong></li>
  </ul>
{/if}

<nav>
  <a href="/queue">Queue</a> · <a href="/worklist">Worklist</a> · <a href="/viewer">Viewer</a> · <a href="/settings">Settings</a> · <a href="/monitoring">Monitoring</a>
</nav>
