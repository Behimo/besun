<script setup>
import ChartJsBarChart from '@/views/charts/chartjs/ChartJsBarChart.vue'
import ChartJsBubbleChart from '@/views/charts/chartjs/ChartJsBubbleChart.vue'
import ChartJsHorizontalBarChart from '@/views/charts/chartjs/ChartJsHorizontalBarChart.vue'
import ChartJsLineAreaChart from '@/views/charts/chartjs/ChartJsLineAreaChart.vue'
import ChartJsLineChart from '@/views/charts/chartjs/ChartJsLineChart.vue'
import ChartJsPolarAreaChart from '@/views/charts/chartjs/ChartJsPolarAreaChart.vue'
import ChartJsRadarChart from '@/views/charts/chartjs/ChartJsRadarChart.vue'
import ChartJsScatterChart from '@/views/charts/chartjs/ChartJsScatterChart.vue'

const chartJsCustomColors = {
  white: '#fff',
  yellow: '#D4AF37',
  primary: '#4A0E17',
  areaChartBlue: '#C75C6D',
  barChartYellow: '#D4AF37',
  polarChartGrey: '#4f5d70',
  polarChartInfo: '#C75C6D',
  lineChartYellow: '#D4AF37',
  polarChartGreen: '#7A2B39',
  lineChartPrimary: '#4A0E17',
  lineChartWarning: '#B99425',
  horizontalBarInfo: '#C75C6D',
  polarChartWarning: '#D4AF37',
  scatterChartGreen: '#7A2B39',
  warningShade: '#E6D38B',
  areaChartBlueLight: '#E8C5CB',
  areaChartGreyLight: '#edf1f4',
  scatterChartWarning: '#D4AF37',
}
</script>

<template>
  <VRow id="chartjs-wrapper">
    <!-- 👉 Statistics Line Chart  -->
    <VCol cols="12">
      <VCard
        title="Statistics"
        subtitle="Commercial networks and enterprises"
      >
        <VCardText>
          <ChartJsLineChart :colors="chartJsCustomColors" />
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉 Radar Chart -->
    <VCol
      cols="12"
      md="6"
    >
      <VCard title="Radar Chart">
        <VCardText>
          <ChartJsRadarChart />
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉 Average Skills Polar Area Chart -->
    <VCol
      cols="12"
      md="6"
    >
      <VCard title="Average Skills">
        <VCardText>
          <ChartJsPolarAreaChart :colors="chartJsCustomColors" />
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉  Bubble Chart -->
    <VCol cols="12">
      <VCard title="Bubble Chart">
        <template #append>
          <span class="text-subtitle-2">$ 100,000</span>
        </template>

        <VCardText>
          <ChartJsBubbleChart :colors="chartJsCustomColors" />
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉 New Product Data Scatter Chart -->
    <VCol cols="12">
      <VCard>
        <VCardItem class="d-flex flex-wrap justify-space-between gap-4">
          <VCardTitle>New Product Data</VCardTitle>

          <template #append>
            <VBtnToggle
              color="primary"
              variant="outlined"
              density="compact"
            >
              <VBtn>Daily</VBtn>
              <VBtn>Monthly</VBtn>
              <VBtn>Yearly</VBtn>
            </VBtnToggle>
          </template>
        </VCardItem>

        <VCardText>
          <ChartJsScatterChart :colors="chartJsCustomColors" />
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉 Data Science Area Line Chart -->
    <VCol cols="12">
      <VCard>
        <VCardItem class="d-flex flex-wrap justify-space-between gap-4">
          <VCardTitle>
            Data Science
          </VCardTitle>

          <template #append>
            <div class="date-picker-wrapper">
              <AppDateTimePicker
                model-value="2022-06-09"
                prepend-inner-icon="tabler-calendar"
                placeholder="Select Date"
                :config="$vuetify.display.smAndDown ? { position: 'auto center' } : { position: 'auto right' }"
              />
            </div>
          </template>
        </VCardItem>

        <VCardText>
          <ChartJsLineAreaChart :colors="chartJsCustomColors" />
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉 Latest Statistics -->
    <VCol
      cols="12"
      md="6"
    >
      <VCard>
        <VCardItem class="d-flex flex-wrap justify-space-between gap-4">
          <VCardTitle>Latest Statistics</VCardTitle>

          <template #append>
            <div class="date-picker-wrapper">
              <AppDateTimePicker
                model-value="2022-06-09"
                prepend-inner-icon="tabler-calendar"
                placeholder="Select Date"
                :config="$vuetify.display.smAndDown ? { position: 'auto center' } : { position: 'auto right' }"
              />
            </div>
          </template>
        </VCardItem>

        <VCardText>
          <ChartJsBarChart :colors="chartJsCustomColors" />
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉 Balance Horizontal Bar Chart -->
    <VCol
      cols="12"
      md="6"
    >
      <VCard>
        <VCardItem class="d-flex flex-wrap justify-space-between gap-4">
          <VCardTitle>Balance</VCardTitle>
          <VCardSubtitle>$74,123</VCardSubtitle>

          <template #append>
            <div class="date-picker-wrapper">
              <AppDateTimePicker
                model-value="2022-06-09"
                prepend-inner-icon="tabler-calendar"
                placeholder="Select Date"
                :config="$vuetify.display.smAndDown ? { position: 'auto center' } : { position: 'auto right' }"
              />
            </div>
          </template>
        </VCardItem>

        <VCardText>
          <ChartJsHorizontalBarChart :colors="chartJsCustomColors" />
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss">
.date-picker-wrapper {
  inline-size: 10.5rem;
}

#chartjs-wrapper {
  .v-card-item__append {
    padding-inline-start: 0;
  }
}
</style>
