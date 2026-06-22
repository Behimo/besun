<script setup>
const userData = useCookie('userData')

const stats = ref({ tenants: 0, active: 0, pending: 0 })

onMounted(async () => {
  try {
    const res = await $api('/tenants')
    const list = res.tenants ?? []
    stats.value = {
      tenants: list.length,
      active: list.filter(t => t.has_core_module).length,
      pending: list.filter(t => !t.has_core_module).length,
    }
  } catch {
    // ignore
  }
})
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard class="welcome-card overflow-hidden">
        <div class="welcome-card__bg" />
        <VCardText class="position-relative pa-6 pa-md-8">
          <VRow align="center">
            <VCol
              cols="12"
              md="8"
            >
              <h4 class="text-h4 text-white mb-2">
                سلام {{ userData?.fullName || 'کاربر' }}!
              </h4>
              <p class="text-body-1 text-white text-opacity-90 mb-4 mb-md-0">
                از اینجا مجموعه‌ها، خرید ماژول و تراکنش‌های خود را مدیریت کنید.
              </p>
            </VCol>
            <VCol
              cols="12"
              md="4"
              class="d-flex flex-wrap gap-3 justify-md-end"
            >
              <VBtn
                color="white"
                variant="elevated"
                :to="{ name: 'apps-account-tenants' }"
              >
                مجموعه‌های من
              </VBtn>
              <VBtn
                color="white"
                variant="outlined"
                :to="{ name: 'apps-account-modules' }"
              >
                فروشگاه ماژول
              </VBtn>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>

    <VCol
      cols="12"
      sm="4"
    >
      <VCard>
        <VCardText class="d-flex align-center gap-4">
          <VAvatar
            color="primary"
            variant="tonal"
            rounded
            size="48"
          >
            <VIcon icon="tabler-building" />
          </VAvatar>
          <div>
            <div class="text-h5">
              {{ stats.tenants }}
            </div>
            <div class="text-body-2 text-medium-emphasis">
              مجموعه
            </div>
          </div>
        </VCardText>
      </VCard>
    </VCol>
    <VCol
      cols="12"
      sm="4"
    >
      <VCard>
        <VCardText class="d-flex align-center gap-4">
          <VAvatar
            color="success"
            variant="tonal"
            rounded
            size="48"
          >
            <VIcon icon="tabler-circle-check" />
          </VAvatar>
          <div>
            <div class="text-h5">
              {{ stats.active }}
            </div>
            <div class="text-body-2 text-medium-emphasis">
              مجموعه فعال
            </div>
          </div>
        </VCardText>
      </VCard>
    </VCol>
    <VCol
      cols="12"
      sm="4"
    >
      <VCard>
        <VCardText class="d-flex align-center gap-4">
          <VAvatar
            color="warning"
            variant="tonal"
            rounded
            size="48"
          >
            <VIcon icon="tabler-shopping-cart" />
          </VAvatar>
          <div>
            <div class="text-h5">
              {{ stats.pending }}
            </div>
            <div class="text-body-2 text-medium-emphasis">
              نیاز به خرید
            </div>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.welcome-card {
  position: relative;
  border: none;
}

.welcome-card__bg {
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgb(var(--v-theme-primary)) 40%, rgba(var(--v-theme-primary), 0.75) 100%);
}
</style>
