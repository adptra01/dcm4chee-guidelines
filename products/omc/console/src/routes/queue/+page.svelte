<script lang="ts">
  import { onMount } from 'svelte';

  const API = 'http://localhost:8100';
  type Study = {
    study_id: string;
    PatientName: string;
    Modality: string;
    StudyDescription: string;
    Rows: number;
    stored: boolean;
  };
  let studies: Study[] = $state([]);
  let error = $state('');

  async function load() {
    try {
      const r = await fetch(`${API}/studies`);
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      studies = (await r.json()).studies;
      error = '';
    } catch (e) {
      error = `OMC API tidak terjangkau: ${e}`;
    }
  }

  async function store(id: string) {
    await fetch(`${API}/studies/${id}/store`, { method: 'POST' });
    await load();
  }

  onMount(load);
</script>

<h1>Queue</h1>

{#if error}
  <p style="color: #d33">{error}</p>
{/if}

<table>
  <thead>
    <tr><th>Pasien</th><th>Modality</th><th>Deskripsi</th><th>Dimensi</th><th>Status</th><th></th></tr>
  </thead>
  <tbody>
    {#each studies as s}
      <tr>
        <td>{s.PatientName || '—'}</td>
        <td>{s.Modality}</td>
        <td>{s.StudyDescription || '—'}</td>
        <td>{s.Rows}px</td>
        <td>{s.stored ? '✓ Orthanc' : 'antre'}</td>
        <td>
          <a href={`/viewer?study_id=${s.study_id}`}>preview</a>
          {#if !s.stored}
            <button onclick={() => store(s.study_id)}>store</button>
          {/if}
        </td>
      </tr>
    {:else}
      <tr><td colspan="6">Antrean kosong — import DICOM via POST /studies/import</td></tr>
    {/each}
  </tbody>
</table>

<nav><a href="/">← Dashboard</a></nav>
