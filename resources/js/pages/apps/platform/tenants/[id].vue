<script setup>
import { formatRial, healthColor } from '@/composables/usePlatformAdmin'

definePage({
  meta: {
    action: 'manage',
    subject: 'PlatformAdmin',
  },
})

const route = useRoute()
const { formatDateTime } = useJalaliDate()

const loading = ref(true)
const detail = ref(null)
const statusLoading = ref(false)

const tenantId = computed(() => route.params.id)

const fetchDetail = async () => {
  loading.value = true
  try {
    detail.value = await $api(`/platform/tenants/${tenantId.value}`)
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const toggleStatus = async () => {
  if (!detail.value?.tenant)
    return

  const next = detail.value.tenant.status === 'active' ? 'suspended' : 'active'
  statusLoading.value = true
  try {
    await $api(`/platform/tenants/${tenantId.value}/status`, {
      method: 'PATCH',
      body: { status: next },
    })
    await fetchDetail()
  } catch (e) {
    console.error(e)
  } finally {
    statusLoading.value = false
  }
}

onMounted(fetchDetail)
</script>

<template>
  <div v-if="loading">
    <VSkeletonLoader type="article" />
  </div>

  <template v-else-if="detail?.tenant">
    <div class="d-flex flex-wrap align-center justify-space-between gap-4 mb-6">
      <div>
        <h4 class="text-h4 mb-1">
          {{ detail.tenant.name }}
        </h4>
        <p class="text-body-2 text-medium-emphasis mb-0">
          {{ detail.tenant.slug }} · مالک: {{ detail.tenant.owner?.name }}
        </p>
      </div>
      <VBtn
        :color="detail.tenant.status === 'active' ? 'error' : 'success'"
        variant="tonal"
        :loading="statusLoading"
        @click="toggleStatus"
      >
        {{ detail.tenant.status === 'active' ? 'تعلیق مجموعه' : 'فعال‌سازی مجموعه' }}
      </VBtn>
    </div>

    <VRow>
      <VCol
        cols="12"
        md="4"
      >
        <VCard title="خلاصه">
          <VCardText>
            <VChip
              class="mb-3"
              :color="healthColor(detail.tenant.health_score)"
              variant="tonal"
            >
              سلامت: {{ detail.tenant.health_label }} ({{ detail.tenant.health_score }})
            </VChip>
            <div class="text-body-2 mb-2">
              اعضا: {{ detail.tenant.members_count }} / {{ detail.tenant.seat_limit ?? '∞' }}
            </div>
            <div class="text-body-2 mb-2">
              ماژول پایه: {{ detail.tenant.has_core_module ? 'فعال' : 'غیرفعال' }}
            </div>
            <div class="text-body-2 mb-2">
              پلن: {{ detail.tenant.plan ?? '—' }}
            </div>
            <div
              v-if="detail.tenant.active_modules?.length"
              class="d-flex flex-wrap gap-1 mt-3"
            >
              <VChip
                v-for="mod in detail.tenant.active_modules"
                :key="mod"
                size="x-small"
                variant="outlined"
              >
                {{ mod }}
              </VChip>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="8"
      >
        <VCard title="تراکنش‌های اخیر">
          <VDataTable
            :headers="[
              { title: '#', key: 'id' },
              { title: 'مبلغ', key: 'amount' },
              { title: 'وضعیت', key: 'status' },
              { title: 'خلاصه', key: 'summary' },
              { title: 'تاریخ', key: 'created_at' },
            ]"
            :items="detail.transactions ?? []"
            hide-default-footer
            class="text-no-wrap"
          >
            <template #item.amount="{ item }">
              {{ formatRial(item.amount) }}
            </template>
            <template #item.created_at="{ item }">
              {{ formatDateTime(item.created_at) }}
            </template>
          </VDataTable>
        </VCard>

        <VCard
          title="اعضا"
          class="mt-4"
        >
          <VDataTable
            :headers="[
              { title: 'نام', key: 'name' },
              { title: 'موبایل', key: 'phone' },
              { title: 'ایمیل', key: 'email' },
            ]"
            :items="detail.tenant.members ?? []"
            hide-default-footer
          />
        </VCard>
      </VCol>
    </VRow>
  </template>
</template>
