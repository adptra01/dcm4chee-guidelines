from tkinter import ttk


BG = "#1e1e1e"
BG2 = "#252526"
FG = "#cccccc"
FG2 = "#969696"
SELECT = "#094771"
SELECT_FG = "#ffffff"
BUTTON = "#0e639c"
BUTTON_FG = "#ffffff"
ENTRY_BG = "#3c3c3c"
TREE_BG = "#2d2d30"
TREE_ALT = "#252526"
TREE_SELECT = "#094771"
ACCENT = "#007acc"
RED = "#f14c4c"
GREEN = "#6a9955"
ORANGE = "#cc7832"


def apply(root):
    root.tk_setPalette(
        background=BG,
        foreground=FG,
        highlightColor=SELECT,
        selectColor=SELECT,
        selectBackground=SELECT,
        selectForeground=SELECT_FG,
        activeBackground=BUTTON,
        activeForeground=BUTTON_FG,
        insertBackground=FG,
    )

    style = ttk.Style()
    style.theme_use("clam")

    style.configure(".", background=BG, foreground=FG, fieldbackground=BG,
                    troughcolor=BG2, selectbackground=SELECT, selectforeground=SELECT_FG)

    style.configure("TFrame", background=BG)
    style.configure("TLabelframe", background=BG, foreground=FG)
    style.configure("TLabelframe.Label", background=BG, foreground=FG)

    style.configure("TLabel", background=BG, foreground=FG)
    style.configure("TLabelBold.TLabel", font=("", 9, "bold"))

    style.configure("TButton", background=BUTTON, foreground=BUTTON_FG,
                    borderwidth=0, focusthickness=0, padding=4)
    style.map("TButton", background=[("active", "#1177bb"), ("pressed", "#0a5280")])

    style.configure("TEntry", fieldbackground=ENTRY_BG, foreground=FG,
                    insertcolor=FG, borderwidth=0, padding=4)

    style.configure("Treeview", background=TREE_BG, foreground=FG,
                    fieldbackground=TREE_BG, rowheight=24)
    style.map("Treeview", background=[("selected", TREE_SELECT)],
              foreground=[("selected", SELECT_FG)])
    style.configure("Treeview.Heading", background=BG2, foreground=FG,
                    borderwidth=0, padding=4)
    style.map("Treeview.Heading", background=[("active", SELECT)])

    style.configure("TRadiobutton", background=BG, foreground=FG)
    style.map("TRadiobutton", background=[("active", BG2)])

    style.configure("TSeparator", background=BG2)

    style.configure("Horizontal.TScrollbar", background=BG2, troughcolor=BG,
                    borderwidth=0, arrowcolor=FG)
    style.configure("Vertical.TScrollbar", background=BG2, troughcolor=BG,
                    borderwidth=0, arrowcolor=FG)
