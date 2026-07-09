"""C-MOVE: retrieve study from PACS, receive on SCP."""
import threading, time, os, sys
from pydicom.dataset import Dataset
from pynetdicom import AE, evt
from pynetdicom.sop_class import (
    StudyRootQueryRetrieveInformationModelMove,
    CTImageStorage, MRImageStorage
)

STORAGE_DIR = "/tmp/cmove_received"
os.makedirs(STORAGE_DIR, exist_ok=True)

received = []

def handle_store(event):
    ds = event.dataset
    ds.file_meta = event.file_meta
    fname = f"{STORAGE_DIR}/{ds.SOPInstanceUID}.dcm"
    ds.save_as(fname, write_like_original=False)
    received.append(ds.SOPInstanceUID)
    return 0x0000

def start_scp():
    ae = AE("SIMULATOR-SCP")
    ae.acse_timeout = 10
    ae.dimse_timeout = 10
    for ctx in [
        "1.2.840.10008.5.1.4.1.1.2",
        "1.2.840.10008.5.1.4.1.1.4",
        "1.2.840.10008.5.1.4.1.1.7",
        "1.2.840.10008.5.1.4.1.1.6.1",
    ]:
        ae.add_supported_context(ctx)
    handlers = [(evt.EVT_C_STORE, handle_store)]
    scp = ae.start_server(("0.0.0.0", 11114), block=False, evt_handlers=handlers)
    return scp

scp = start_scp()
time.sleep(1)

ae_scu = AE("SIMULATOR")
ae_scu.acse_timeout = 10
ae_scu.dimse_timeout = 30
ae_scu.add_requested_context(StudyRootQueryRetrieveInformationModelMove)

assoc = ae_scu.associate("172.20.0.6", 11112, ae_title="DCM4CHEE")
if not assoc.is_established:
    print("MOVE: Association failed")
    scp.shutdown()
    exit(1)

ds = Dataset()
ds.QueryRetrieveLevel = "STUDY"
ds.StudyInstanceUID = "1.2.840.113663.1500.1.480141154.1.1.20231012162936"

print(f"MOVE study {ds.StudyInstanceUID} to SIMULATOR-SCP at 172.20.0.1:11114")
try:
    responses = assoc.send_c_move(ds, "SIMULATOR-SCP",
                                   StudyRootQueryRetrieveInformationModelMove)
    for status, identifier in responses:
        print(f"  Status: {status.Status if status else 'None'} "
              f"Remaining: {getattr(status, 'NumberOfRemainingSuboperations', None)} "
              f"Completed: {getattr(status, 'NumberOfCompletedSuboperations', None)}")
except Exception as e:
    print(f"  Error: {e}")

assoc.release()
scp.shutdown()
print(f"\nReceived {len(received)} instances")
for uid in received:
    os.remove(f"{STORAGE_DIR}/{uid}.dcm")
print("Done")
