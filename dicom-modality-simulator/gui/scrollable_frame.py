import tkinter as tk
from tkinter import ttk

from gui.theme import BG


class ScrollableFrame(ttk.Frame):
    def __init__(self, parent, *args, **kwargs):
        super().__init__(parent, *args, **kwargs)
        canvas = tk.Canvas(self, highlightthickness=0, borderwidth=0,
                           background=BG)
        scroll = ttk.Scrollbar(self, orient=tk.VERTICAL, command=canvas.yview)
        self.inner = ttk.Frame(canvas)

        def _configure_inner(e):
            canvas.configure(scrollregion=canvas.bbox("all"))
        self.inner.bind("<Configure>", _configure_inner)

        self._window_id = canvas.create_window((0, 0), window=self.inner, anchor="nw")
        def _configure_canvas(e):
            canvas.itemconfig(self._window_id, width=e.width)
        canvas.bind("<Configure>", _configure_canvas)

        canvas.configure(yscrollcommand=scroll.set)
        canvas.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        scroll.pack(side=tk.RIGHT, fill=tk.Y)
