#!/bin/bash
# ============================================================
# DCM4CHEE Archive - Podman Shutdown Script
# ============================================================
# Usage:
#   ./podman-down.sh        # Stop containers, keep volumes
#   ./podman-down.sh -v     # Stop AND remove volumes (DESTRUCTIVE!)
# ============================================================

set -e

REMOVE_VOLUMES=false
if [ "$1" = "-v" ] || [ "$1" = "--remove-volumes" ]; then
    REMOVE_VOLUMES=true
fi

echo ""
echo "Stopping DCM4CHEE containers..."

# Stop containers
for container in dcm4chee-arc dcm4chee-db dcm4chee-ldap; do
    if podman ps --format "{{.Names}}" | grep -q "^${container}$"; then
        echo "  Stopping $container..."
        podman stop "$container" 2>/dev/null || true
    fi
done

# Remove containers
for container in dcm4chee-arc dcm4chee-db dcm4chee-ldap; do
    if podman ps -a --format "{{.Names}}" | grep -q "^${container}$"; then
        echo "  Removing $container..."
        podman rm "$container" 2>/dev/null || true
    fi
done

# Remove volumes if requested
if [ "$REMOVE_VOLUMES" = true ]; then
    echo ""
    echo "WARNING: Removing volumes (DESTRUCTIVE!)..."
    for vol in ldap_data ldap_config db_data storage_data wildfly_data; do
        if podman volume inspect "dcm4chee_${vol}" >/dev/null 2>&1; then
            echo "  Removing volume dcm4chee_${vol}..."
            podman volume rm "dcm4chee_${vol}" 2>/dev/null || true
        fi
    done
    echo ""
    echo "  WARNING: ALL DATA HAS BEEN DELETED!"
fi

# Remove network (optional)
if podman network inspect dcm4chee-net >/dev/null 2>&1; then
    echo "Removing network..."
    podman network rm dcm4chee-net 2>/dev/null || true
fi

echo ""
echo "DCM4CHEE stopped."
echo "To start again: ./podman-up.sh"