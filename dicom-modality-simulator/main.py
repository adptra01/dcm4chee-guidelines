#!/usr/bin/env python3

from gui.main_window import MainWindow


def main():
    app = MainWindow()
    try:
        app.mainloop()
    except KeyboardInterrupt:
        app._on_close()


if __name__ == "__main__":
    main()
