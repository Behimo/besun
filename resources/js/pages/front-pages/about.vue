<script setup>
import Footer from '@/views/front-pages/front-page-footer.vue'
import Navbar from '@/views/front-pages/front-page-navbar.vue'
import { BRAND } from '@/config/brand'
import { useConfigStore } from '@core/stores/config'

const store = useConfigStore()
store.skin = 'default'

definePage({
  meta: { layout: 'blank', public: true },
})

usePageSeo({
  title: `درباره ${BRAND.nameFa} | ${BRAND.nameEn}`,
  description: `آشنایی با ${BRAND.nameFa} — CRM فارسی SaaS برای فروش، بازاریابی و مدیریت تیم.`,
  canonical: '/front-pages/about',
})
</script>

<template>
  <div class="landing-page-wrapper">
    <Navbar />

    <VContainer class="py-16">
      <VRow justify="center">
        <VCol
          cols="12"
          md="8"
        >
          <div class="text-center mb-10">
            <h1 class="text-h3 font-weight-bold mb-4">
              درباره {{ BRAND.nameFa }}
            </h1>
            <p class="text-h6 text-medium-emphasis">
              {{ BRAND.nameEn }} — {{ BRAND.tagline }}
            </p>
          </div>

          <VCard class="mb-6">
            <VCardText>
              <h2 class="text-h5 mb-3">
                ما کی هستیم؟
              </h2>
              <p>
                {{ BRAND.nameFa }} نرم‌افزار CRM ابری (SaaS) است که برای تیم‌های فروش و بازاریابی ایرانی طراحی شده.
                از ثبت لید و کمپین تا قیف فروش، گزارش ریزش و مدیریت کاربر — همه در یک پنل فارسی RTL.
              </p>
            </VCardText>
          </VCard>

          <VCard class="mb-6">
            <VCardText>
              <h2 class="text-h5 mb-3">
                چرا {{ BRAND.nameFa }}؟
              </h2>
              <VList>
                <VListItem prepend-icon="tabler-chart-funnel">
                  <VListItemTitle>قیف فروش و بازاریابی با گزارش ارزش هر مرحله</VListItemTitle>
                </VListItem>
                <VListItem prepend-icon="tabler-building-community">
                  <VListItemTitle>چند مجموعه و Workspace — داده ایزوله</VListItemTitle>
                </VListItem>
                <VListItem prepend-icon="tabler-shield-lock">
                  <VListItemTitle>نقش Owner، Admin و Employee با CASL</VListItemTitle>
                </VListItem>
                <VListItem prepend-icon="tabler-puzzle">
                  <VListItemTitle>ماژول پایه + افزونه پیامک، BI و اتوماسیون</VListItemTitle>
                </VListItem>
                <VListItem prepend-icon="tabler-calendar">
                  <VListItemTitle>{{ BRAND.trialDays }} روز آزمایش رایگان</VListItemTitle>
                </VListItem>
              </VList>
            </VCardText>
          </VCard>

          <VRow class="mb-8">
            <VCol
              cols="12"
              md="6"
            >
              <VImg
                src="/marketing/crm-dashboard.png"
                alt="پیشخوان راهبر CRM"
                class="rounded-lg"
                cover
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VImg
                src="/marketing/crm-kanban.png"
                alt="قیف فروش راهبر CRM"
                class="rounded-lg"
                cover
              />
            </VCol>
          </VRow>

          <div class="text-center">
            <VBtn
              color="primary"
              size="large"
              :to="{ name: 'login' }"
              class="me-3"
            >
              شروع {{ BRAND.trialDays }} روز رایگان
            </VBtn>
            <VBtn
              variant="tonal"
              size="large"
              :to="{ name: 'front-pages-pricing' }"
            >
              قیمت‌گذاری
            </VBtn>
          </div>
        </VCol>
      </VRow>
    </VContainer>

    <Footer />
  </div>
</template>
