# CI-only candidate; retain Redis engine, entrypoint and other packages.
FROM docker.io/library/redis:8.2.11-alpine3.22@sha256:a18fce2f705d9782ccf6968f8dd94fa04566ff97e10a8d322ad72645243e4c0c
RUN --mount=type=bind,source=.,target=/patch,ro \
    set -eu; \
    before="$(apk info -v | sort | grep -Ev '^lib(crypto|ssl)3-[0-9]')"; \
    apk info -v | grep -Fx 'libcrypto3-3.5.9-r0'; \
    apk info -v | grep -Fx 'libssl3-3.5.9-r0'; \
    apk verify /patch/libcrypto3-3.5.9-r2.apk /patch/libssl3-3.5.9-r2.apk; \
    apk add --no-network --no-cache /patch/libcrypto3-3.5.9-r2.apk /patch/libssl3-3.5.9-r2.apk; \
    apk info -v | grep -Fx 'libcrypto3-3.5.9-r2'; \
    apk info -v | grep -Fx 'libssl3-3.5.9-r2'; \
    test "$(apk info -v | sort | grep -Ev '^lib(crypto|ssl)3-[0-9]')" = "$before"; \
    install -d -m 0755 /usr/share/licenses/elmo-f04-redis; \
    install -m 0644 /patch/notices/*.txt /usr/share/licenses/elmo-f04-redis/
