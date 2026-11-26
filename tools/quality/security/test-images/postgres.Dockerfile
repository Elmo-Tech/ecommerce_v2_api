# CI-only candidate, not an approved test or production image.
FROM docker.io/library/postgres:17.12-alpine3.23@sha256:833be86fa9cbc2e12ca52ecf8afa306a3f20f6f57772037caeae65075aeb4616
COPY --chown=0:0 --chmod=0755 gosu /usr/local/bin/gosu
