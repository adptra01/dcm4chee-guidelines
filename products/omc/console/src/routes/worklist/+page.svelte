<script lang="ts">
  import { onMount } from 'svelte';

  const API = 'http://localhost:8100';
  type WorklistItem = {
    patient: string;
    patient_id: string;
    accession: string;
    modality: string;
    start_date: string;
  };
  let items: WorklistItem[] = $state([]);
  let error = $state('');

  async function load() {
    try {
      const r = await fetch(`${API}/worklist`);
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      items = (await r.json()).worklist;
      error = '';
    } catch (e) {
      error = `OMC API tidak terjangkau: ${e}`;
    }
  }

  onMount(load);
</script>

<h1>Worklist MWL</h1>
<p>Jadwal modalitas dari Integration MWL SCP (:4243).</p>

{#if error}
  <p style="color: #d33">{error}</p>
{/if}

<table>
  <thead>
    <tr><th>Pasien</th><th>ID</th><th>Accession</th><th>Modality</th><th>Tanggal</th></tr>
  </thead>
  <tbody>
    {#each items as w}
      <tr>
        <td>{w.patient || '—'}</td>
        <td>{w.patient_id || '—'}</td>
        <td>{w.accession || '—'}</td>
        <td>{w.modality || '—'}</td>
        <td>{w.start_date || '—'}</td>
      </tr>
    {:else}
      <tr><td colspan="5">Worklist kosong — tidak ada jadwal MWL</td></tr>
    {/each}
  </tbody>
</table>

<nav><a href="/">← Dashboard</a></nav>
