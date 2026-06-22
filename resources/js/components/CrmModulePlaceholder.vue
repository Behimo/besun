<script setup>
import { ADDON_MODULES } from '@/config/crm-modules'

const props = defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  icon: { type: String, default: 'tabler-tools' },
  moduleSlug: { type: String, default: '' },
})

const userData = useCookie('userData')

const isActive = computed(() => {
  if (!props.moduleSlug)
    return false

  return (userData.value?.activeModules ?? []).includes(props.moduleSlug)
})

const moduleTitle = computed(() =>
  ADDON_MODULES.find(m => m.slug === props.moduleSlug)?.title ?? props.title)

const message = computed(() => {
  if (isActive.value)
    return props.description || 'این ماژول فعال است و امکانات آن به‌زودی در دسترس قرار می‌گیرد.'

  return `برای استفاده از «${moduleTitle.value}»، ابتدا این افزونه را از فروشگاه ماژول در حساب کاربری خریداری کنید.`
})

const storeLink = computed(() => ({
  name: 'apps-account-modules',
  query: userData.value?.tenant?.id ? { tenant: userData.value.tenant.id } : {},
}))
</script>

<template>
  <VRow justify="center">
    <VCol
      cols="12"
      md="8"
      lg="6"
    >
      <VCard>
        <VCardText class="text-center pa-8">
          <VAvatar
            :color="isActive ? 'success' : 'info'"
            variant="tonal"
            size="72"
            rounded
            class="mb-4"
          >
            <VIcon
              :icon="icon"
              size="36"
            />
          </VAvatar>
          <VChip
            v-if="moduleSlug"
            :color="isActive ? 'success' : 'info'"
            size="small"
            variant="tonal"
            class="mb-3"
          >
            {{ isActive ? 'فعال' : 'افزونه' }}
          </VChip>
          <h4 class="text-h4 mb-2">
            {{ title }}
          </h4>
          <p class="text-body-1 text-medium-emphasis mb-6">
            {{ message }}
          </p>
          <VBtn
            v-if="!isActive"
            color="primary"
            :to="storeLink"
            prepend-icon="tabler-shopping-cart"
          >
            فروشگاه ماژول
          </VBtn>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
