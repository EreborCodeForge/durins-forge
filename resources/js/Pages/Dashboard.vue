<template>
  <AdminLayout :user="user" :notifications="notifications" :messages="messages">
    <h1 class="title">Dashboard</h1>

    <ul class="breadcrumbs">
      <li><a href="#">Home</a></li>
      <li class="divider">/</li>
      <li><a href="#" class="active">Dashboard</a></li>
    </ul>

    <InfoCards />

    <div class="data">
      <SalesReport />
      <Chatbox />
    </div>
  </AdminLayout>
</template>

<script>
import AdminLayout from '../Layouts/AdminLayout.vue';
import InfoCards from '../Components/Dashboard/InfoCards.vue';
import SalesReport from '../Components/Dashboard/SalesReport.vue';
import Chatbox from '../Components/Dashboard/Chatbox.vue';

export default {
  name: 'Dashboard',
  components: { AdminLayout, InfoCards, SalesReport, Chatbox },
  props: {
    user: Object,
  },
  data() {
      return {
          notifications: 5,
          messages: 8,
      }
  }
};
</script>
