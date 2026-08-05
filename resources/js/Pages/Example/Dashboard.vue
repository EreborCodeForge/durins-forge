<template>
  <div class="min-h-screen bg-gray-100 font-sans">
    <!-- Navbar -->
    <nav class="bg-white shadow-sm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex">
            <div class="flex-shrink-0 flex items-center">
              <h1 class="text-xl font-bold text-gray-800">{{ appName }}</h1>
            </div>
          </div>
          <div class="flex items-center space-x-4">
             <span class="text-sm text-gray-500 mr-4" v-if="auth?.user">Welcome, {{ auth.user.name }}</span>
             <a href="/" mithril-link class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">Home</a>
             <a href="/example" mithril-link class="text-emerald-600 hover:text-emerald-900 px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
          </div>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
      <div class="mb-8">
        <h2 class="text-3xl font-bold text-gray-900">Dashboard Overview</h2>
        <p class="mt-2 text-gray-600">A demonstration of Mithril-Vue Bridge with Tailwind CSS.</p>
      </div>

      <!-- Stats Grid -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div v-for="(stat, index) in stats" :key="index" class="bg-white overflow-hidden shadow rounded-lg transform hover:scale-105 transition duration-300">
          <div class="p-5">
            <div class="flex items-center">
              <div :class="['flex-shrink-0 rounded-md p-3', stat.color]">
                <!-- Heroicon name: outline/users -->
                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
              </div>
              <div class="ml-5 w-0 flex-1">
                <dl>
                  <dt class="text-sm font-medium text-gray-500 truncate">{{ stat.label }}</dt>
                  <dd>
                    <div class="text-lg font-medium text-gray-900">{{ stat.value }}</div>
                  </dd>
                </dl>
              </div>
            </div>
          </div>
          <div class="bg-gray-50 px-5 py-3">
            <div class="text-sm">
              <span class="font-medium" :class="stat.change.startsWith('+') ? 'text-green-600' : 'text-red-600'">
                 {{ stat.change }}
              </span>
              <span class="text-gray-500"> from last month</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Example Card -->
      <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
          <h3 class="text-lg leading-6 font-medium text-gray-900">Interactive Component</h3>
          <div class="mt-2 max-w-xl text-sm text-gray-500">
            <p>This is a Vue component fully hydrated and interactive.</p>
          </div>
          <div class="mt-5">
            <button @click="toggle" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
              {{ isOpen ? 'Hide Details' : 'Show Details' }}
            </button>
          </div>
          
          <transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-1"
          >
            <div v-if="isOpen" class="mt-4 p-4 bg-gray-50 rounded-md border border-gray-200">
                <p class="text-gray-700">Here are the hidden details revealed by Vue's reactivity system!</p>
            </div>
          </transition>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  stats: Array,
  appName: String,
  auth: Object,
  flash: Object
});

const isOpen = ref(false);

const toggle = () => {
    isOpen.value = !isOpen.value;
};
</script>
