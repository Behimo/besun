<script setup>
import Footer from '@/views/front-pages/front-page-footer.vue'
import Navbar from '@/views/front-pages/front-page-navbar.vue'
import { BRAND } from '@/config/brand'
import { useConfigStore } from '@core/stores/config'
import laptopGirl from '@images/illustrations/laptop-girl.png'

const store = useConfigStore()
store.skin = 'default'

definePage({
  meta: { layout: 'blank', public: true },
})

usePageSeo({
  title: `قیمت‌گذاری ${BRAND.nameFa} | ${BRAND.nameEn}`,
  description: `قیمت ماژول پایه CRM، پلن ماهانه، ۶ماهه و سالانه ${BRAND.nameFa}. ${BRAND.trialDays} روز رایگان.`,
  canonical: '/front-pages/pricing',
})

const faqs = [
  {
    question: 'قیمت بر چه اساسی است؟',
    answer: 'ماژول پایه CRM بر اساس تعداد صندلی (کاربر فعال در مجموعه) و دوره اشتراک (ماهانه، ۶ماهه، سالانه) محاسبه می‌شود.',
  },
  {
    question: 'آیا دوره آزمایشی دارید؟',
    answer: `بله — ${BRAND.trialDays} روز دسترسی کامل بدون کارت بانکی.`,
  },
  {
    question: 'ماژول افزونه چطور قیمت‌گذاری می‌شود؟',
    answer: 'پیامک، BI، اتوماسیون و... جدا از پایه — در فروشگاه ماژول داخل پنل قابل خرید هستند.',
  },
  {
    question: 'فاکتور رسمی صادر می‌شود؟',
    answer: 'برای اشتراک‌های سازمانی فاکتور رسمی صادر می‌گردد.',
  },
]
</script>

<template>
  <div class="pricing-page">
    <Navbar />

    <VCard class="pricing-card">
      <VContainer>
        <AppPricing md="4" />
      </VContainer>

      <div class="page-pricing-free-trial-banner-bg">
        <VContainer>
          <div class="d-flex align-center flex-md-row flex-column position-relative">
            <div class="text-center text-md-start pb-5 px-10 px-sm-0 pt-8">
              <h2 class="text-h4 text-primary mb-2">
                هنوز مطمئن نیستید؟
              </h2>
              <p class="text-body-1 mb-11">
                {{ BRAND.trialDays }} روز {{ BRAND.nameFa }} را رایگان امتحان کنید — قیف فروش، گزارش و کمپین.
              </p>
              <VBtn
                color="primary"
                :to="{ name: 'login' }"
              >
                شروع رایگان
              </VBtn>
            </div>
            <div class="free-trial-illustrator">
              <VImg
                :src="laptopGirl"
                :width="238"
                alt="آزمایش رایگان راهبر CRM"
              />
            </div>
          </div>
        </VContainer>
      </div>

      <div style="background-color: rgba(var(--v-theme-on-surface), var(--v-hover-opacity));">
        <VContainer>
          <VCardText class="py-10 py-sm-16">
            <div class="text-center mb-6">
              <h2 class="text-h4 mb-2">
                سوالات متداول قیمت‌گذاری
              </h2>
            </div>
            <VExpansionPanels>
              <VExpansionPanel
                v-for="(faq, index) in faqs"
                :key="faq.question"
                :title="faq.question"
                :text="faq.answer"
                :value="index"
              />
            </VExpansionPanels>
          </VCardText>
        </VContainer>
      </div>

      <Footer />
    </VCard>
  </div>
</template>

<style lang="scss" scoped>
.page-pricing-free-trial-banner-bg {
  background-color: rgba(var(--v-theme-primary), var(--v-activated-opacity));
  margin-block-start: 4rem !important;
}

.pricing-card {
  padding-block-start: 10.5rem !important;
}

@media screen and (min-width: 960px) {
  .free-trial-illustrator {
    position: absolute;
    inset-block-end: -1rem !important;
    inset-inline-end: 0%;
  }
}
</style>

<style lang="scss">
.pricing-page {
  @media (min-width: 600px) and (max-width: 960px) {
    .v-container {
      padding-inline: 2rem !important;
    }
  }
}
</style>
