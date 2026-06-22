<script setup>
definePage({
  meta: {
    action: 'read',
    subject: 'PlatformSupport',
  },
})

const { formatDateTime } = useJalaliDate()

const loading = ref(true)
const data = ref(null)

const fetchDashboard = async () => {
  loading.value = true
  try {
    data.value = await $api('/platform/support/dashboard')
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(fetchDashboard)

const summaryCards = computed(() => {
  const s = data.value?.summary
  if (!s)
    return []

  return [
    { icon: 'tabler-message-cog', color: 'warning', title: 'SMS در انتظار', stat: s.pending_sms },
    { icon: 'tabler-alert-triangle', color: 'error', title: 'نیازمند کمک', stat: s.tenants_needing_help },
    { icon: 'tabler-receipt-off', color: 'secondary', title: 'تراکنش ناموفق (هفته)', stat: s.failed_transactions_week },
    { icon: 'tabler-ticket', color: 'info', title: 'تیکت باز', stat: s.open_tickets },
  ]
})
</script>

<template>
  <div>
    <div class="mb-6">
      <h4 class="text-h4 mb-1">
        داشبورد پشتیبانی
      </h4>
      <p class="text-body-2 text-medium-emphasis mb-0">
        صف عملیات — SMS، مجموعه‌های نیازمند کمک و تیکت‌ها
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

    <template v-else-if="data">
      <VRow class="mb-4">
        <VCol
          v-for="card in summaryCards"
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
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <VRow>
        <VCol
          cols="12"
          lg="6"
        >
          <VCard title="درخواست SMS در انتظار">
            <VList v-if="data.pending_sms?.length">
              <VListItem
                v-for="item in data.pending_sms"
                :key="item.tenant_id"
                :title="item.tenant_name"
                :subtitle="`${item.company ?? ''} · ${item.mobile_number}`"
              >
                <template #append>
                  <span class="text-caption">{{ formatDateTime(item.created_at) }}</span>
                </template>
              </VListItem>
            </VList>
            <VCardText
              v-else
              class="text-medium-emphasis"
            >
              صفی وجود ندارد
            </VCardText>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          lg="6"
        >
          <VCard title="مجموعه‌های نیازمند کمک">
            <VList v-if="data.tenants_needing_help?.length">
              <VListItem
                v-for="item in data.tenants_needing_help"
                :key="item.id"
                :title="item.name"
                :subtitle="item.reasons?.join(' · ')"
              >
                <template #append>
                  <VChip
                    size="x-small"
                    color="warning"
                  >
                    {{ item.health_score }}
                  </VChip>
                </template>
              </VListItem>
            </VList>
            <VCardText
              v-else
              class="text-medium-emphasis"
            >
              موردی یافت نشد
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12">
          <VCard title="ثبت‌نام‌های اخیر">
            <VDataTable
              :headers="[
                { title: 'مجموعه', key: 'name' },
                { title: 'مالک', key: 'owner_name' },
                { title: 'موبایل', key: 'owner_phone' },
                { title: 'پایه', key: 'has_core' },
                { title: 'تاریخ', key: 'created_at' },
              ]"
              :items="data.recent_signups ?? []"
              hide-default-footer
            >
              <template #item.has_core="{ item }">
                <VIcon
                  :icon="item.has_core ? 'tabler-check' : 'tabler-x'"
                  :color="item.has_core ? 'success' : 'error'"
                />
              </template>
              <template #item.created_at="{ item }">
                {{ formatDateTime(item.created_at) }}
              </template>
            </VDataTable>
          </VCard>
        </VCol>
      </VRow>
    </template>
  </div>
</template>
