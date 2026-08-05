<template>
  <div class="content-data">
    <div class="head">
      <h3>Sales Report</h3>

      <div class="menu" ref="menu" @click.stop="menuOpen = !menuOpen">
        <i class='bx bx-dots-horizontal-rounded icon'></i>
        <ul class="menu-link" :class="{ show: menuOpen }">
          <li><a href="#">Edit</a></li>
          <li><a href="#">Save</a></li>
          <li><a href="#">Remove</a></li>
        </ul>
      </div>
    </div>

    <div class="chart">
      <apexchart type="area" height="280" :options="chartOptions" :series="series"></apexchart>
    </div>
  </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';

export default {
  name: 'SalesReport',
  components: {
    apexchart: VueApexCharts,
  },

  data() {
    return {
      menuOpen: false,
      series: [{ name: 'Sales', data: [10, 22, 18, 35, 28, 46, 40, 62, 55, 78, 70, 92] }],
      chartOptions: {
        chart: { type: 'area', height: 280, toolbar: { show: false } },
        xaxis: { categories: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth' },
      },
    };
  },

  mounted() {
    document.addEventListener('click', this.onDocClick);
  },

  beforeUnmount() {
    document.removeEventListener('click', this.onDocClick);
  },

  methods: {
    onDocClick(e) {
      const menu = this.$refs.menu;
      if (!menu) return;
      if (!menu.contains(e.target)) this.menuOpen = false;
    },
  },
};
</script>
