#! /bin/bash
echo "Resetting web UI admin password to default."
echo "UPDATE ADMIN SET HASH = '\$2a\$12\$3rYrrUoe62DkIgvZUE6UJOFuIkVPirH8k8oL8Cj74QBDrb7Gy8fFi'  WHERE ID = 1;" | sqlite3 /opt/f5backup/db/ui.db