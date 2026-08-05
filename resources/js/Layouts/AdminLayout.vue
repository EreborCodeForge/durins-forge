<template>
  <section id="sidebar" :class="{ hide: sidebarHidden }">
    <Sidebar :activePage="activePage" />
  </section>

  <section id="content">
    <Navbar
      :notifications="notifications"
      :messages="messages"
      :user="user"
      @toggle-sidebar="toggleSidebar"
    />

    <main>
      <slot />
    </main>
  </section>
</template>

<script>
import Sidebar from '../Components/Dashboard/Sidebar.vue';
import Navbar from '../Components/Dashboard/Navbar.vue';

export default {
  name: 'AdminLayout',
  components: { Sidebar, Navbar },
  
  props: {
      user: { type: Object, required: true },
      notifications: { type: Number, default: 0 },
      messages: { type: Number, default: 0 },
  },

  data() {
    return {
      sidebarHidden: false,
      activePage: 'Dashboard',
    };
  },

  methods: {
    toggleSidebar() {
      this.sidebarHidden = !this.sidebarHidden;
    },
  },
};
</script>
