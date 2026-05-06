# syntax=docker/dockerfile:1
FROM node:20-alpine AS base
WORKDIR /app
COPY package*.json ./

FROM base AS dev
RUN npm install
COPY scripts ./scripts
COPY src ./src
EXPOSE 3000
CMD ["node", "src/server.js"]

FROM base AS production
ENV NODE_ENV=production
COPY scripts ./scripts
COPY src ./src
RUN npm install --omit=dev
EXPOSE 3000
USER node
CMD ["node", "src/server.js"]
