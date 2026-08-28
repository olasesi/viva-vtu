FROM node:20-alpine AS base
WORKDIR /app

FROM base AS deps
COPY package.json package-lock.json* ./
COPY prisma ./prisma/
RUN npm ci

FROM base AS build
COPY --from=deps /app/node_modules ./node_modules
COPY . .
RUN npx prisma generate

FROM base AS runner
RUN addgroup --system --gid 1001 nodejs && adduser --system --uid 1001 appuser
COPY --from=build /app/node_modules ./node_modules
COPY --from=build /app/package.json ./package.json
COPY --from=build /app/src ./src
COPY --from=build /app/prisma ./prisma

USER appuser
EXPOSE 3001
ENV NODE_ENV=production

CMD ["sh", "-c", "npx prisma migrate deploy && node src/server.js"]
