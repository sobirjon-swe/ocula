FROM node:22-bookworm-slim AS build
WORKDIR /app
COPY landing/package.json landing/package-lock.json ./
RUN npm ci
COPY landing/app ./app
COPY landing/components ./components
COPY landing/lib ./lib
COPY landing/public ./public
COPY landing/tsconfig.json landing/next.config.ts landing/postcss.config.mjs landing/proxy.ts ./
ENV NEXT_TELEMETRY_DISABLED=1
RUN npm run build
FROM node:22-bookworm-slim
WORKDIR /app
ENV NODE_ENV=production NEXT_TELEMETRY_DISABLED=1
COPY --from=build --chown=node:node /app/package.json /app/package-lock.json ./
COPY --from=build --chown=node:node /app/node_modules ./node_modules
COPY --from=build --chown=node:node /app/.next ./.next
COPY --from=build --chown=node:node /app/public ./public
COPY --from=build --chown=node:node /app/next.config.ts ./
USER node
EXPOSE 3000
CMD ["npm", "start", "--", "--hostname", "0.0.0.0"]
