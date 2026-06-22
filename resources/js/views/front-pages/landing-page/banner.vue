<script setup>
import { BRAND } from '@/config/brand'

const ctaImage = '/marketing/crm-reports.png'
</script>

<template>
  <div
    class="landing-cta bg-surface"
    :class="$vuetify.theme.current.dark ? 'banner-bg-dark' : 'banner-bg-light'"
  >
    <VContainer>
      <div class="d-flex justify-center justify-md-space-between flex-wrap gap-6 gap-x-10 position-relative pt-12">
        <div class="align-self-center">
          <div class="banner-title text-primary mb-1">
            آماده فروش حرفه‌ای هستید؟
          </div>
          <h2 class="text-h5 text-medium-emphasis mb-8">
            {{ BRAND.trialDays }} روز {{ BRAND.nameFa }} — بدون کارت بانکی
          </h2>
          <VBtn
            color="primary"
            :to="{ name: 'login' }"
            :size="$vuetify.display.smAndUp ? 'large' : 'default' "
            class="me-3"
          >
            شروع رایگان
          </VBtn>
          <VBtn
            variant="tonal"
            :to="{ name: 'front-pages-help-center' }"
            :size="$vuetify.display.smAndUp ? 'large' : 'default' "
          >
            مرکز راهنما
          </VBtn>
        </div>

        <div class="banner-img">
          <img
            :src="ctaImage"
            class="w-100 rounded-lg"
            :alt="`گزارش فروش ${BRAND.nameFa}`"
          >
        </div>
      </div>
    </VContainer>
  </div>
</template>

<style lang="scss">
.landing-cta {
  background-size: cover;
  margin-block: auto;
}

.banner-img {
  margin-block-end: -22px;
}

.banner-title {
  font-size: 34px;
  font-weight: 700;
  line-height: 44px;
}

.banner-bg-light {
  background-image: url("@images/front-pages/backgrounds/cta-bg.png");
}

.banner-bg-dark {
  background-image: url("@images/front-pages/backgrounds/cta-bg-dark.png");
}
</style>
