<script setup>
const { exitTenantShell } = useAppShell()
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
            برای استفاده از امکانات این مجموعه، ابتدا از حساب کاربری خود ماژول پایه را خریداری کنید.
            خرید فقط از بخش «فروشگاه ماژول» در حساب شخصی انجام می‌شود.
          </p>
          <div class="d-flex flex-wrap justify-center gap-3">
            <VBtn
              color="primary"
              :to="{ name: 'apps-account-modules' }"
              prepend-icon="tabler-shopping-cart"
            >
              فروشگاه ماژول
            </VBtn>
            <VBtn
              variant="tonal"
              prepend-icon="tabler-arrow-right"
              @click="exitTenantShell"
            >
              بازگشت به حساب کاربری
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
