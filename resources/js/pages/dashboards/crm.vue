<script setup>
import { ADDON_MODULES } from '@/config/crm-modules'
import CrmSalesFunnel from '@/views/dashboards/crm/CrmSalesFunnel.vue'

const { userData } = useAppShell()
const { formatDateTime } = useJalaliDate()

const stats = ref(null)
const loading = ref(true)

const hasCore = computed(() => Boolean(userData.value?.hasCoreModule))

const lockedAddons = computed(() => {
  if (!hasCore.value)
    return []

  const active = userData.value?.activeModules ?? []

  return ADDON_MODULES.filter(mod => !active.includes(mod.slug))
})

const fetchStats = async () => {
  if (!hasCore.value) {
    loading.value = false

    return
  }

  loading.value = true
  try {
    stats.value = await $api('/dashboard/stats')
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(fetchStats)

const summaryCards = computed(() => {
  if (!stats.value?.summary)
    return []

  const s = stats.value.summary

  return [
    { icon: 'tabler-users', color: 'primary', title: 'مخاطبین', stat: s.total_contacts, route: 'apps-crm-contacts' },
    { icon: 'tabler-target', color: 'info', title: 'لیدها', stat: s.total_leads, route: 'apps-crm-leads' },
    { icon: 'tabler-chart-funnel', color: 'success', title: 'معاملات', stat: s.total_deals, route: 'apps-crm-deals' },
    { icon: 'tabler-percentage', color: 'warning', title: 'نرخ تبدیل', stat: `${s.conversion_rate}%`, route: 'apps-crm-leads' },
    { icon: 'tabler-checkbox', color: 'error', title: 'تسک باز', stat: s.pending_tasks, route: 'apps-crm-tasks' },
    { icon: 'tabler-calendar-event', color: 'info', title: 'تسک امروز', stat: s.my_due_today ?? 0, route: 'apps-crm-tasks' },
    { icon: 'tabler-bell', color: 'warning', title: 'پیگیری امروز', stat: s.my_follow_ups_today ?? 0, route: 'apps-crm-leads' },
    { icon: 'tabler-alert-circle', color: 'error', title: 'سررسید گذشته', stat: s.my_overdue_tasks ?? 0, route: 'apps-crm-tasks' },
    { icon: 'tabler-currency-dollar', color: 'secondary', title: 'درآمد', stat: Number(s.total_revenue).toLocaleString('fa-IR'), route: 'apps-crm-deals' },
  ]
})

const quickLinks = [
  { title: 'مخاطبین', icon: 'tabler-address-book', route: 'apps-crm-contacts', color: 'primary' },
  { title: 'لیدها', icon: 'tabler-user-search', route: 'apps-crm-leads', color: 'info' },
  { title: 'قیف فروش', icon: 'tabler-chart-funnel', route: 'apps-crm-deals', color: 'success' },
  { title: 'گزارش فروش', icon: 'tabler-report-analytics', route: 'apps-crm-reports', color: 'secondary' },
  { title: 'تسک‌ها', icon: 'tabler-checkbox', route: 'apps-crm-tasks', color: 'warning' },
]

const activityTypeLabel = type => ({
  call: 'تماس',
  meeting: 'جلسه',
  note: 'یادداشت',
}[type] ?? type)
</script>

<template>
  <div v-if="!hasCore">
    <VRow justify="center">
      <VCol
        cols="12"
        md="8"
        lg="6"
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
              برای مشاهده پیشخوان مجموعه، ابتدا ماژول پایه را از فروشگاه ماژول خریداری کنید.
            </p>
            <VBtn
              color="primary"
              :to="{ name: 'apps-tenant-modules' }"
              prepend-icon="tabler-shopping-cart"
            >
              وضعیت ماژول‌ها
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>

  <div v-else>
    <VAlert
      v-if="lockedAddons.length"
      type="info"
      variant="tonal"
      class="mb-4"
    >
      {{ lockedAddons.length }} ماژول تکمیلی هنوز فعال نشده است.
      <RouterLink
        :to="{ name: 'apps-tenant-modules' }"
        class="ms-1"
      >
        مشاهده وضعیت ماژول‌ها
      </RouterLink>
    </VAlert>

    <VProgressLinear
      v-if="loading"
      indeterminate
      class="mb-4"
    />

    <VRow
      v-if="summaryCards.length"
      class="mb-4"
    >
      <VCol
        v-for="card in summaryCards"
        :key="card.title"
        cols="12"
        sm="6"
        md="4"
        lg="3"
      >
        <VCard
          :to="{ name: card.route }"
          hover
        >
          <VCardText>
            <VAvatar
              :color="card.color"
              variant="tonal"
              rounded
              size="44"
            >
              <VIcon
                :icon="card.icon"
                size="28"
              />
            </VAvatar>
            <h5 class="text-h5 mt-3">
              {{ card.title }}
            </h5>
            <p class="mb-0 text-high-emphasis">
              {{ card.stat }}
            </p>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VRow>
      <VCol
        cols="12"
        lg="8"
      >
        <CrmSalesFunnel :stages="stats?.sales_by_stage ?? []" />
      </VCol>

      <VCol
        cols="12"
        lg="4"
      >
        <VCard title="دسترسی سریع">
          <VCardText>
            <VList class="card-list">
              <VListItem
                v-for="link in quickLinks"
                :key="link.route"
                :to="{ name: link.route }"
                rounded
              >
                <template #prepend>
                  <VAvatar
                    :color="link.color"
                    variant="tonal"
                    rounded
                    size="36"
                  >
                    <VIcon :icon="link.icon" />
                  </VAvatar>
                </template>
                <VListItemTitle>{{ link.title }}</VListItemTitle>
                <template #append>
                  <VIcon
                    icon="tabler-chevron-left"
                    size="18"
                  />
                </template>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12">
        <VCard title="فعالیت‌های اخیر">
          <VCardText>
            <div
              v-if="!stats?.recent_activities?.length"
              class="text-medium-emphasis text-center py-6"
            >
              فعالیتی ثبت نشده است.
            </div>
            <VTimeline
              v-else
              side="end"
              align="start"
              truncate-line="both"
              density="compact"
            >
              <VTimelineItem
                v-for="activity in stats.recent_activities"
                :key="activity.id"
                dot-color="primary"
                size="x-small"
              >
                <div class="d-flex justify-space-between gap-4 flex-wrap">
                  <div>
                    <div class="font-weight-medium">
                      {{ activity.subject || activityTypeLabel(activity.type) }}
                    </div>
                    <div class="text-body-2 text-medium-emphasis">
                      {{ activity.user?.name ?? 'کاربر' }}
                      —
                      {{ activityTypeLabel(activity.type) }}
                    </div>
                    <div
                      v-if="activity.body"
                      class="text-body-2 mt-1"
                    >
                      {{ activity.body }}
                    </div>
                  </div>
                  <span class="text-caption text-medium-emphasis text-no-wrap">
                    {{ formatDateTime(activity.happened_at) }}
                  </span>
                </div>
              </VTimelineItem>
            </VTimeline>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
