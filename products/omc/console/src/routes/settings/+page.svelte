<script lang="ts">
  import { onMount } from 'svelte';

  const API = 'http://localhost:8100';
  type Settings = {
    host: string;
    port: number;
    scu_ae: string;
    scp_ae: string;
    echoc: boolean;
  };

  let settings = $state<Settings | null>(null);
  let error = $state('');

  onMount(async () => {
    try {
      const r = await fetch(`${API}/settings`);
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      settings = await r.json();
    } catch (e) {
      error = `OMC API tidak terjangkau: ${e}`;
    }
  });
</script>

<h1>Settings</h1>
<p>Target DICOM (Orthanc). Konfigurasi via env <code>OMC_*</code> di server.</p>

{#if error}
  <p style="color: #d33">{error}</p>
{/if}

{#if settings}
  <table>
    <tbody>
      <tr><th>Host</th><td><code>{settings.host}</code></td></tr>
      <tr><th>Port DICOM</th><td><code>{settings.port}</code></td></tr>
      <tr><th>SCU AE (console)</th><td><code>{settings.scu_ae}</code></td></tr>
      <tr><th>SCP AE (Orthanc)</th><td><code>{settings.scp_ae}</code></td></tr>
      <tr>
        <th>Koneksi (C-ECHO)</th>
        <td>
          {#if settings.echoc}
            <span style="color:#177245;font-weight:600">● OK</span>
          {:else}
            <span style="color:#d33;font-weight:600">● Gagal</span>
          {/if}
        </td>
      </tr>
    </tbody>
  </table>
{/if}

<nav><a href="/">← Dashboard</a></nav>