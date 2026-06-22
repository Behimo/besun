<script setup>
import { formatRial } from '@/composables/usePlatformAdmin'

definePage({
  meta: {
    action: 'manage',
    subject: 'PlatformAdmin',
  },
})

const loading = ref(true)
const summary = ref(null)

const fetchDashboard = async () => {
  loading.value = true
  try {
    const res = await $api('/platform/dashboard')
    summary.value = res.summary ?? null
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(fetchDashboard)

const kpiCards = computed(() => {
  if (!summary.value)
    return []

  const t = summary.value.tenants
  const r = summary.value.revenue
  const o = summary.value.operations
  const m = summary.value.marketing

  return [
    { icon: 'tabler-building', color: 'primary', title: 'مجموعه‌ها', stat: t.total, subtitle: `${t.active_core} فعال · ${t.new_this_month} جدید` },
    { icon: 'tabler-currency-dollar', color: 'success', title: 'درآمد ماه', stat: formatRial(r.month), subtitle: `کل: ${formatRial(r.total)}` },
    { icon: 'tabler-chart-line', color: 'info', title: 'MRR تخمینی', stat: formatRial(r.mrr_estimate), subtitle: `ARPU: ${formatRial(r.arpu)}` },
    { icon: 'tabler-clock', color: 'warning', title: 'در آزمایش', stat: t.in_trial, subtitle: `${t.inactive_core} بدون ماژول پایه` },
    { icon: 'tabler-message-cog', color: 'secondary', title: 'SMS در انتظار', stat: o.pending_sms, subtitle: `${o.pending_invitations} دعوت باز` },
    { icon: 'tabler-mail-forward', color: 'error', title: 'لید سایت', stat: m.leads_this_month, subtitle: `کل: ${m.leads_total}` },
    { icon: 'tabler-receipt', color: 'primary', title: 'تراکنش امروز', stat: o.transactions_today, subtitle: `${o.failed_transactions_month} ناموفق این ماه` },
    { icon: 'tabler-ban', color: 'error', title: 'معلق', stat: t.suspended, subtitle: 'مجموعه' },
  ]
})

const quickLinks = [
  { title: 'گزارش‌ها', icon: 'tabler-report-analytics', route: 'apps-platform-reports', color: 'primary' },
  { title: 'تراکنش‌ها', icon: 'tabler-receipt', route: 'apps-platform-transactions', color: 'success' },
  { title: 'مجموعه‌ها', icon: 'tabler-building', route: 'apps-platform-tenants', color: 'info' },
  { title: 'لیدهای سایت', icon: 'tabler-mail', route: 'apps-platform-marketing-leads', color: 'warning' },
]
</script>

<template>
  <div>
    <div class="mb-6">
      <h4 class="text-h4 mb-1">
        داشبورد مدیریت پلتفرم
      </h4>
      <p class="text-body-2 text-medium-emphasis mb-0">
        نمای کلی کسب‌وکار SaaS — مجموعه‌ها، درآمد و عملیات
      </p>
    </div>

    <VRow v-if="loading">
      <VCol
        v-for="i in 4"
        :key="i"
        cols="12"
        sm="6"
        lg="3"
      >
        <VSkeletonLoader type="card" />
      </VCol>
    </VRow>

    <template v-else-if="summary">
      <VRow class="mb-2">
        <VCol
          v-for="card in kpiCards"
          :key="card.title"
          cols="12"
          sm="6"
          lg="3"
        >
          <VCard>
            <VCardText class="d-flex align-center gap-4">
              <VAvatar
                :color="card.color"
                variant="tonal"
                rounded
                size="48"
              >
                <VIcon :icon="card.icon" />
              </VAvatar>
              <div>
                <div class="text-body-2 text-medium-emphasis">
                  {{ card.title }}
                </div>
                <div class="text-h5">
                  {{ card.stat }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ card.subtitle }}
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <VRow>
        <VCol cols="12">
          <VCard title="دسترسی سریع">
            <VCardText class="d-flex flex-wrap gap-3">
              <VBtn
                v-for="link in quickLinks"
                :key="link.route"
                :color="link.color"
                variant="tonal"
                :to="{ name: link.route }"
                :prepend-icon="link.icon"
              >
                {{ link.title }}
              </VBtn>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </template>
  </div>
</template>
