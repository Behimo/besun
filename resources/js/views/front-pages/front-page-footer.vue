<script setup>
import footerDarkBg from '@images/front-pages/backgrounds/footer-bg-dark.png'
import footerLightBg from '@images/front-pages/backgrounds/footer-bg-light.png'
import { BRAND } from '@/config/brand'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

const footerBg = useGenerateImageVariant(footerLightBg, footerDarkBg)

const pagesList = [
  { name: 'قیمت‌گذاری', to: { name: 'front-pages-pricing' } },
  { name: 'درباره ما', to: { name: 'front-pages-about' } },
  { name: 'مرکز راهنما', to: { name: 'front-pages-help-center' } },
  { name: 'حریم خصوصی', to: { name: 'front-pages-privacy' } },
  { name: 'شرایط استفاده', to: { name: 'front-pages-terms' } },
  { name: 'ورود', to: { name: 'login' } },
]

const modulesList = [
  { title: 'قیف فروش', desc: 'کاریز معاملات' },
  { title: 'بازاریابی', desc: 'کمپین و لید' },
  { title: 'گزارش', desc: 'تحلیل فروش و تیم' },
  { title: 'چت تیم', desc: 'ارتباط داخلی' },
]
</script>

<template>
  <div class="footer">
    <div
      class="footer-top pt-11"
      :style="{ 'background-image': `url(${footerBg})` }"
    >
      <VContainer>
        <VRow>
          <VCol
            cols="12"
            md="5"
          >
            <div
              class="mb-4"
              :class="$vuetify.display.smAndDown ? 'w-100' : 'w-75'"
            >
              <div class="app-logo mb-6">
                <VNodeRenderer :nodes="themeConfig.app.logo" />
                <h2 class="app-logo-title text-white">
                  {{ themeConfig.app.title }}
                </h2>
              </div>

              <p
                class="mb-6"
                :class="$vuetify.theme.current.dark ? 'text-body-1' : 'text-white-variant'"
              >
                {{ BRAND.tagline }} — {{ BRAND.nameEn }} SaaS با Workspace جدا،
                گزارش قیف فروش و ماژول‌های پیامک و اتوماسیون.
              </p>
              <VBtn
                color="primary"
                :to="{ name: 'login' }"
                prepend-icon="tabler-login"
              >
                {{ BRAND.trialDays }} روز رایگان — شروع کنید
              </VBtn>
            </div>
          </VCol>

          <VCol
            md="2"
            sm="4"
            xs="6"
          >
            <div class="footer-links">
              <h6 class="footer-title text-h6 mb-6">
                امکانات پایه
              </h6>
              <ul style="list-style: none;">
                <li
                  v-for="(item, index) in modulesList"
                  :key="index"
                  class="mb-4"
                >
                  <span :class="$vuetify.theme.current.dark ? 'text-body-1' : 'text-white-variant'">
                    {{ item.title }}
                    <span class="text-caption d-block opacity-70">{{ item.desc }}</span>
                  </span>
                </li>
              </ul>
            </div>
          </VCol>

          <VCol
            md="2"
            sm="4"
            xs="6"
          >
            <div class="footer-links">
              <h6 class="footer-title text-h6 mb-6">
                صفحات
              </h6>
              <ul style="list-style: none;">
                <li
                  v-for="(item, index) in pagesList"
                  :key="index"
                  class="mb-4"
                >
                  <RouterLink
                    :class="$vuetify.theme.current.dark ? 'text-body-1' : 'text-white-variant'"
                    :to="item.to"
                  >
                    {{ item.name }}
                  </RouterLink>
                </li>
              </ul>
            </div>
          </VCol>

          <VCol
            cols="12"
            md="3"
            sm="4"
          >
            <div>
              <h6 class="footer-title text-h6 mb-6">
                تماس
              </h6>
              <p :class="$vuetify.theme.current.dark ? 'text-body-1' : 'text-white-variant'">
                <VIcon
                  icon="tabler-mail"
                  size="18"
                  class="me-2"
                />
                {{ BRAND.supportEmail }}
              </p>
              <p :class="$vuetify.theme.current.dark ? 'text-body-1' : 'text-white-variant'">
                <VIcon
                  icon="tabler-map-pin"
                  size="18"
                  class="me-2"
                />
                تهران، ایران
              </p>
              <VBtn
                variant="outlined"
                color="white"
                class="mt-4"
                :to="{ path: '/home', hash: '#contact-us' }"
              >
                فرم تماس
              </VBtn>
            </div>
          </VCol>
        </VRow>
      </VContainer>
    </div>

    <div class="footer-line w-100">
      <VContainer>
        <div class="d-flex justify-space-between flex-wrap gap-y-5 align-center">
          <div class="text-body-1 text-white-variant text-wrap me-4">
            &copy; {{ new Date().getFullYear() }} {{ BRAND.nameFa }} ({{ BRAND.nameEn }}) — تمامی حقوق محفوظ است.
          </div>

          <div class="d-flex gap-x-6 flex-wrap">
            <RouterLink
              :to="{ name: 'front-pages-privacy' }"
              class="text-white"
            >
              حریم خصوصی
            </RouterLink>
            <RouterLink
              :to="{ name: 'front-pages-terms' }"
              class="text-white"
            >
              شرایط استفاده
            </RouterLink>
            <RouterLink
              :to="{ name: 'front-pages-help-center' }"
              class="text-white"
            >
              راهنما
            </RouterLink>
          </div>
        </div>
      </VContainer>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.footer-title {
  color: rgba(255, 255, 255, 92%);
}

.footer-top {
  border-radius: 60px 60px 0 0;
  background-size: cover;
  color: #fff;
}

.footer-links {
  .text-white-variant,
  .text-body-1 {
    &:hover {
      color: #fff;
    }
  }
}

.footer-line {
  background: #282c3e;
}
</style>

<style lang="scss">
.footer {
  border-radius: 50%;

  @media (min-width: 600px) and (max-width: 960px) {
    .v-container {
      padding-inline: 2rem !important;
    }
  }
}
</style>
