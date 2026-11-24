import { defineConfig } from 'astro/config';

export default defineConfig({
  site: 'https://domen.site',
  output: 'static',
  server: {
    port: 8081,
    host: true,
  },
});
