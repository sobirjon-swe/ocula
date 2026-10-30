FROM node:22-bookworm-slim AS admin
WORKDIR /build
COPY admin/package.json admin/package-lock.json ./
RUN npm ci
COPY admin/src ./src
COPY admin/public ./public
COPY admin/index.html admin/tsconfig.json admin/vite.config.ts ./
RUN npm run build
FROM node:22-bookworm-slim AS customer
WORKDIR /build
COPY customer/package.json customer/package-lock.json ./
RUN npm ci
COPY customer/src ./src
COPY customer/public ./public
COPY customer/index.html customer/tsconfig.json customer/vite.config.ts ./
RUN npm run build
FROM nginx:stable-alpine
COPY --from=admin /build/dist /srv/admin
COPY --from=customer /build/dist /srv/customer
COPY docs/deployment/nginx.conf.template /etc/nginx/templates/default.conf.template
EXPOSE 8080 8081
