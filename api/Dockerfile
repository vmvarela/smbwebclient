FROM node:18-alpine

WORKDIR /app

COPY package*.json ./
RUN npm install

COPY . .

EXPOSE 3000

# Fix for OpenSSL 3 issue with NTLM/smb2
ENV NODE_OPTIONS=--openssl-legacy-provider

CMD ["node", "src/index.js"]
