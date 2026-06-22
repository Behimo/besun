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
  title: `حریم خصوصی | ${BRAND.nameFa}`,
  description: `سیاست حریم خصوصی ${BRAND.nameFa} (${BRAND.nameEn}).`,
  canonical: '/front-pages/privacy',
})

const sections = [
  {
    title: 'داده‌هایی که جمع‌آوری می‌کنیم',
    body: 'شماره موبایل برای OTP، نام، ایمیل (اختیاری)، داده CRM مجموعه شما و لاگ فنی امنیتی.',
  },
  {
    title: 'استفاده از داده',
    body: 'فقط برای ارائه سرویس، پشتیبانی و بهبود محصول. هر Tenant داده جدا دارد.',
  },
  {
    title: 'امنیت',
    body: 'داده روی زیرساخت امن ذخیره می‌شود. دسترسی داخلی محدود و کنترل‌شده است.',
  },
  {
    title: 'حقوق شما',
    body: 'درخواست مشاهده، اصلاح یا حذف داده شخصی از طریق پشتیبانی.',
  },
  {
    title: 'تماس',
    body: BRAND.supportEmail,
  },
]
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
          <h1 class="text-h4 mb-6">
            حریم خصوصی {{ BRAND.nameFa }}
          </h1>
          <VCard
            v-for="(section, i) in sections"
            :key="i"
            class="mb-4"
          >
            <VCardText>
              <h2 class="text-h6 mb-2">
                {{ section.title }}
              </h2>
              <p class="mb-0">
                {{ section.body }}
              </p>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </VContainer>
    <Footer />
  </div>
</template>
