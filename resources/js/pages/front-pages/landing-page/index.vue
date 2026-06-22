<script setup>
import Footer from '@/views/front-pages/front-page-footer.vue'
import Navbar from '@/views/front-pages/front-page-navbar.vue'
import HeroSection from '@/views/front-pages/landing-page/hero-section.vue'
import { BRAND } from '@/config/brand'
import { useConfigStore } from '@core/stores/config'

const Features = defineAsyncComponent(() => import('@/views/front-pages/landing-page/features.vue'))
const ProductShowcase = defineAsyncComponent(() => import('@/views/front-pages/landing-page/product-showcase.vue'))
const CustomersReview = defineAsyncComponent(() => import('@/views/front-pages/landing-page/customers-review.vue'))
const PricingPlans = defineAsyncComponent(() => import('@/views/front-pages/landing-page/pricing-plans.vue'))
const ProductStats = defineAsyncComponent(() => import('@/views/front-pages/landing-page/product-stats.vue'))
const FaqSection = defineAsyncComponent(() => import('@/views/front-pages/landing-page/faq-section.vue'))
const Banner = defineAsyncComponent(() => import('@/views/front-pages/landing-page/banner.vue'))
const ContactUs = defineAsyncComponent(() => import('@/views/front-pages/landing-page/contact-us.vue'))

const store = useConfigStore()
store.skin = 'default'

definePage({
  alias: '/home',
  meta: {
    layout: 'blank',
    public: true,
  },
})

usePageSeo({
  title: `${BRAND.nameFa} | ${BRAND.nameEn} — CRM فارسی فروش و بازاریابی`,
  description: BRAND.description,
  canonical: '/home',
  ogImage: '/marketing/crm-dashboard.png',
  jsonLd: {
    '@context': 'https://schema.org',
    '@type': 'SoftwareApplication',
    name: BRAND.nameFa,
    alternateName: BRAND.nameEn,
    applicationCategory: 'BusinessApplication',
    operatingSystem: 'Web',
    inLanguage: 'fa',
    offers: {
      '@type': 'Offer',
      price: '0',
      priceCurrency: 'IRR',
      description: `آزمایش رایگان ${BRAND.trialDays} روزه`,
    },
  },
})

const activeSectionId = ref()
const refHome = ref()
const refFeatures = ref()
const refShowcase = ref()
const refContact = ref()
const refFaq = ref()

useIntersectionObserver(
  [refHome, refFeatures, refShowcase, refContact, refFaq],
  entries => {
    const visible = entries.find(entry => entry.isIntersecting)
    if (visible?.target?.id)
      activeSectionId.value = visible.target.id
  },
  { threshold: 0.25 },
)
</script>

<template>
  <div class="landing-page-wrapper">
    <Navbar :active-id="activeSectionId" />

    <HeroSection ref="refHome" />

    <div :style="{ 'background-color': 'rgb(var(--v-theme-surface))' }">
      <Features ref="refFeatures" />
    </div>

    <ProductShowcase ref="refShowcase" />

    <div :style="{ 'background-color': 'rgb(var(--v-theme-surface))' }">
      <CustomersReview />
    </div>

    <div :style="{ 'background-color': 'rgb(var(--v-theme-surface))' }">
      <PricingPlans />
    </div>

    <ProductStats />

    <div :style="{ 'background-color': 'rgb(var(--v-theme-surface))' }">
      <FaqSection ref="refFaq" />
    </div>

    <Banner />

    <ContactUs ref="refContact" />

    <Footer />
  </div>
</template>

<style lang="scss">
@media (max-width: 960px) and (min-width: 600px) {
  .landing-page-wrapper {
    .v-container {
      padding-inline: 2rem !important;
    }
  }
}
</style>
