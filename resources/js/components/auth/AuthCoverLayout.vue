<script setup>
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

defineProps({
  tagline: {
    type: String,
    default: 'راهبر CRM — فروش، بازاریابی و گزارش در یک پنل فارسی',
  },
})
</script>

<template>
  <div class="auth-mobile-page">
    <VRow
      no-gutters
      class="auth-wrapper bg-surface"
    >
      <VCol
        cols="12"
        md="5"
        lg="6"
        class="auth-card-v2 d-flex align-center justify-center order-2 order-md-1"
      >
        <div class="auth-form-shell w-100">
          <slot />
        </div>
      </VCol>

      <VCol
        cols="12"
        md="7"
        lg="6"
        class="d-none d-md-flex order-1 order-md-2"
      >
        <div class="auth-brand-panel w-100">
          <div class="auth-brand-panel__pattern" />
          <div class="auth-brand-panel__content text-center">
            <div class="auth-brand-panel__logo mb-6">
              <VNodeRenderer :nodes="themeConfig.app.logo" />
            </div>
            <h2 class="auth-brand-panel__title mb-3">
              {{ themeConfig.app.title }}
            </h2>
            <p class="auth-brand-panel__tagline mb-0">
              {{ tagline }}
            </p>
          </div>
        </div>
      </VCol>
    </VRow>
  </div>
</template>

<style lang="scss" scoped>
.auth-mobile-page {
  min-block-size: 100dvh;
}

.auth-form-shell {
  max-inline-size: 420px;
  margin-inline: auto;
  padding: 2rem 1.5rem;
}

.auth-brand-panel {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  min-block-size: 100%;
  overflow: hidden;
  background: linear-gradient(145deg, #1a2f5c 0%, #243b6e 45%, #1e3568 100%);
  color: #fff;

  &__pattern {
    position: absolute;
    inset: 0;
    opacity: 0.35;
    background:
      radial-gradient(circle at 20% 30%, rgba(255, 255, 255, 0.12) 0, transparent 45%),
      radial-gradient(circle at 80% 70%, rgba(255, 255, 255, 0.08) 0, transparent 40%),
      radial-gradient(circle at 50% 50%, rgba(255, 255, 255, 0.05) 0, transparent 55%);
  }

  &__content {
    position: relative;
    z-index: 1;
    padding: 3rem 2rem;
  }

  &__logo {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 1.25rem;
    border-radius: 1rem;
    background: rgb(var(--v-theme-surface));
    color: rgb(var(--v-theme-primary));
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);

    :deep(svg) {
      block-size: 3rem;
      inline-size: 3rem;
    }
  }

  &__title {
    font-size: 1.75rem;
    font-weight: 700;
    letter-spacing: 0.02em;
  }

  &__tagline {
    font-size: 1rem;
    line-height: 1.8;
    opacity: 0.9;
    max-inline-size: 22rem;
    margin-inline: auto;
  }
}
</style>
