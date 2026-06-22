<script setup>
import CrmModulePlaceholder from '@/components/CrmModulePlaceholder.vue'

definePage({
  meta: {
    action: 'read',
    subject: 'BI',
  },
})

const BiDashboardView = defineAsyncComponent({
  loader: () => import('@/views/bi/BiDashboardView.vue'),
  delay: 0,
  timeout: 30000,
})

const BiReportBuilderView = defineAsyncComponent({
  loader: () => import('@/views/bi/BiReportBuilderView.vue'),
  delay: 0,
  timeout: 30000,
})

const { hasModule, hasCoreModule, userData } = useAppShell()

const activeTab = ref('dashboard')
const hasBiModule = computed(() => hasModule('mod-bi'))

const canAccessBi = computed(() => {
  if (userData.value?.tenant?.isOwner)
    return true

  return userData.value?.role === 'owner' || Boolean(userData.value?.isManager)
})
</script>

<template>
  <div>
    <CrmModulePlaceholder
      v-if="!hasBiModule"
      title="هوش تجاری BI"
      icon="tabler-chart-dots-3"
      description="داشبورد تحلیلی پیشرفته و گزارش‌ساز BI."
      module-slug="mod-bi"
    />

    <div v-else-if="!hasCoreModule">
      <VRow justify="center">
        <VCol
          cols="12"
          md="8"
        >
          <VCard>
            <VCardText class="text-center pa-8">
              <VAvatar
                color="warning"
                variant="tonal"
                size="72"
                rounded
                class="mb-4"
              >
                <VIcon
                  icon="tabler-lock"
                  size="36"
                />
              </VAvatar>
              <h4 class="text-h4 mb-2">
                ماژول پایه فعال نیست
              </h4>
              <p class="text-body-1 text-medium-emphasis mb-6">
                هوش تجاری BI نیاز به ماژول پایه CRM دارد.
              </p>
              <VBtn
                color="primary"
                :to="{ name: 'apps-tenant-modules' }"
              >
                وضعیت ماژول‌ها
              </VBtn>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>

    <div v-else-if="!canAccessBi">
      <VRow justify="center">
        <VCol
          cols="12"
          md="8"
        >
          <VAlert
            type="warning"
            variant="tonal"
            title="دسترسی محدود"
            text="هوش تجاری BI فقط برای مالک و مدیران مجموعه در دسترس است."
          />
        </VCol>
      </VRow>
    </div>

    <div v-else>
      <div class="d-flex flex-wrap align-center justify-space-between gap-4 mb-6">
        <div>
          <h4 class="text-h4 mb-1">
            هوش تجاری BI
          </h4>
          <p class="text-body-2 text-medium-emphasis mb-0">
            تحلیل پیشرفته، روند زمانی و گزارش‌ساز
          </p>
        </div>
      </div>

      <VTabs
        v-model="activeTab"
        class="mb-6"
      >
        <VTab value="dashboard">
          <VIcon
            icon="tabler-chart-dots-3"
            start
          />
          داشبورد
        </VTab>
        <VTab value="builder">
          <VIcon
            icon="tabler-report-analytics"
            start
          />
          گزارش‌ساز
        </VTab>
      </VTabs>

      <VWindow v-model="activeTab">
        <VWindowItem value="dashboard">
          <Suspense>
            <BiDashboardView />
            <template #fallback>
              <div class="d-flex justify-center py-12">
                <VProgressCircular
                  indeterminate
                  color="primary"
                />
              </div>
            </template>
          </Suspense>
        </VWindowItem>
        <VWindowItem value="builder">
          <Suspense>
            <BiReportBuilderView />
            <template #fallback>
              <div class="d-flex justify-center py-12">
                <VProgressCircular
                  indeterminate
                  color="primary"
                />
              </div>
            </template>
          </Suspense>
        </VWindowItem>
      </VWindow>
    </div>
  </div>
</template>
