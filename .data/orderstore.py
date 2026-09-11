"""Keep real orders out of a test run's way, and put them back afterwards.

Every suite in here starts by emptying storage/orders, because the figures it
then asserts have to be exact — a reports total or a customer count cannot be
checked against a store with somebody else's orders in it.

That was harmless while the store only ever held what the suites themselves
had just made. It stopped being harmless the day .data/import_orders.php put
two years of the old shop's order archive in there: one `python
.data/test_reports.py`, or one `preflight.py --full`, and it was gone, with
nothing to put it back from but the SQL dump.

So the orders are moved aside rather than deleted, into a .held directory
inside the store — all_orders() globs *.json and never sees it — and moved
back at the end. The restore is registered with atexit as well as called
explicitly, so a suite that fails an assertion and exits, or throws, still
gives them back. If a run is killed outright the files are still there, in
storage/orders/.held, and running any suite again returns them.

    from orderstore import park
    park(ORD)          # instead of deleting every .json
"""
import atexit
import os
import shutil

HELD = '.held'


def _held_dir(ord_dir):
    return os.path.join(ord_dir, HELD)


def unpark(ord_dir):
    """Put back what park() moved, and clear out what the run left behind."""
    held = _held_dir(ord_dir)
    if not os.path.isdir(held):
        return 0

    # whatever the suite made is not wanted; the real ones are about to land
    for name in os.listdir(ord_dir):
        if name.endswith('.json'):
            try:
                os.remove(os.path.join(ord_dir, name))
            except OSError:
                pass

    back = 0
    for name in os.listdir(held):
        if not name.endswith('.json'):
            continue
        try:
            shutil.move(os.path.join(held, name), os.path.join(ord_dir, name))
            back += 1
        except OSError:
            pass

    try:
        os.rmdir(held)
    except OSError:
        pass
    return back


def park(ord_dir):
    """Empty the order store for the run, keeping whatever was in it."""
    os.makedirs(ord_dir, exist_ok=True)

    # A previous run that was killed outright left its orders held; give them
    # back before taking them away again, or the second run would bury them
    # under its own and the first lot would be lost for good.
    unpark(ord_dir)

    held = _held_dir(ord_dir)
    os.makedirs(held, exist_ok=True)

    moved = 0
    for name in os.listdir(ord_dir):
        if not name.endswith('.json'):
            continue
        try:
            shutil.move(os.path.join(ord_dir, name), os.path.join(held, name))
            moved += 1
        except OSError:
            pass

    atexit.register(unpark, ord_dir)
    if moved:
        print(f'  {moved} real order(s) held aside for this run')
    return moved
