<script setup>
import Footer from '@/views/front-pages/front-page-footer.vue'
import Navbar from '@/views/front-pages/front-page-navbar.vue'
import { useConfigStore } from '@core/stores/config'

const store = useConfigStore()

store.skin = 'default'
definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const radioContent = [
  {
    title: 'درگاه بانکی',
    value: 'bank',
    icon: 'tabler-building-bank',
  },
  {
    title: 'پرداخت سازمانی',
    value: 'invoice',
    icon: 'tabler-file-invoice',
  },
]

const selectedRadio = ref('bank')
const isPricingPlanDialogVisible = ref(false)
</script>

<template>
  <div class="payment-page">
    <Navbar />

    <VContainer>
      <div class="d-flex justify-center align-center payment-card">
        <VCard width="100%">
          <VRow>
            <VCol
              cols="12"
              md="7"
              :class="$vuetify.display.mdAndUp ? 'border-e' : 'border-b'"
            >
              <VCardText class="pa-8 pe-5">
                <div>
                  <h4 class="text-h4 mb-2">
                    تسویه حساب
                  </h4>
                  <div class="text-body-1">
                    تمام پلن‌ها شامل امکانات CRM، Workspace و پشتیبانی فارسی هستند. پلن مناسب خود را انتخاب کنید.
                  </div>
                </div>

                <CustomRadios
                  v-model:selected-radio="selectedRadio"
                  :radio-content="radioContent"
                  :grid-column="{ cols: '12', sm: '6' }"
                  class="my-8"
                >
                  <template #default="{ item }">
                    <div class="d-flex align-center gap-x-4 ms-3">
                      <VIcon
                        :icon="item.icon"
                        size="32"
                        color="primary"
                      />
                      <h6 class="text-h6">
                        {{ item.title }}
                      </h6>
                    </div>
                  </template>
                </CustomRadios>

                <div class="mb-8">
                  <h4 class="text-h4 mb-6">
                    اطلاعات صورتحساب
                  </h4>
                  <VForm>
                    <VRow>
                      <VCol
                        cols="12"
                        md="6"
                      >
                        <AppTextField
                          label="ایمیل"
                          type="email"
                          placeholder="email@example.com"
                        />
                      </VCol>
                      <VCol
                        cols="12"
                        md="6"
                      >
                        <AppTextField
                          label="نام شرکت"
                          placeholder="نام سازمان"
                        />
                      </VCol>
                      <VCol
                        cols="12"
                        md="6"
                      >
                        <AppTextField
                          label="شماره تماس"
                          placeholder="09123456789"
                        />
                      </VCol>
                      <VCol
                        cols="12"
                        md="6"
                      >
                        <AppTextField
                          label="کد پستی"
                          placeholder="1234567890"
                        />
                      </VCol>
                    </VRow>
                  </VForm>
                </div>
              </VCardText>
            </VCol>

            <VCol
              cols="12"
              md="5"
            >
              <VCardText class="pa-8 ps-5">
                <div class="mb-8">
                  <h4 class="text-h4 mb-2">
                    خلاصه سفارش
                  </h4>
                  <div class="text-body-1">
                    جزئیات اشتراک انتخابی و مبلغ قابل پرداخت
                  </div>
                </div>

                <VCard
                  flat
                  color="rgba(var(--v-theme-on-surface), var(--v-hover-opacity))"
                >
                  <VCardText>
                    <div class="text-body-1">
                      پلن حرفه‌ای
                    </div>
                    <h1 class="text-h1 my-4">
                      ۷۵۰٬۰۰۰<span class="text-body-1 font-weight-medium"> ریال / ماه</span>
                    </h1>
                    <VBtn
                      variant="tonal"
                      block
                      @click="isPricingPlanDialogVisible = !isPricingPlanDialogVisible"
                    >
                      تغییر پلن
                    </VBtn>
                  </VCardText>
                </VCard>

                <div class="my-5">
                  <div class="d-flex justify-space-between mb-2">
                    <span>اشتراک</span>
                    <h6 class="text-h6">
                      ۷۵۰٬۰۰۰ ریال
                    </h6>
                  </div>
                  <div class="d-flex justify-space-between">
                    <span>مالیات</span>
                    <h6 class="text-h6">
                      ۰ ریال
                    </h6>
                  </div>
                  <VDivider class="my-4" />
                  <div class="d-flex justify-space-between">
                    <span>جمع کل</span>
                    <h6 class="text-h6">
                      ۷۵۰٬۰۰۰ ریال
                    </h6>
                  </div>
                </div>

                <VBtn
                  block
                  color="success"
                  class="mb-8"
                >
                  <template #append>
                    <VIcon
                      icon="tabler-arrow-right"
                      class="flip-in-rtl"
                    />
                  </template>
                  پرداخت و فعال‌سازی
                </VBtn>

                <div class="text-body-1">
                  با ادامه، شرایط استفاده و سیاست حریم خصوصی راهبر CRM را می‌پذیرید.
                </div>
              </VCardText>
            </VCol>
          </VRow>
        </VCard>
      </div>
    </VContainer>

    <Footer />

    <PricingPlanDialog v-model:is-dialog-visible="isPricingPlanDialogVisible" />
  </div>
</template>

<style lang="scss" scoped>
.footer {
  position: static !important;
  inline-size: 100%;
  inset-block-end: 0;
}

.payment-card {
  margin-block: 10.5rem 5.25rem;
}

.payment-page {
  @media (min-width: 600px) and (max-width: 960px) {
    .v-container {
      padding-inline: 2rem !important;
    }
  }
}
</style>

<style lang="scss">
.payment-card {
  .custom-radio {
    .v-radio {
      margin-block-start: 0 !important;
    }
  }
}
</style>
