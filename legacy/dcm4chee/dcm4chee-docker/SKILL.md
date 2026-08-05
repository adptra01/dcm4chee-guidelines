# DCM4CHEE Docker Deployment Skill

## Description

Deploy, configure, and manage DCM4CHEE Archive 5.x using Docker. Covers minimum setup, secured setup, Elastic Stack integration, and multi-host distribution.

## Prerequisites

- Docker Engine 24.0+ and Docker Compose v2
- 8GB+ RAM recommended
- Ports 8080, 9990, 11112, 5432, 389 available

## Quick Commands

### 1. Clone & Start Minimum Setup

```bash
git clone https://github.com/dcm4che/dcm4chee-arc-light.git
cd dcm4chee-arc-light/docker/
docker-compose up -d
```

### 2. Check Status

```bash
docker-compose ps
docker logs -f <container_name>
```

### 3. Stop / Restart

```bash
docker-compose down
docker-compose up -d
```

### 4. View Logs

```bash
docker-compose logs -f [arc|ldap|db]
```

## Deployment Modes

### Mode 1: Minimum (Single Host)

Archive + PostgreSQL + OpenLDAP only. No SSL, no Elastic Stack.

```bash
cd docker/
curl -LO https://raw.githubusercontent.com/dcm4che/dcm4chee-arc-light/master/docker/docker-compose.yml
# Edit ARCHIVE_STORAGE_DIR to point to your DICOM storage path
docker-compose up -d
```

### Mode 2: Secured (Single Host)

Adds Keycloak SSO + OAuth2 proxy. Includes SSL.

```bash
# Uses separate compose file
docker-compose -f docker-compose-sqlite-isp.yml up -d
# Or download secured variant
curl -LO https://raw.githubusercontent.com/dcm4che/dcm4chee-arc-light/master/docker/docker-compose-ssl.yml
```

### Mode 3: Secured + Elastic Stack

Adds Elasticsearch, Logstash, Kibana for audit logging and monitoring.

```bash
docker-compose -f docker-compose-full.yml up -d
```

### Mode 4: Distributed (Multi-Host)

Distribute services across multiple Docker hosts without or with Docker Swarm.
See: https://github.com/dcm4che/dcm4chee-arc-light/wiki/Distribute-secured-archive-services-and-Elastic-Stack-over-several-hosts-without-Docker-Swarm

## Key Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `ARCHIVE_DEVICE_NAME` | `DCM4CHEE` | Archive AE title |
| `ARCHIVE_STORAGE_DIR` | `/home/data/dcm4chee/archive` | DICOM file storage path |
| `POSTGRES_HOST` | `db` | PostgreSQL hostname |
| `LDAP_URL` | `ldap://ldap` | OpenLDAP URL |
| `LDAP_BASE_DN` | `dc=dcm4che,dc=org` | LDAP base DN |
| `SLAPD_PASSWORD` | `changeit` | LDAP admin password |
| `DB_VENDOR` | `POSTGRESQL` | Database vendor |
| `ARCHIVE_PORT` | `8080` | HTTP port |
| `ARCHIVE_SSL_PORT` | `8443` | HTTPS port |
| `ARCHIVE_DICOM_PORT` | `11112` | DICOM port |

## Key URLs

| Service | URL | Credentials |
|---------|-----|-------------|
| Archive UI | `http://<host>:8080/dcm4chee-arc/ui2` | root/changeit (auth+admin) |
| Wildfly Console | `http://<host>:9990` | root/changeit |
| QIDO-RS | `http://<host>:8080/dcm4chee-arc/aets/DCM4CHEE/rs` | — |
| WADO-RS | `http://<host>:8080/dcm4chee-arc/aets/DCM4CHEE/rs` | — |
| WADO-URI | `http://<host>:8080/dcm4chee-arc/aets/DCM4CHEE/wado` | — |
| Kibana (secured) | `http://<host>:5601` | via OAuth2 proxy |

## Customizing Storage Path

Edit `docker-compose.yml` and set:

```yaml
services:
  arc:
    environment:
      ARCHIVE_STORAGE_DIR: /your/custom/storage
    volumes:
      - /your/custom/storage:/home/data/dcm4chee/archive
```

## Using with OHIF Viewer

```js
window.config = {
  dataSources: [{
    namespace: '@ohif/extension-default.dataSourcesModule.dicomweb',
    configuration: {
      name: 'DCM4CHEE',
      wadoUriRoot: 'http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/wado',
      qidoRoot:   'http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs',
      wadoRoot:   'http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs',
      supportsFuzzyMatching: true,
      supportsWildcard: true,
      imageRendering: 'wadors',
      thumbnailRendering: 'wadors',
    }
  }]
}
```

## Skill Workflows

### deploy-minimum
Deploy minimum set of archive services on a single Docker host.
1. Check Docker availability
2. Download docker-compose.yml
3. Configure ARCHIVE_STORAGE_DIR
4. Run docker-compose up -d
5. Verify containers are healthy
6. Report key URLs

### deploy-secured
Deploy secured archive services with Keycloak SSO.
1. Download docker-compose-ssl.yml
2. Configure SSL certificates or use self-signed
3. Set Keycloak realm and client credentials
4. Run docker-compose up -d
5. Verify Keycloak is running
6. Report secured URLs

### deploy-full
Deploy secured services + Elastic Stack (Elasticsearch, Logstash, Kibana).
1. Download docker-compose-full.yml
2. Configure Elasticsearch heap and storage
3. Configure ARCHIVE_AET for Kibana access
4. Run docker-compose up -d
5. Verify all services healthy
6. Report Kibana URL via OAuth2 proxy

### check-status
Check health of all running DCM4CHEE containers.
1. List running containers
2. Check container health status
3. Show container resource usage
4. Check Wildfly deployment status
5. Report any unhealthy services

### inspect-logs
Inspect logs from specific container or all containers.
1. Identify target container
2. Tail recent logs
3. Search for errors/warnings
4. Extract relevant entries
5. Report findings

### configure-storage
Configure external storage path for DICOM files.
1. Check current ARCHIVE_STORAGE_DIR setting
2. Create target directory with proper permissions
3. Update docker-compose.yml
4. Restart arc container
5. Verify storage mount

### troubleshoot
Diagnose common DCM4CHEE Docker issues.
1. Check container logs for errors
2. Verify network connectivity between containers
3. Check database connectivity
4. Verify LDAP authentication
5. Check disk space / storage permissions
6. Report diagnosis and fix suggestions

## Common Issues & Fixes

### Container exits immediately
```bash
docker logs <container>
# Usually: missing volume mounts, port conflicts, or bad env vars
```

### Archive UI blank / 500 error
```bash
# Check Wildfly deployed successfully
docker exec <arc_container> /opt/wildfly/bin/jboss-cli.sh -c ":read-attribute(name=server-state)"
# Check database connection
docker exec <db_container> pg_isready
```

### DICOM C-ECHO fails
- Verify ARCHIVE_DICOM_PORT (default 11112) is exposed
- Check firewall rules
- Verify AE title matches client configuration

### Slow performance / OOM
```bash
# Increase Java heap for Wildfly
docker exec <arc_container> /opt/wildfly/bin/jboss-cli.sh -c '/system-property=java.xmx:write-attribute(name=value,value="4g")'
```

## Links

- Wiki: https://github.com/dcm4che/dcm4chee-arc-light/wiki/Running-on-Docker
- Docker Hub: https://hub.docker.com/u/dcm4che
- Latest compose files: https://github.com/dcm4che/dcm4chee-arc-light/tree/master/docker