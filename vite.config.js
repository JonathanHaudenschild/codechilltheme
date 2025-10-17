import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/postcss'
import autoprefixer from 'autoprefixer'

export default defineConfig({
  build: {
    outDir: 'assets/build',
    rollupOptions: {
      input: 'assets/css/src.css',
      output: {
        entryFileNames: 'main.js',
        assetFileNames: 'main.css'
      }
    }
  },
  css: {
    postcss: {
      plugins: [
        tailwindcss,
        autoprefixer
      ]
    }
  }
})