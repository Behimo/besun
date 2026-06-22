<script setup>
const { userData } = useAppShell()
const { formatDate } = useJalaliDate()

const subscription = ref(null)
const loading = ref(true)

const fetchData = async () => {
  loading.value = true
  try {
    subscription.value = await $api('/subscription')
  } finally {
    loading.value = false
  }
}

onMounted(fetchData)
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard :loading="loading">
        <VCardText>
          <h5 class="text-h5 mb-2">
            وضعیت اشتراک مجموعه
          </h5>
          <p class="text-body-2 text-medium-emphasis mb-4">
            مشاهده وضعیت — خرید و تمدید از
            <RouterLink :to="{ name: 'apps-account-modules' }">
              فروشگاه ماژول (حساب کاربری)
            </RouterLink>
          </p>

          <VAlert
            :type="subscription?.has_core_module ? 'success' : 'warning'"
            variant="tonal"
            class="mb-4"
          >
            {{ subscription?.has_core_module ? 'ماژول پایه فعال است' : 'ماژول پایه فعال نیست' }}
          </VAlert>

          <VList density="compact">
            <VListItem
              v-if="subscription?.tenant?.seat_limit"
              title="صندلی کارمند"
              :subtitle="`${subscription?.tenant?.seats_used ?? 0} / ${subscription?.tenant?.seat_limit} نفر`"
            />
            <VListItem
              v-if="subscription?.tenant?.core_expires_at"
              title="انقضای ماژول پایه"
              :subtitle="formatDate(subscription?.tenant?.core_expires_at)"
            />
          </VList>

          <div
            v-if="subscription?.active_modules?.length"
            class="mt-4"
          >
            <p class="text-body-2 mb-2">
              ماژول‌های فعال:
            </p>
            <VChip
              v-for="slug in subscription.active_modules"
              :key="slug"
              class="me-2 mb-2"
              color="primary"
              variant="tonal"
            >
              {{ slug }}
            </VChip>
          </div>

          <VBtn
            class="mt-6"
            color="primary"
            variant="tonal"
            :to="{ name: 'apps-account-modules' }"
          >
            مدیریت خرید در حساب کاربری
          </VBtn>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
