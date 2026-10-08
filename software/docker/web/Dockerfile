# syntax=docker/dockerfile:1
# Imagen web: build de Vite -> Nginx no root que sirve la SPA y reenvía la API (mismo origen).
# Contexto de construcción: software/.

ARG NODE_IMAGE=node:22.23.1-alpine3.23
ARG NGINX_IMAGE=nginxinc/nginx-unprivileged:1.30.3-alpine3.23

# --- build: dependencias desde el lockfile y compilación (tsc + vite) ---
FROM ${NODE_IMAGE} AS build

WORKDIR /src
COPY web/package.json web/package-lock.json ./
RUN --mount=type=cache,target=/root/.npm \
    npm ci --no-audit --no-fund
COPY web/ ./
RUN npm run build

# --- prod: solo artefactos compilados y configuración de Nginx; usuario nginx (uid 101) ---
FROM ${NGINX_IMAGE} AS prod

USER root
RUN rm -rf /usr/share/nginx/html/* /etc/nginx/conf.d/default.conf
COPY docker/web/nginx.conf /etc/nginx/nginx.conf
COPY docker/web/security-headers.conf docker/web/api-proxy.conf /etc/nginx/snippets/
# La plantilla la resuelve el entrypoint de la imagen (envsubst) hacia /etc/nginx/conf.d/.
COPY docker/web/default.conf.template /etc/nginx/templates/default.conf.template
COPY --from=build /src/dist/ /usr/share/nginx/html/
USER 101

# Destino del proxy y DNS interno de Docker (resolución dinámica: sobrevive a la recreación de api).
ENV API_UPSTREAM=api:8080 \
    NGINX_ENTRYPOINT_LOCAL_RESOLVERS=1 \
    NGINX_ENTRYPOINT_QUIET_LOGS=1

EXPOSE 8080
