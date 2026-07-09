import tkinter as tk
from tkinter import ttk
import threading

from gui.theme import BG, FG, BG2, ENTRY_BG


class LogWidget(ttk.Frame):
    def __init__(self, parent):
        super().__init__(parent)
        self._create_widgets()

    def _create_widgets(self):
        f = ttk.LabelFrame(self, text="Log", padding=4)
        f.pack(fill=tk.BOTH, expand=True)
        self._text = tk.Text(f, height=10, wrap=tk.WORD, state=tk.DISABLED,
                             font=("Consolas", 9), bg=BG2, fg=FG,
                             insertbackground=FG, relief=tk.FLAT, bd=2,
                             padx=6, pady=4)
        scroll = ttk.Scrollbar(f, orient=tk.VERTICAL, command=self._text.yview)
        self._text.configure(yscrollcommand=scroll.set)
        self._text.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        scroll.pack(side=tk.RIGHT, fill=tk.Y)

    def log(self, msg):
        def _do():
            self._text.configure(state=tk.NORMAL)
            self._text.insert(tk.END, msg + "\n")
            self._text.see(tk.END)
            self._text.configure(state=tk.DISABLED)
        if threading.current_thread() is threading.main_thread():
            _do()
        else:
            self.after(0, _do)

    def log_info(self, msg):
        self.log(f"[INFO] {msg}")

    def log_ok(self, msg):
        self.log(f"[OK]   {msg}")

    def log_error(self, msg):
        self.log(f"[ERR]  {msg}")

    def log_raw(self, msg):
        self.log(f"  {msg}")
