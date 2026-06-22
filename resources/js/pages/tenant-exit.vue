<script setup>
const { exitTenantShell } = useAppShell()
const router = useRouter()

const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    await exitTenantShell()
  } catch (e) {
    console.error(e)
    error.value = 'خروج از مجموعه انجام نشد. دوباره تلاش کنید.'
    loading.value = false
  }
})

const goHome = async () => {
  await router.replace({ name: 'dashboards-home' })
}
</script>

<template>
  <div class="d-flex flex-column align-center justify-center pa-12 gap-4">
    <VProgressCircular
      v-if="loading && !error"
      indeterminate
      color="primary"
    />
    <VAlert
      v-if="error"
      type="error"
      variant="tonal"
      class="mb-2"
      max-width="420"
    >
      {{ error }}
    </VAlert>
    <VBtn
      v-if="error"
      color="primary"
      @click="goHome"
    >
      بازگشت به حساب کاربری
    </VBtn>
  </div>
</template>
