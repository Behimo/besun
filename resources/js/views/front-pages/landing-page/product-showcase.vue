<script setup>
const panels = [
  {
    title: 'پیشخوان مجموعه',
    desc: 'نمای کلی از مخاطبین، لیدها، معاملات، نرخ تبدیل و قیف فروش — همه در یک صفحه.',
    image: '/marketing/crm-dashboard.png',
    alt: 'پیشخوان راهبر CRM — داشبورد فروش و CRM فارسی',
    icon: 'tabler-smart-home',
  },
  {
    title: 'قیف فروش کاریز',
    desc: 'مدیریت معاملات در مراحل سرنخ تا برنده با drag & drop و ثبت مبلغ هر معامله.',
    image: '/marketing/crm-kanban.png',
    alt: 'قیف فروش راهبر CRM — کاریز معاملات',
    icon: 'tabler-layout-kanban',
  },
  {
    title: 'گزارش فروش و بازاریابی',
    desc: 'ارزش هر مرحله، نرخ ریزش، عملکرد کمپین و مقایسه فروشندگان — بدون نیاز به اکسل.',
    image: '/marketing/crm-reports.png',
    alt: 'گزارش فروش راهبر CRM — تحلیل قیف و تیم',
    icon: 'tabler-report-analytics',
  },
]

const active = ref(0)
</script>

<template>
  <VContainer id="showcase">
    <div class="product-showcase py-12">
      <div class="headers d-flex justify-center flex-column align-center mb-10">
        <VChip
          label
          color="primary"
          class="mb-4"
          size="small"
        >
          نمای پنل
        </VChip>
        <h2 class="text-h4 text-center mb-2">
          همان چیزی که بعد از خرید می‌بینید
        </h2>
        <p class="text-body-1 text-center mb-0 text-medium-emphasis">
          رابط فارسی، RTL و آماده تیم فروش و بازاریابی — از روز اول
        </p>
      </div>

      <VRow align="center">
        <VCol
          cols="12"
          md="5"
        >
          <VList
            bg-color="transparent"
            class="panel-tabs"
          >
            <VListItem
              v-for="(panel, index) in panels"
              :key="panel.title"
              rounded
              :active="active === index"
              color="primary"
              @click="active = index"
            >
              <template #prepend>
                <VAvatar
                  :color="active === index ? 'primary' : 'secondary'"
                  :variant="active === index ? 'flat' : 'tonal'"
                  rounded
                  size="40"
                >
                  <VIcon :icon="panel.icon" />
                </VAvatar>
              </template>
              <VListItemTitle class="font-weight-medium">
                {{ panel.title }}
              </VListItemTitle>
              <VListItemSubtitle>{{ panel.desc }}</VListItemSubtitle>
            </VListItem>
          </VList>
        </VCol>

        <VCol
          cols="12"
          md="7"
        >
          <VCard
            elevation="8"
            class="overflow-hidden panel-shot"
          >
            <VImg
              :src="panels[active].image"
              :alt="panels[active].alt"
              cover
              aspect-ratio="16/10"
            />
          </VCard>
        </VCol>
      </VRow>
    </div>
  </VContainer>
</template>

<style lang="scss" scoped>
.product-showcase {
  margin-block: 4rem;
}

.panel-shot {
  border-radius: 12px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.panel-tabs .v-list-item {
  margin-block-end: 0.5rem;
  cursor: pointer;
}
</style>
