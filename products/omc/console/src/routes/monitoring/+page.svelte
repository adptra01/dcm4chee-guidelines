<script lang="ts">
  import { onMount } from 'svelte';

  const API = 'http://localhost:8100';
  type Disk = { dicom_storage_mb: number; host: { used_percent: number; avail_gb: number } };
  type Health = {
    orthanc_http: string;
    ohif_http: string;
    postgres: string;
    service: string;
    studies: string;
    disk: Disk;
    error?: string;
  };
  let health = $state<Health | null>(null);
  let error = $state('');

  async function load() {
    try {
      const r = await fetch(`${API}/pacs/health`);
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      health = await r.json();
      error = '';
    } catch (e) {
      error = `OMC API tidak terjangkau: ${e}`;
    }
  }

  onMount(load);

  function status(s: string): string {
    return s === 'up' || s === '200' ? '✓ sehat' : '✗ bermasalah';
  }
</script>

<h1>Monitoring PACS</h1>
<p>Status health stack PACS (Orthanc, Postgres, OHIF, disk storage DICOM).</p>

{#if error}
  <p style="color: #d33">{error}</p>
{/if}

{#if health}
  <table>
    <thead><tr><th>Komponen</th><th>Status</th></tr></thead>
    <tbody>
      <tr><td>Orthanc REST (:8042)</td><td>{health.orthanc_http} — {status(health.orthanc_http)}</td></tr>
      <tr><td>OHIF Viewer (:3000)</td><td>{health.ohif_http} — {status(health.ohif_http)}</td></tr>
      <tr><td>Postgres index</td><td>{health.postgres} — {status(health.postgres)}</td></tr>
      <tr><td>Service</td><td>{health.service} — {status(health.service)}</td></tr>
      <tr><td>Studi terdaftar</td><td>{health.studies}</td></tr>
      <tr><td>Storage DICOM</td><td>{health.disk.dicom_storage_mb} MB</td></tr>
      <tr><td>Disk host</td><td>{health.disk.host.used_percent}% terpakai · {health.disk.host.avail_gb} GB tersedia</td></tr>
    </tbody>
  </table>
{/if}

<nav><a href="/">← Dashboard</a></nav>
