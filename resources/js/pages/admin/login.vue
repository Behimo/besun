<script setup>
import PlatformStaffLoginForm from '@/components/auth/PlatformStaffLoginForm.vue'

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
    platformPortal: true,
    portal: 'admin',
  },
})
</script>

<template>
  <PlatformStaffLoginForm
    portal="admin"
    title="ورود مدیریت پلتفرم"
    subtitle="مدیر کل و کاربران اداری"
  />
</template>
