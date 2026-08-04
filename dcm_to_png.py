#!/usr/bin/env python3
"""Ubah pixel data DICOM → PNG (Tahap 3 belajar DICOM).

Membedah pixel data file sample: baca metadata gambar, ambil PixelData,
terapkan window/level, dan (karena PhotometricInterpretation MONOCHROME1)
invert nilai agar gambar terlihat wajar.

Jalankan:
  python dcm_to_png.py <file.dcm> [window_center] [window_width]

Contoh:
  python dcm_to_png.py "sample/DX0000005 tes lagi/DX0000005 Chest PA/DX Chest PA/DX000000.dcm"
"""
import sys

import pydicom
from PIL import Image


def apply_window(arr, wc, ww):
    """Terapkan window/level (Center/Width) dan kembalikan array 0-255."""
    import numpy as np
    lo = wc - ww / 2.0
    hi = wc + ww / 2.0
    out = np.clip(arr, lo, hi)
    return ((out - lo) / (hi - lo) * 255.0).astype("uint8")


def main(filepath: str, wc=None, ww=None) -> int:
    ds = pydicom.dcmread(filepath)
    arr = ds.pixel_array  # numpy ndarray, nilai mentah (raw)

    print("=== Pixel data (metadata) ===")
    print(f"  Modality        : {ds.Modality}")
    print(f"  Rows x Columns  : {ds.Rows} x {ds.Columns}")
    print(f"  BitsAllocated   : {ds.BitsAllocated}   BitsStored: {ds.BitsStored}")
    print(f"  SamplesPerPixel : {ds.SamplesPerPixel}")
    print(f"  Photometric     : {ds.PhotometricInterpretation}")
    print(f"  PixelRepr       : {ds.PixelRepresentation}")
    print(f"  Rescale         : slope={getattr(ds, 'RescaleSlope', 1)} intercept={getattr(ds, 'RescaleIntercept', 0)}")
    print(f"  Window center/width: {getattr(ds, 'WindowCenter', 'n/a')} / {getattr(ds, 'WindowWidth', 'n/a')}")
    print(f"  PixelData shape : {arr.shape}, dtype={arr.dtype}, min={arr.min()}, max={arr.max()}")

    # Window/level: pakai yang ada di file, atau override dari argumen
    wc = float(wc) if wc is not None else float(ds.WindowCenter if hasattr(ds, "WindowCenter") else arr.mean())
    ww = float(ww) if ww is not None else float(ds.WindowWidth if hasattr(ds, "WindowWidth") else (arr.max() - arr.min()))

    img8 = apply_window(arr, wc, ww)

    # MONOCHROME1 = nilai tinggi = gelap → invert supaya wajar secara visual
    if ds.PhotometricInterpretation == "MONOCHROME1":
        img8 = 255 - img8

    out = filepath.rsplit(".", 1)[0] + ".png"
    Image.fromarray(img8, mode="L").save(out)
    print(f"\n=== PNG tersimpan: {out} ===")
    return 0


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(__doc__)
        sys.exit(1)
    sys.exit(main(sys.argv[1], *sys.argv[2:4]))