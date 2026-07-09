from pydicom.dataset import Dataset


def store(assoc, ds: Dataset):
    if assoc is None:
        return None, "Association not established"
    status = assoc.send_c_store(ds)
    if status:
        return status.Status, status.get("ErrorComment", "")
    return None, "Association not established"
