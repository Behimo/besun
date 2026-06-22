<script setup>
definePage({
  meta: {
    action: 'read',
    subject: 'Dashboard',
  },
})
</script>

<template>
  <VRow>
    <VCol cols="12">
      <div class="mb-4">
        <h4 class="text-h4 mb-1">
          تراکنش‌های من
        </h4>
        <p class="text-body-2 text-medium-emphasis mb-0">
          تاریخچه پرداخت‌های مجموعه‌هایی که مالک آن‌ها هستید.
        </p>
      </div>
      <AccountTransactionsTable embedded />
    </VCol>
  </VRow>
</template>
