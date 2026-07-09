#!/bin/sh
mkdir -p /storage
python -c "
from pynetdicom import AE, evt, AllStoragePresentationContexts
from pynetdicom.sop_class import StorageCommitmentPushModel
from pydicom import dcmwrite
from pathlib import Path
import signal, sys

ae = AE(ae_title=b'${AE_TITLE:-SIMULATOR-SCP}')
for cx in AllStoragePresentationContexts:
    ae.add_supported_context(str(cx.abstract_syntax))
ae.add_supported_context(StorageCommitmentPushModel)

def handle_store(event):
    ds = event.dataset
    ds.file_meta = event.file_meta
    uid = ds.StudyInstanceUID or 'unknown'
    d = Path('/storage') / uid
    d.mkdir(parents=True, exist_ok=True)
    f = d / f'{ds.SOPInstanceUID}.dcm'
    dcmwrite(str(f), ds)
    print(f'Stored: {ds.PatientName} ({ds.PatientID}) [{ds.Modality}] {f.name}')
    return 0x0000

handlers = [(evt.EVT_C_STORE, handle_store)]
server = ae.start_server(('0.0.0.0', ${PORT:-11114}), evt_handlers=handlers, block=False)
print(f'SCP {ae.ae_title} listening on port ${PORT:-11114}')
signal.pause()
" 2>&1
