<template>
  <div class="flex items-center justify-center min-h-screen bg-gray-900 text-white">
    <div class="text-center">
      <h1 class="text-6xl font-bold text-emerald-400 mb-4">Durin's Forge</h1>
      <p class="text-xl text-gray-300 mb-8">{{ message }}</p>
      
      <div class="p-6 bg-gray-800 rounded-lg shadow-lg">
        <h2 class="text-2xl font-semibold mb-4">Mithril-Vue Bridge Active</h2>
        <p>This page is rendered by Vue 3, powered by MithrilPHP.</p>
        
        <div class="mt-6">
            <button @click="count++" class="px-4 py-2 bg-emerald-600 rounded hover:bg-emerald-500 transition">
                Count is: {{ count }}
            </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  message: String
});

const count = ref(0);
</script>
