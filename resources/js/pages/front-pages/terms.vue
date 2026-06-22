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
  title: `شرایط استفاده | ${BRAND.nameFa}`,
  description: `شرایط و قوانین استفاده از ${BRAND.nameFa} (${BRAND.nameEn}).`,
  canonical: '/front-pages/terms',
})

const sections = [
  {
    title: 'پذیرش شرایط',
    body: `با ثبت‌نام در ${BRAND.nameFa} این شرایط را می‌پذیرید. در صورت عدم موافقت، از سرویس استفاده نکنید.`,
  },
  {
    title: 'حساب کاربری',
    body: 'مسئولیت حفظ امنیت OTP و دسترسی اعضای تیم بر عهده مالک مجموعه است.',
  },
  {
    title: 'اشتراک و پرداخت',
    body: 'قیمت ماژول پایه بر اساس صندلی (کاربر) و دوره اشتراک محاسبه می‌شود. فاکتور و تراکنش در پنل قابل مشاهده است.',
  },
  {
    title: 'استفاده مجاز',
    body: 'استفاده برای فعالیت‌های غیرقانونی، spam یا نقض حقوق دیگران ممنوع است.',
  },
  {
    title: 'قطع سرویس',
    body: 'در صورت تخلف یا عدم پرداخت، دسترسی می‌تواند محدود شود. داده طبق سیاست حریم خصوصی نگهداری می‌شود.',
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
            شرایط استفاده {{ BRAND.nameFa }}
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
