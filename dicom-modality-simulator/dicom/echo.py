def echo(assoc):
    if assoc is None:
        return None, "Association not established"
    status = assoc.send_c_echo()
    if status:
        return status.Status, status.get("ErrorComment", "")
    return None, "Association not established"
