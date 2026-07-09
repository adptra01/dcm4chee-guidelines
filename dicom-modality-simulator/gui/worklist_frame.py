import tkinter as tk
from tkinter import ttk
import threading

from dicom.association import associate
from dicom.worklist import build_query, query_worklist
from dicom.worklist_mwl import build_mwl_query, query_mwl_dicom, query_mwl_local


class WorklistFrame(ttk.LabelFrame):
    def __init__(self, parent, log_widget, get_config, get_ae, on_select, cancel_event):
        super().__init__(parent, text="Worklist", padding=8)
        self._log = log_widget
        self._get_config = get_config
        self._get_ae = get_ae
        self._on_select = on_select
        self._cancel = cancel_event
        self._assoc = None
        self._items = []
        self._mode = tk.StringVar(value="study")
        self._create_widgets()

    def _create_widgets(self):
        toolbar = ttk.Frame(self)
        toolbar.pack(fill=tk.X, pady=(0, 4))

        ttk.Radiobutton(toolbar, text="Study Root", variable=self._mode,
                        value="study").pack(side=tk.LEFT, padx=2)
        ttk.Radiobutton(toolbar, text="MWL (Scheduled)", variable=self._mode,
                        value="mwl").pack(side=tk.LEFT, padx=2)

        self._refresh_btn = ttk.Button(toolbar, text="Refresh", command=self._on_refresh)
        self._refresh_btn.pack(side=tk.LEFT, padx=6)
        self._cancel_btn = ttk.Button(toolbar, text="Cancel", command=self._on_cancel, state=tk.DISABLED)
        self._cancel_btn.pack(side=tk.LEFT, padx=2)
        self._mode_lbl = ttk.Label(toolbar, text="📋 Study Root", foreground="#555")
        self._mode_lbl.pack(side=tk.LEFT, padx=6)
        self._count_lbl = ttk.Label(toolbar, text="0 items")
        self._count_lbl.pack(side=tk.RIGHT, padx=4)

        self._mode.trace_add("write", self._on_mode_change)

        columns = ("patient_id", "patient_name", "study_date", "modality", "accession", "study_desc")
        self._tree = ttk.Treeview(self, columns=columns, show="headings",
                                  height=8, selectmode="browse")
        self._tree.heading("patient_id", text="Patient ID")
        self._tree.heading("patient_name", text="Patient Name")
        self._tree.heading("study_date", text="Date")
        self._tree.heading("modality", text="Modality")
        self._tree.heading("accession", text="Accession")
        self._tree.heading("study_desc", text="Description")
        for col, w in [("patient_id", 90), ("patient_name", 140), ("study_date", 80),
                       ("modality", 70), ("accession", 100), ("study_desc", 160)]:
            self._tree.column(col, width=w)
        scroll = ttk.Scrollbar(self, orient=tk.VERTICAL, command=self._tree.yview)
        self._tree.configure(yscrollcommand=scroll.set)
        self._tree.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        scroll.pack(side=tk.RIGHT, fill=tk.Y)
        self._tree.bind("<<TreeviewSelect>>", self._on_tree_select)

    def _on_mode_change(self, *_):
        self._on_cancel()
        self._reset_btn()
        mode = self._mode.get()
        label = "📋 Study Root" if mode == "study" else "🩺 MWL (Scheduled)"
        self._mode_lbl.configure(text=label)

    def _on_cancel(self):
        if self._assoc:
            self._assoc.abort()
            self._assoc = None
        self._cancel.set()

    def _on_refresh(self):
        self._cancel.clear()
        self._refresh_btn.configure(state=tk.DISABLED)
        self._cancel_btn.configure(state=tk.NORMAL)
        self._log.log_info(f"Querying {self._mode.get().upper()} worklist...")
        threading.Thread(target=self._do_refresh, daemon=True).start()

    def _do_refresh(self):
        cfg = self._get_config()
        ae = self._get_ae()
        mode = self._mode.get()
        if ae is None:
            self.after(0, lambda: self._log.log_error("Not connected. Test connection first."))
            self.after(0, lambda: self._reset_btn())
            return
        if self._cancel.is_set():
            self.after(0, lambda: self._reset_btn())
            return

        if mode == "mwl":
            self._do_mwl_refresh(cfg, ae)
        else:
            self._do_study_refresh(cfg, ae)

    def _do_study_refresh(self, cfg, ae):
        try:
            assoc = associate(ae, cfg["pacs_host"], cfg["pacs_port"], cfg["called_ae"])
            self._assoc = assoc
            if self._cancel.is_set():
                assoc.abort()
                self.after(0, lambda: self._reset_btn())
                return
            if not assoc.is_established:
                self._assoc = None
                self.after(0, lambda: self._log.log_error("Association failed"))
                self.after(0, lambda: self._reset_btn())
                return
            query = build_query()
            results = query_worklist(assoc, query)
            self._assoc = None
            assoc.release()
            self._items = results
            self.after(0, self._update_table)
            self.after(0, lambda: self._log.log_ok(f"Worklist: {len(results)} studies"))
        except Exception as e:
            self._assoc = None
            self.after(0, lambda e=e: self._log.log_error(f"Study query error: {e}"))
        finally:
            self.after(0, lambda: self._reset_btn())

    def _do_mwl_refresh(self, cfg, ae):
        local_items = query_mwl_local(ae_title=cfg.get("ae_title", "SIMULATOR"))
        dicom_items = []
        assoc = None

        try:
            assoc = associate(ae, cfg["pacs_host"], cfg["pacs_port"], cfg["called_ae"])
            self._assoc = assoc
            if self._cancel.is_set():
                return
            if assoc.is_established:
                query = build_mwl_query(ae_title=cfg.get("ae_title", "SIMULATOR"))
                dicom_items = query_mwl_dicom(assoc, query)
                assoc.release()
                assoc = None
        except Exception as e:
            self.after(0, lambda e=e: self._log.log_error(f"MWL DICOM: {e} (using local data)"))
        finally:
            if assoc:
                assoc.release()
            self._assoc = None

        seen = {i.accession_number for i in dicom_items}
        for item in local_items:
            if item.accession_number not in seen:
                dicom_items.append(item)

        self._items = dicom_items
        self.after(0, self._update_table)
        self.after(0, lambda n=len(dicom_items): self._log.log_ok(f"MWL: {n} scheduled procedures"))
        self.after(0, lambda: self._reset_btn())

    def _reset_btn(self):
        self._refresh_btn.configure(state=tk.NORMAL)
        self._cancel_btn.configure(state=tk.DISABLED)

    def _update_table(self):
        for row in self._tree.get_children():
            self._tree.delete(row)
        for item in self._items:
            self._tree.insert("", tk.END, values=(
                item.patient_id,
                item.patient_name,
                item.study_date or item.scheduled_procedure_step_start_date,
                item.modality,
                item.accession_number,
                item.study_description or item.requested_procedure_description,
            ))
        self._count_lbl.configure(text=f"{len(self._items)} items")

    def _on_tree_select(self, event):
        sel = self._tree.selection()
        if not sel or not self._items:
            return
        idx = self._tree.index(sel[0])
        if 0 <= idx < len(self._items):
            self._on_select(self._items[idx])
