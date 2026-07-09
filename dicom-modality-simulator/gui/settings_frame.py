import tkinter as tk
from tkinter import ttk
import threading

import config
from dicom.association import create_ae, associate
from dicom.echo import echo


class SettingsFrame(ttk.LabelFrame):
    def __init__(self, parent, log_widget, on_status, cancel_event):
        super().__init__(parent, text="PACS Connection", padding=8)
        self._log = log_widget
        self._on_status = on_status
        self._cancel = cancel_event
        self._ae = None
        self._assoc = None
        self._cfg = config.load()
        self._create_widgets()
        self._load_config()

    def _create_widgets(self):
        self.columnconfigure(0, weight=1)
        row_frame = ttk.Frame(self)
        row_frame.pack(fill=tk.BOTH, expand=True)
        row_frame.columnconfigure(0, weight=1)
        row_frame.columnconfigure(1, weight=1)

        fields = [
            (0, 0, "AE Title:", "_ae_title"),
            (0, 1, "Called AE:", "_called_ae"),
            (0, 2, "PACS Host:", "_host"),
            (0, 3, "PACS Port:", "_port"),
            (1, 0, "SCP AE:", "_scp_ae"),
            (1, 1, "SCP Port:", "_scp_port"),
            (1, 2, "Storage Dir:", "_dir_entry"),
        ]

        for col, row, label, attr in fields:
            side = ttk.Frame(row_frame)
            side.grid(row=row, column=col, sticky=tk.EW, padx=2)
            side.columnconfigure(1, weight=1)
            ttk.Label(side, text=label, font=("", 9, "bold")).grid(
                row=0, column=0, sticky=tk.W, padx=4, pady=2)
            entry = ttk.Entry(side, width=18)
            entry.grid(row=0, column=1, sticky=tk.EW, padx=4, pady=2)
            setattr(self, attr, entry)

        self._dir_entry.configure(width=18)

        btn_frame = ttk.Frame(self)
        btn_frame.pack(side=tk.BOTTOM, fill=tk.X, pady=(6, 0))
        self._test_btn = ttk.Button(btn_frame, text="Test Connection", command=self._on_test)
        self._test_btn.pack(side=tk.LEFT, padx=2)
        self._cancel_btn = ttk.Button(btn_frame, text="Cancel", command=self._on_cancel, state=tk.DISABLED)
        self._cancel_btn.pack(side=tk.LEFT, padx=2)
        self._save_btn = ttk.Button(btn_frame, text="Save", command=self._on_save)
        self._save_btn.pack(side=tk.LEFT, padx=2)
        self._status_lbl = ttk.Label(btn_frame, text="○ Offline", foreground="gray")
        self._status_lbl.pack(side=tk.LEFT, padx=8)

    def _load_config(self):
        def _set(attr, key, default=""):
            getattr(self, attr).delete(0, tk.END)
            getattr(self, attr).insert(0, str(self._cfg.get(key, default)))

        _set("_ae_title", "ae_title", "SIMULATOR")
        _set("_called_ae", "called_ae", "DCM4CHEE")
        _set("_host", "pacs_host", "localhost")
        _set("_port", "pacs_port", 11112)
        _set("_scp_ae", "scp_ae", "SIMULATOR-SCP")
        _set("_scp_port", "scp_port", 11113)
        _set("_dir_entry", "storage_dir", "")

    def get_config(self):
        return {
            "ae_title": self._ae_title.get().strip(),
            "called_ae": self._called_ae.get().strip(),
            "pacs_host": self._host.get().strip(),
            "pacs_port": int(self._port.get().strip()),
            "scp_ae": self._scp_ae.get().strip(),
            "scp_port": int(self._scp_port.get().strip()),
            "storage_dir": self._dir_entry.get().strip(),
        }

    def get_ae(self):
        return self._ae

    def _on_save(self):
        cfg = config.load()
        cfg.update(self.get_config())
        config.save(cfg)
        self._log.log_info("Configuration saved")

    def _on_cancel(self):
        if self._assoc:
            self._assoc.abort()
            self._assoc = None
        self._cancel.set()
        self._status_lbl.configure(text="⟳ Cancelled", foreground="orange")
        self._log.log_info("Connection test cancelled")

    def _on_test(self):
        self._cancel.clear()
        self._test_btn.configure(state=tk.DISABLED)
        self._cancel_btn.configure(state=tk.NORMAL)
        self._status_lbl.configure(text="⟳ Testing...", foreground="orange")
        self._log.log_info("Testing connection...")
        cfg = self.get_config()
        threading.Thread(target=self._do_test, args=(cfg,), daemon=True).start()

    def _do_test(self, cfg):
        if self._cancel.is_set():
            self.after(0, lambda: self._reset_test_btn())
            return
        try:
            ae = create_ae(cfg["ae_title"])
            assoc = associate(ae, cfg["pacs_host"], cfg["pacs_port"], cfg["called_ae"])
            self._assoc = assoc
            if self._cancel.is_set():
                assoc.abort()
                self.after(0, lambda: self._log.log_info("Connection test cancelled"))
                self.after(0, lambda: self._reset_test_btn())
                return
            if assoc.is_established:
                status_code, comment = echo(assoc)
                self._assoc = None
                assoc.release()
                if status_code == 0x0000:
                    self._ae = ae
                    self._on_status(True)
                    self.after(0, lambda: self._status_lbl.configure(text="● Online", foreground="green"))
                    self.after(0, lambda: self._log.log_ok(
                        f"Connected to {cfg['called_ae']}@{cfg['pacs_host']}:{cfg['pacs_port']}"
                    ))
                else:
                    self.after(0, lambda: self._log.log_error(
                        f"C-ECHO failed: Status 0x{status_code:04X} {comment or ''}"
                    ))
            else:
                self._assoc = None
                assoc.release()
                self._on_status(False)
                self.after(0, lambda: self._log.log_error(
                    f"Could not associate with {cfg['called_ae']}@{cfg['pacs_host']}:{cfg['pacs_port']}"
                ))
        except TimeoutError:
            self._on_status(False)
            self.after(0, lambda: self._log.log_error("Connection timed out (10s)"))
        except Exception as e:
            self._on_status(False)
            self.after(0, lambda e=e: self._log.log_error(f"Connection error: {e}"))
        finally:
            self.after(0, lambda: self._reset_test_btn())

    def _reset_test_btn(self):
        self._test_btn.configure(state=tk.NORMAL)
        self._cancel_btn.configure(state=tk.DISABLED)
        if not self._ae:
            self._status_lbl.configure(text="○ Offline", foreground="gray")
