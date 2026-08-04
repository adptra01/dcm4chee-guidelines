#!/usr/bin/env python3
"""SCU DICOM lab — C-ECHO + C-STORE ke Orthanc dengan tampilan PDU asosiasi.

Menampilkan 'di balik layar' negosiasi di level protokol:
A-ASSOCIATE-RQ (kirim) dan A-ASSOCIATE-AC (terima) termasuk Abstract Syntax
dan Transfer Syntax yang disepakati — persis diagram di docs/DICOM-BELAJAR.md.

Jalankan:  python scu_demo.py [file.dcm]
   tanpa file  → hanya C-ECHO
   dengan file → C-ECHO + C-STORE
"""
import logging
import sys

HOST, PORT, SCU_AE, SCP_AE = "localhost", 4242, "PY_LAB", "ORTHANC"


def main(filepath: str | None) -> int:
    # Root DEBUG supaya pynetdicom menyemburkan deskripsi PDU (A-ASSOCIATE-RQ/AC)
    logging.basicConfig(level=logging.DEBUG, format="%(message)s")
    # Cukup tampilkan baris yang berkaitan dengan PDU asosiasi + handler status
    import pynetdicom._handlers as h
    logging.getLogger("pynetdicom._handlers").setLevel(logging.DEBUG)
    logging.getLogger("pynetdicom.association").setLevel(logging.INFO)
    logging.getLogger("pynetdicom.acse").setLevel(logging.INFO)

    from pynetdicom import AE
    from pynetdicom.sop_class import DigitalXRayImageStorageForPresentation, Verification

    ae = AE(ae_title=SCU_AE)
    ae.add_requested_context(Verification)
    ae.add_requested_context(DigitalXRayImageStorageForPresentation)

    print(f"\n=== SCU '{SCU_AE}' → asosiasi ke '{SCP_AE}' @ {HOST}:{PORT} ===")
    assoc = ae.associate(HOST, PORT, ae_title=SCP_AE)
    if not assoc.is_established:
        print("\n!!! Association GAGAL")
        return 1
    print(f"\n=== Asosiasi ESTABLISHED ({len([c for c in assoc.accepted_contexts if c])} context). ===")

    print("\n=== C-ECHO (Verification) ===")
    status = assoc.send_c_echo()
    print(f"<<< status: {status}  (0x0000 = Success)\n")

    if filepath is not None:
        from pydicom import dcmread

        ds = dcmread(filepath)
        print(f"=== C-STORE ({ds.SOPClassUID}) ===")
        status = assoc.send_c_store(ds)
        print(f"<<< status: {status}  (0x0000 = Success)\n")

    assoc.release()
    print("=== Association RELEASED ===")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1] if len(sys.argv) > 1 else None))